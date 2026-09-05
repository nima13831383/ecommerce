<?php

use App\Contracts\Sms\OtpSenderInterface;
use App\Exceptions\OtpException;
use App\Exceptions\SmsGatewayException;
use App\Models\AuthOtpChallenge;
use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\CustomerOtpService;
use App\Services\Settings\SettingsService;
use App\Support\IranianMobileNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FakeOtpSender implements OtpSenderInterface
{
    /** @var array<int, array{mobile: string, code: string}> */
    public array $sent = [];

    public bool $fails = false;

    public function sendVerificationCode(string $mobile, string $code): void
    {
        if ($this->fails) {
            throw new SmsGatewayException('SMS provider is temporarily unavailable.');
        }

        $this->sent[] = compact('mobile', 'code');
    }
}

function smsOtpSettings(): SettingsService
{
    $settings = app(SettingsService::class);
    $settings->update('sms.smsir.api_key', 'sandbox-api-key-for-testing');
    $settings->update('sms.smsir.enabled', true);
    $settings->update('auth.customer_auth_mode', 'sms_otp');

    return $settings;
}

function fakeOtpSender(): FakeOtpSender
{
    $sender = new FakeOtpSender;
    app()->instance(OtpSenderInterface::class, $sender);

    return $sender;
}

test('iranian mobile input has one normalized representation', function (): void {
    expect(IranianMobileNumber::normalize('۰۹۱۲۳۴۵۶۷۸۹'))->toBe('09123456789')
        ->and(IranianMobileNumber::normalize('+989123456789'))->toBe('09123456789')
        ->and(IranianMobileNumber::normalize('989123456789'))->toBe('09123456789');

    expect(fn () => IranianMobileNumber::normalize('0912'))->toThrow(InvalidArgumentException::class);
});

test('otp challenges are hashed, purpose-bound, single-use, and expire', function (): void {
    smsOtpSettings();
    $sender = fakeOtpSender();
    $service = app(CustomerOtpService::class);
    $challenge = $service->request('۰۹۱۲۳۴۵۶۷۸۹', AuthOtpChallenge::PURPOSE_LOGIN, '127.0.0.1');

    expect($challenge->mobile)->toBe('09123456789')
        ->and($challenge->code_hash)->not->toBe('12345')
        ->and(Hash::check('12345', $challenge->code_hash))->toBeTrue()
        ->and($sender->sent)->toHaveCount(1);

    expect(fn () => $service->verify('09123456789', AuthOtpChallenge::PURPOSE_REGISTER, '12345'))->toThrow(OtpException::class);
    $service->verify('09123456789', AuthOtpChallenge::PURPOSE_LOGIN, '۱۲۳۴۵');
    expect($challenge->fresh()->consumed_at)->not->toBeNull();
    expect(fn () => $service->verify('09123456789', AuthOtpChallenge::PURPOSE_LOGIN, '12345'))->toThrow(OtpException::class);

    $expired = AuthOtpChallenge::query()->create([
        'mobile' => '09121111111', 'purpose' => AuthOtpChallenge::PURPOSE_LOGIN,
        'code_hash' => Hash::make('12345'), 'expires_at' => now()->subSecond(), 'max_attempts' => 5, 'sent_at' => now()->subMinute(),
    ]);
    expect(fn () => $service->verify($expired->mobile, $expired->purpose, '12345'))->toThrow(OtpException::class);
});

test('failed attempts and resend cooldown are server enforced', function (): void {
    $settings = smsOtpSettings();
    $sender = fakeOtpSender();
    $service = app(CustomerOtpService::class);
    $challenge = $service->request('09121234567', AuthOtpChallenge::PURPOSE_LOGIN, '127.0.0.1');

    expect(fn () => $service->request('09121234567', AuthOtpChallenge::PURPOSE_LOGIN, '127.0.0.1'))->toThrow(OtpException::class);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $service->verify('09121234567', AuthOtpChallenge::PURPOSE_LOGIN, '99999');
        } catch (OtpException) {
            // The terminal attempt is intentionally rejected too.
        }
    }
    expect($challenge->fresh()->attempts)->toBe(5)
        ->and(fn () => $service->verify('09121234567', AuthOtpChallenge::PURPOSE_LOGIN, '12345'))->toThrow(OtpException::class)
        ->and($sender->sent)->toHaveCount(1);

    $settings->update('auth.otp.resend_cooldown_seconds', 30);
});

