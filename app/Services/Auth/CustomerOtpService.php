<?php

namespace App\Services\Auth;

use App\Contracts\Sms\OtpSenderInterface;
use App\Exceptions\OtpException;
use App\Models\AuthOtpChallenge;
use App\Services\Settings\SettingsService;
use App\Services\Sms\SmsGatewayConfiguration;
use App\Support\IranianMobileNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class CustomerOtpService
{
    public function __construct(
        private readonly OtpSenderInterface $sender,
        private readonly SettingsService $settings,
    ) {}

    public function request(string $mobile, string $purpose, string $ip): AuthOtpChallenge
    {
        $mobile = $this->normalizeMobile($mobile);
        $this->assertPurpose($purpose);
        $this->assertSmsModeOperational();

        $throttleKey = 'otp:send:'.hash('sha256', $mobile.'|'.$ip.'|'.$purpose);
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw new OtpException('لطفاً چند دقیقه دیگر دوباره تلاش کنید.');
        }

        $cooldown = (int) $this->settings->get('auth.otp.resend_cooldown_seconds');
        $previous = AuthOtpChallenge::query()->usable()
            ->where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->latest('sent_at')
            ->first();

        if ($previous !== null && $previous->sent_at->addSeconds($cooldown)->isFuture()) {
            throw new OtpException('لطفاً پس از گذشت زمان ارسال مجدد دوباره تلاش کنید.');
        }

        $code = $this->code();

        try {
            $this->sender->sendVerificationCode($mobile, $code);
        } catch (\Throwable $exception) {
            RateLimiter::hit($throttleKey, 60);

            throw $exception;
        }

        RateLimiter::hit($throttleKey, 60);

        return DB::transaction(function () use ($mobile, $purpose, $code): AuthOtpChallenge {
            AuthOtpChallenge::query()
                ->usable()
                ->where('mobile', $mobile)
                ->where('purpose', $purpose)
                ->update(['invalidated_at' => now()]);

            return AuthOtpChallenge::query()->create([
                'id' => (string) Str::ulid(),
                'mobile' => $mobile,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addSeconds((int) $this->settings->get('auth.otp.ttl_seconds')),
                'attempts' => 0,
                'max_attempts' => (int) $this->settings->get('auth.otp.max_attempts'),
                'sent_at' => now(),
            ]);
        });
    }

    public function verify(string $mobile, string $purpose, string $code): AuthOtpChallenge
    {
        $mobile = $this->normalizeMobile($mobile);
        $this->assertPurpose($purpose);
        $code = $this->normalizeCode($code);

        $verified = DB::transaction(function () use ($mobile, $purpose, $code): ?AuthOtpChallenge {
            $challenge = AuthOtpChallenge::query()
                ->where('mobile', $mobile)
                ->where('purpose', $purpose)
                ->latest('sent_at')
                ->lockForUpdate()
                ->first();

            if ($challenge === null || $challenge->consumed_at !== null || $challenge->invalidated_at !== null || $challenge->expires_at->isPast() || $challenge->attempts >= $challenge->max_attempts) {
                return null;
            }

            if (! Hash::check($code, $challenge->code_hash)) {
                $challenge->increment('attempts');

                return null;
            }

            $challenge->update(['consumed_at' => now()]);

            return $challenge;
        });

        if ($verified === null) {
            throw new OtpException('کد تأیید معتبر نیست یا صحیح نیست.');
        }

        return $verified;
    }

    /** @return array{available_at: int, remaining_seconds: int}|null */
    public function resendState(?string $challengeId, ?string $mobile, string $purpose): ?array
    {
        if (! is_string($challengeId) || ! is_string($mobile)) {
            return null;
        }

        $challenge = AuthOtpChallenge::query()
            ->usable()
            ->whereKey($challengeId)
            ->where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->first();

        if ($challenge === null) {
            return null;
        }

        $availableAt = $challenge->sent_at->copy()->addSeconds((int) $this->settings->get('auth.otp.resend_cooldown_seconds'));

        return [
            'available_at' => $availableAt->getTimestamp(),
            'remaining_seconds' => max(0, now()->diffInSeconds($availableAt, false)),
        ];
    }

    public function invalidate(string $challengeId, string $mobile, string $purpose): void
    {
        AuthOtpChallenge::query()
            ->usable()
            ->whereKey($challengeId)
            ->where('mobile', $this->normalizeMobile($mobile))
            ->where('purpose', $purpose)
            ->update(['invalidated_at' => now()]);
    }

    public function normalizeMobile(string $mobile): string
    {
        try {
            return IranianMobileNumber::normalize($mobile);
        } catch (\InvalidArgumentException) {
            throw new OtpException('شماره موبایل واردشده معتبر نیست.');
        }
    }

    private function code(): string
    {
        $length = (int) $this->settings->get('auth.otp.code_length');

        if (app()->environment(['local', 'testing']) && $this->settings->get('sms.smsir.sandbox') === true) {
            return '12345';
        }

        $minimum = 10 ** ($length - 1);
        $maximum = (10 ** $length) - 1;

        return (string) random_int($minimum, $maximum);
    }

    private function normalizeCode(string $code): string
    {
        return strtr(trim($code), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private function assertPurpose(string $purpose): void
    {
        if (! in_array($purpose, [AuthOtpChallenge::PURPOSE_LOGIN, AuthOtpChallenge::PURPOSE_REGISTER], true)) {
            throw new OtpException('هدف کد تأیید معتبر نیست.');
        }
    }

    private function assertSmsModeOperational(): void
    {
        if ($this->settings->get('auth.customer_auth_mode') !== 'sms_otp' || app(SmsGatewayConfiguration::class)->operational() === false) {
            throw new OtpException('ورود پیامکی در حال حاضر در دسترس نیست.');
        }
    }
}
