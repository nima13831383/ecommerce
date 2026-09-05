<?php

use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\PaymentGatewayConfiguration;
use App\Services\Payments\PaymentGatewayRegistry;
use App\Services\Payments\ZarinPalPaymentGateway;
use App\Services\Settings\SettingsService;
use App\Settings\SettingRegistry;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function configuredZarinPalSettings(): SettingsService
{
    $settings = app(SettingsService::class);
    $settings->update('payment.zarinpal.merchant_id', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
    $settings->update('payment.zarinpal.sandbox', true);
    $settings->update('payment.default_gateway', 'zarinpal');
    $settings->update('payment.zarinpal.enabled', true);

    return $settings;
}

function paymentSettingsEditor(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('settings.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('settings.update', 'web'));

    return $user;
}

test('payment core settings are registered and freshly persisted with safe defaults', function (): void {
    expect(SettingRegistry::coreDefinitions())->toHaveCount(12);

    foreach ([
        'payment.default_gateway',
        'payment.zarinpal.enabled',
        'payment.zarinpal.sandbox',
        'payment.zarinpal.merchant_id',
    ] as $key) {
        $definition = SettingRegistry::get($key);
        expect(Setting::query()->where('group', 'payment')->where('key', $key)->exists())->toBeTrue();
        expect(app(SettingsService::class)->get($key))->toBe($definition->default);
    }

    expect(SettingRegistry::get('payment.zarinpal.merchant_id')->secret)->toBeTrue()
        ->and(app(PaymentGatewayConfiguration::class)->zarinPal())->toBeNull();
});

test('merchant credentials are encrypted at rest and decrypted only through settings service', function (): void {
    $merchantId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    $settings = app(SettingsService::class);
    $settings->update('payment.zarinpal.merchant_id', $merchantId);

    $stored = Setting::query()->where('key', 'payment.zarinpal.merchant_id')->value('value');

    expect($stored)->not->toBe($merchantId)
        ->and($stored)->toBeString()
        ->and($settings->get('payment.zarinpal.merchant_id'))->toBe($merchantId)
        ->and(Setting::query()->where('key', 'payment.zarinpal.merchant_id')->firstOrFail()->typed_value)->not->toBe($merchantId);
});

test('payment configuration fails closed when disabled, incomplete, or sandboxed in production', function (): void {
    $settings = app(SettingsService::class);

    expect(app(PaymentGatewayConfiguration::class)->zarinPal())->toBeNull();
    expect(fn () => $settings->update('payment.zarinpal.enabled', true))->toThrow(ValidationException::class);

    configuredZarinPalSettings();
    expect(app(PaymentGatewayConfiguration::class)->zarinPal())->not->toBeNull()
        ->and(app(PaymentGatewayRegistry::class)->gateway('zarinpal'))->toBeInstanceOf(ZarinPalPaymentGateway::class);

    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(app(PaymentGatewayConfiguration::class)->zarinPal())->toBeNull();
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('payment settings reject malformed merchants and production sandbox writes', function (): void {
    $settings = app(SettingsService::class);

    expect(fn () => $settings->update('payment.zarinpal.merchant_id', 'not-a-merchant-id'))->toThrow(ValidationException::class);

    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(fn () => $settings->update('payment.zarinpal.sandbox', true))->toThrow(ValidationException::class);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('blank Filament merchant edit preserves the encrypted credential and rotation replaces it', function (): void {
    $merchantId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    $replacement = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
    $settings = app(SettingsService::class);
    $settings->update('payment.zarinpal.merchant_id', $merchantId);
    $setting = Setting::query()->where('key', 'payment.zarinpal.merchant_id')->firstOrFail();
    $originalCiphertext = $setting->value;

    Livewire::actingAs(paymentSettingsEditor(), 'web')
        ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
        ->assertFormSet(['value_secret' => null], 'form')
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertDontSee($merchantId);

    expect($setting->fresh()->value)->toBe($originalCiphertext)
        ->and($settings->get('payment.zarinpal.merchant_id'))->toBe($merchantId);

    Livewire::actingAs(paymentSettingsEditor(), 'web')
        ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
        ->fillForm(['value_secret' => $replacement], 'form')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($setting->fresh()->value)->not->toBe($originalCiphertext)
        ->and($setting->fresh()->value)->not->toBe($replacement)
        ->and($settings->get('payment.zarinpal.merchant_id'))->toBe($replacement);
});

test('Filament can safely disable and re-enable an otherwise complete ZarinPal configuration', function (): void {
    configuredZarinPalSettings();
    $setting = Setting::query()->where('key', 'payment.zarinpal.enabled')->firstOrFail();
    $editor = paymentSettingsEditor();

    Livewire::actingAs($editor, 'web')
        ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
        ->assertDontSee('fake')
        ->fillForm(['value_boolean' => false], 'form')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(PaymentGatewayConfiguration::class)->zarinPal())->toBeNull();

    Livewire::actingAs($editor, 'web')
        ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
        ->fillForm(['value_boolean' => true], 'form')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(PaymentGatewayConfiguration::class)->zarinPal())->not->toBeNull();
});

test('Filament rejects enabling ZarinPal before its gateway and merchant are configured', function (): void {
    $setting = Setting::query()->where('key', 'payment.zarinpal.enabled')->firstOrFail();

    Livewire::actingAs(paymentSettingsEditor(), 'web')
        ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
        ->fillForm(['value_boolean' => true], 'form')
        ->call('save')
        ->assertHasFormErrors(['value_boolean']);

    expect(app(SettingsService::class)->get('payment.zarinpal.enabled'))->toBeFalse();
});

test('settings status and legacy import never expose a merchant credential', function (): void {
    $merchantId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    config([
        'payment.legacy.default_gateway' => 'zarinpal',
        'payment.legacy.zarinpal.merchant_id' => $merchantId,
        'payment.legacy.zarinpal.sandbox' => true,
    ]);

    $this->artisan('payment:import-zarinpal-env', ['--dry-run' => true])
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('Dry run: no settings were written.');
    expect(app(SettingsService::class)->get('payment.zarinpal.merchant_id'))->toBeNull();

    $this->artisan('payment:import-zarinpal-env')
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('Merchant credential is encrypted');
    expect(Setting::query()->where('key', 'payment.zarinpal.merchant_id')->value('value'))->not->toBe($merchantId);

    $this->artisan('settings:status')
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('Registered: 12')
        ->doesntExpectOutputToContain($merchantId);
    $this->artisan('payment:diagnose-zarinpal')
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('Merchant configured: yes')
        ->doesntExpectOutputToContain($merchantId);
});

test('legacy import does not overwrite an existing merchant unless forced', function (): void {
    $settings = configuredZarinPalSettings();
    $original = $settings->get('payment.zarinpal.merchant_id');
    config([
        'payment.legacy.default_gateway' => 'zarinpal',
        'payment.legacy.zarinpal.merchant_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        'payment.legacy.zarinpal.sandbox' => false,
    ]);

    $this->artisan('payment:import-zarinpal-env')->assertExitCode(Command::SUCCESS);
    expect($settings->get('payment.zarinpal.merchant_id'))->toBe($original);

    $this->artisan('payment:import-zarinpal-env', ['--force' => true])->assertExitCode(Command::SUCCESS);
    expect($settings->get('payment.zarinpal.merchant_id'))->toBe('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
});