test('sms login authenticates a verified mobile without using a password', function (): void {
    smsOtpSettings();
    fakeOtpSender();
    $user = User::factory()->create(['mobile' => '09123456789', 'mobile_verified_at' => now(), 'password' => null]);

    $this->post('/login/otp', ['mobile' => '+989123456789'])
        ->assertRedirect(route('login'));
    $this->get('/login')
        ->assertSee('کد تأیید ارسال شد.')
        ->assertDontSee('otp-sent');
    $this->post('/login/otp/verify', ['mobile' => '09123456789', 'code' => '12345'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);

    $this->post('/logout');
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertNotFound();
});

test('unknown sms login does not create a user and sms registration only creates after verification', function (): void {
    smsOtpSettings();
    fakeOtpSender();

    $this->post('/login/otp', ['mobile' => '09120000000'])->assertSessionHasErrors('mobile');
    expect(User::query()->where('mobile', '09120000000')->exists())->toBeFalse();

    $this->post('/register/otp', ['name' => 'SMS Customer', 'mobile' => '09120000000'])->assertRedirect(route('register'));
    expect(User::query()->where('mobile', '09120000000')->exists())->toBeFalse();
    $this->post('/register/otp/verify', ['mobile' => '09120000000', 'code' => '12345'])
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('mobile', '09120000000')->firstOrFail();
    expect($user->email)->toBeNull()->and($user->password)->toBeNull()->and($user->mobile_verified_at)->not->toBeNull();
});

test('email password mode keeps Breeze screens and does not send otp', function (): void {
    $sender = fakeOtpSender();
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->get('/login')->assertOk()->assertSee('name="email"', false)->assertSee('forgot-password');
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));
    expect($sender->sent)->toBeEmpty();
});

test('provider failure does not persist a delivered otp challenge', function (): void {
    smsOtpSettings();
    $sender = fakeOtpSender();
    $sender->fails = true;

    expect(fn () => app(CustomerOtpService::class)->request('09121234567', AuthOtpChallenge::PURPOSE_LOGIN, '127.0.0.1'))
        ->toThrow(SmsGatewayException::class);
    expect(AuthOtpChallenge::query()->count())->toBe(0);
});

test('a provider failure does not show the sent state or local sandbox helper', function (): void {
    smsOtpSettings();
    $sender = fakeOtpSender();
    $sender->fails = true;
    User::factory()->create(['mobile' => '09121234567', 'mobile_verified_at' => now()]);

    $this->post('/login/otp', ['mobile' => '09121234567'])
        ->assertSessionHasErrors('mobile')
        ->assertSessionMissing('auth.otp.login_mobile');

    $this->get('/login')
        ->assertDontSee('کد تأیید ارسال شد.')
        ->assertDontSee('کد تست Sandbox: ۱۲۳۴۵');
});

test('deterministic local sandbox otp does not apply in production', function (): void {
    $settings = smsOtpSettings();
    $settings->update('sms.smsir.verify_template_id', 991122);
    $settings->update('sms.smsir.verify_parameter_name', 'VERIFY_CODE');
    $settings->update('sms.smsir.sandbox', false);
    $settings->update('auth.otp.code_length', 6);
    $sender = fakeOtpSender();

    app()->detectEnvironment(fn (): string => 'production');
    try {
        app(CustomerOtpService::class)->request('09129876543', AuthOtpChallenge::PURPOSE_LOGIN, '127.0.0.1');
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }

    expect($sender->sent)->toHaveCount(1)
        ->and($sender->sent[0]['code'])->toMatch('/^\d{6}$/')
        ->and($sender->sent[0]['code'])->not->toBe('12345');
});

test('sms secret stays encrypted and auth mode cannot activate with incomplete configuration', function (): void {
    $settings = app(SettingsService::class);
    expect(fn () => $settings->update('auth.customer_auth_mode', 'sms_otp'))->toThrow(ValidationException::class);

    $settings->update('sms.smsir.api_key', 'sandbox-api-key-for-testing');
    $stored = Setting::query()->where('key', 'sms.smsir.api_key')->value('value');
    expect($stored)->not->toBe('sandbox-api-key-for-testing')
        ->and($settings->get('sms.smsir.api_key'))->toBe('sandbox-api-key-for-testing');
});

