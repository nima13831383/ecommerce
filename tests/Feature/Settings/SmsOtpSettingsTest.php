<?php

use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Services\Sms\SmsGatewayConfiguration;
use App\Services\Sms\SmsIrClientFactory;
use App\Services\Sms\SmsIrOtpSender;
use App\Settings\SettingRegistry;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Ipe\Sdk\SmsIrResult;
use Ipe\Sdk\SmsIrService;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

class RecordingSmsIrService extends SmsIrService
{
    /** @var array{mobile: string, template_id: int, parameters: array<int, array{name: string, value: string}>}|null */
    public ?array $request = null;

    public function __construct() {}

    public function verifySend($mobile, $templateId, $parameters): SmsIrResult
    {
        $this->request = [
            'mobile' => $mobile,
            'template_id' => $templateId,
            'parameters' => $parameters,
        ];

        return new SmsIrResult(1, 'ok');
    }
}

class RecordingSmsIrFactory extends SmsIrClientFactory
{
    public ?string $apiKey = null;

    public RecordingSmsIrService $service;

    public function __construct()
    {
        $this->service = new RecordingSmsIrService;
    }

    public function make(string $apiKey): SmsIrService
    {
        $this->apiKey = $apiKey;

        return $this->service;
    }
}

function smsSettingsEditor(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('settings.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('settings.update', 'web'));

    return $user;
}

test('sms otp core settings are registered, persisted, and safe by default', function (): void {
    $keys = [
        'auth.customer_auth_mode', 'auth.otp.code_length', 'auth.otp.ttl_seconds',
        'auth.otp.resend_cooldown_seconds', 'auth.otp.max_attempts', 'sms.default_provider',
        'sms.smsir.enabled', 'sms.smsir.sandbox', 'sms.smsir.api_key',
        'sms.smsir.verify_template_id', 'sms.smsir.verify_parameter_name',
    ];

    foreach ($keys as $key) {
        expect(SettingRegistry::has($key))->toBeTrue()
            ->and(Setting::query()->where('key', $key)->exists())->toBeTrue();
    }

    expect(app(SettingsService::class)->get('auth.customer_auth_mode'))->toBe('email_password')
        ->and(app(SettingsService::class)->get('sms.smsir.enabled'))->toBeFalse()
        ->and(app(SettingsService::class)->get('sms.smsir.sandbox'))->toBeTrue()
        ->and(SettingRegistry::get('sms.smsir.api_key')->secret)->toBeTrue();
});

test('sms mode validates complete configuration and fails closed in production sandbox', function (): void {
    $settings = app(SettingsService::class);
    expect(fn () => $settings->update('auth.customer_auth_mode', 'sms_otp'))->toThrow(ValidationException::class);

    $settings->update('sms.smsir.api_key', 'sandbox-api-key-for-testing');
    $settings->update('sms.smsir.enabled', true);
    $settings->update('auth.customer_auth_mode', 'sms_otp');
    expect(app(SmsGatewayConfiguration::class)->operational())->toBeTrue();

    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(app(SmsGatewayConfiguration::class)->operational())->toBeFalse()
            ->and(fn () => $settings->update('sms.smsir.sandbox', true))->toThrow(ValidationException::class);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('sms secrets remain masked in Filament and can be rotated without disclosure', function (): void {
    $settings = app(SettingsService::class);
    $first = 'sandbox-api-key-for-testing';
    $second = 'replacement-sandbox-api-key';
    $settings->update('sms.smsir.api_key', $first);
    $setting = Setting::query()->where('key', 'sms.smsir.api_key')->firstOrFail();
    $ciphertext = $setting->value;

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::actingAs(smsSettingsEditor(), 'web')
        ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
        ->assertFormSet(['value_secret' => null], 'form')
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertDontSee($first);

    expect($setting->fresh()->value)->toBe($ciphertext);

    Livewire::actingAs(smsSettingsEditor(), 'web')
        ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
        ->fillForm(['value_secret' => $second], 'form')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($setting->fresh()->value)->not->toBe($second)
        ->and($settings->get('sms.smsir.api_key'))->toBe($second);
});

test('settings and sms diagnostics never print the sms api key', function (): void {
    $key = 'sandbox-api-key-for-testing';
    app(SettingsService::class)->update('sms.smsir.api_key', $key);

    $this->artisan('settings:status')
        ->assertExitCode(Command::SUCCESS)
        ->doesntExpectOutputToContain($key);
    $this->artisan('sms:status')
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('Effective template ID: 123456')
        ->doesntExpectOutputToContain($key);
});

test('official SDK adapter uses runtime settings and forces the sandbox verify contract', function (): void {
    $settings = app(SettingsService::class);
    $settings->update('sms.smsir.api_key', 'sandbox-api-key-for-testing');
    $settings->update('sms.smsir.enabled', true);
    $factory = new RecordingSmsIrFactory;
    $sender = new SmsIrOtpSender(app(SmsGatewayConfiguration::class), $factory);

    $sender->sendVerificationCode('09123456789', '12345');
    expect($factory->apiKey)->toBe('sandbox-api-key-for-testing')
        ->and($factory->service->request)->toBe([
            'mobile' => '09123456789',
            'template_id' => 123456,
            'parameters' => [['name' => 'CODE', 'value' => '12345']],
        ]);

    $settings->update('sms.smsir.sandbox', false);
    $settings->update('sms.smsir.verify_template_id', 991122);
    $settings->update('sms.smsir.verify_parameter_name', 'VERIFY_CODE');
    $sender->sendVerificationCode('09123456789', '45678');

    expect($factory->service->request)->toBe([
        'mobile' => '09123456789',
        'template_id' => 991122,
        'parameters' => [['name' => 'VERIFY_CODE', 'value' => '45678']],
    ]);
});