test('login otp view exposes server-authoritative cooldown that survives reload', function (): void {
    $settings = smsOtpSettings();
    $settings->update('auth.otp.resend_cooldown_seconds', 60);
    fakeOtpSender();
    User::factory()->create(['mobile' => '09125555555', 'mobile_verified_at' => now()]);

    Carbon::setTestNow('2026-09-05 12:00:00');
    try {
        $this->post('/login/otp', ['mobile' => '09125555555'])->assertRedirect(route('login'));
        $availableAt = now()->addSeconds(60)->getTimestamp();

        $this->get('/login')
            ->assertSee('data-resend-available-at="'.$availableAt.'"', false)
            ->assertSee('ارسال مجدد کد تا ۰۱:۰۰')
            ->assertSee('disabled');

        Carbon::setTestNow(now()->addSeconds(20));
        $this->get('/login')->assertSee('ارسال مجدد کد تا ۰۰:۴۰');

        Carbon::setTestNow(now()->addSeconds(40));
        $this->get('/login')
            ->assertSee('ارسال مجدد کد')
            ->assertDontSee('ارسال مجدد کد تا');
    } finally {
        Carbon::setTestNow();
    }
});

test('change mobile invalidates the trusted challenge and restores mobile entry', function (): void {
    smsOtpSettings();
    fakeOtpSender();
    User::factory()->create(['mobile' => '09126666666', 'mobile_verified_at' => now()]);
    User::factory()->create(['mobile' => '09127777777', 'mobile_verified_at' => now()]);

    $this->post('/login/otp', ['mobile' => '09126666666'])->assertRedirect(route('login'));
    $challenge = AuthOtpChallenge::query()->latest('sent_at')->firstOrFail();

    $this->post('/login/otp/change-mobile')
        ->assertRedirect(route('login'))
        ->assertSessionMissing('auth.otp.login_mobile')
        ->assertSessionMissing('auth.otp.login_challenge_id');

    expect($challenge->fresh()->invalidated_at)->not->toBeNull();
    $this->get('/login')
        ->assertSee('name="mobile"', false)
        ->assertDontSee('name="code"', false);

    $this->post('/login/otp/verify', ['mobile' => '09126666666', 'code' => '12345'])
        ->assertRedirect(route('login'));
    $this->assertGuest();

    $this->post('/login/otp', ['mobile' => '09127777777'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('auth.otp.login_mobile', '09127777777');
});

test('resend remains server-protected and safely replaces the challenge after cooldown', function (): void {
    $settings = smsOtpSettings();
    $settings->update('auth.otp.resend_cooldown_seconds', 60);
    $sender = fakeOtpSender();
    User::factory()->create(['mobile' => '09128888888', 'mobile_verified_at' => now()]);

    Carbon::setTestNow('2026-09-05 12:00:00');
    try {
        $this->post('/login/otp', ['mobile' => '09128888888'])->assertRedirect(route('login'));
        $first = AuthOtpChallenge::query()->latest('sent_at')->firstOrFail();

        $this->post('/login/otp/resend', ['mobile' => '09128888888'])
            ->assertSessionHasErrors('mobile');
        expect($sender->sent)->toHaveCount(1);

        Carbon::setTestNow(now()->addSeconds(60));
        $this->post('/login/otp/resend', ['mobile' => '09128888888'])
            ->assertRedirect(route('login'));

        $second = AuthOtpChallenge::query()->latest('sent_at')->firstOrFail();
        expect($sender->sent)->toHaveCount(2)
            ->and($second->id)->not->toBe($first->id)
            ->and($first->fresh()->invalidated_at)->not->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

test('a failed resend does not replace the current challenge or show sent success', function (): void {
    $settings = smsOtpSettings();
    $settings->update('auth.otp.resend_cooldown_seconds', 30);
    $sender = fakeOtpSender();
    User::factory()->create(['mobile' => '09129999999', 'mobile_verified_at' => now()]);

    $this->post('/login/otp', ['mobile' => '09129999999'])->assertRedirect(route('login'));
    $challenge = AuthOtpChallenge::query()->latest('sent_at')->firstOrFail();
    $sender->fails = true;

    Carbon::setTestNow(now()->addSeconds(30));
    try {
        $this->post('/login/otp/resend', ['mobile' => '09129999999'])
            ->assertSessionHasErrors('mobile');
    } finally {
        Carbon::setTestNow();
    }

    expect(AuthOtpChallenge::query()->count())->toBe(1)
        ->and($challenge->fresh()->invalidated_at)->toBeNull();
});
