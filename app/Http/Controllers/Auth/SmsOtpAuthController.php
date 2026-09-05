<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\OtpException;
use App\Exceptions\SmsGatewayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SmsOtpRequest;
use App\Http\Requests\Auth\SmsOtpVerificationRequest;
use App\Http\Requests\Auth\SmsRegistrationOtpRequest;
use App\Models\AuthOtpChallenge;
use App\Models\User;
use App\Services\Auth\CustomerOtpService;
use App\Services\Settings\SettingsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SmsOtpAuthController extends Controller
{
    public function requestLogin(SmsOtpRequest $request, CustomerOtpService $otp): RedirectResponse
    {
        $this->assertSmsMode();
        $mobile = $this->normalizedMobile($otp, $request->string('mobile')->toString());
        $user = User::query()->where('mobile', $mobile)->whereNotNull('mobile_verified_at')->first();

        if ($user === null) {
            return back()->withInput()->withErrors(['mobile' => 'اطلاعات ورود معتبر نیست.']);
        }

        $challenge = $this->requestOtp($otp, $mobile, AuthOtpChallenge::PURPOSE_LOGIN, $request->ip());
        $request->session()->put('auth.otp.login_mobile', $mobile);
        $request->session()->put('auth.otp.login_challenge_id', $challenge->getKey());

        return redirect()->route('login')->with('status', 'otp-sent');
    }

    public function verifyLogin(SmsOtpVerificationRequest $request, CustomerOtpService $otp): RedirectResponse
    {
        $this->assertSmsMode();
        $mobile = $this->normalizedMobile($otp, $request->string('mobile')->toString());

        if ($request->session()->get('auth.otp.login_mobile') !== $mobile) {
            return redirect()->route('login')->withErrors(['mobile' => 'درخواست تأیید معتبر نیست.']);
        }

        try {
            $otp->verify($mobile, AuthOtpChallenge::PURPOSE_LOGIN, $request->string('code')->toString());
        } catch (OtpException $exception) {
            return back()->withInput()->withErrors(['code' => $exception->getMessage()]);
        }

        $user = User::query()->where('mobile', $mobile)->whereNotNull('mobile_verified_at')->first();
        if ($user === null) {
            return redirect()->route('login')->withErrors(['mobile' => 'اطلاعات ورود معتبر نیست.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(['auth.otp.login_mobile', 'auth.otp.login_challenge_id']);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function requestRegistration(SmsRegistrationOtpRequest $request, CustomerOtpService $otp): RedirectResponse
    {
        $this->assertSmsMode();
        $mobile = $this->normalizedMobile($otp, $request->string('mobile')->toString());

        if (User::query()->where('mobile', $mobile)->exists()) {
            return back()->withInput()->withErrors(['mobile' => 'این شماره موبایل قبلاً ثبت شده است.']);
        }

        $this->requestOtp($otp, $mobile, AuthOtpChallenge::PURPOSE_REGISTER, $request->ip());
        $request->session()->put('auth.otp.register_profile', [
            'name' => $request->string('name')->toString(),
            'mobile' => $mobile,
        ]);

        return redirect()->route('register')->with('status', 'otp-sent');
    }

    public function verifyRegistration(SmsOtpVerificationRequest $request, CustomerOtpService $otp): RedirectResponse
    {
        $this->assertSmsMode();
        $mobile = $this->normalizedMobile($otp, $request->string('mobile')->toString());
        $profile = $request->session()->get('auth.otp.register_profile');

        if (! is_array($profile) || ($profile['mobile'] ?? null) !== $mobile) {
            return redirect()->route('register')->withErrors(['mobile' => 'درخواست تأیید معتبر نیست.']);
        }

        try {
            $otp->verify($mobile, AuthOtpChallenge::PURPOSE_REGISTER, $request->string('code')->toString());
        } catch (OtpException $exception) {
            return back()->withInput()->withErrors(['code' => $exception->getMessage()]);
        }

        try {
            $user = DB::transaction(fn (): User => User::query()->create([
                'name' => $profile['name'],
                'mobile' => $mobile,
                'mobile_verified_at' => now(),
                'status' => 'active',
            ]));
        } catch (QueryException) {
            return redirect()->route('register')->withErrors(['mobile' => 'این شماره موبایل قبلاً ثبت شده است.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('auth.otp.register_profile');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resendLogin(SmsOtpRequest $request, CustomerOtpService $otp): RedirectResponse
    {
        return $this->requestLogin($request, $otp);
    }

    public function changeLoginMobile(Request $request, CustomerOtpService $otp): RedirectResponse
    {
        $this->assertSmsMode();

        $challengeId = $request->session()->get('auth.otp.login_challenge_id');
        $mobile = $request->session()->get('auth.otp.login_mobile');

        if (is_string($challengeId) && is_string($mobile)) {
            $otp->invalidate($challengeId, $mobile, AuthOtpChallenge::PURPOSE_LOGIN);
        }

        $request->session()->forget(['auth.otp.login_mobile', 'auth.otp.login_challenge_id']);

        return redirect()->route('login');
    }

    public function resendRegistration(SmsRegistrationOtpRequest $request, CustomerOtpService $otp): RedirectResponse
    {
        return $this->requestRegistration($request, $otp);
    }

    private function requestOtp(CustomerOtpService $otp, string $mobile, string $purpose, string $ip): AuthOtpChallenge
    {
        try {
            return $otp->request($mobile, $purpose, $ip);
        } catch (OtpException|SmsGatewayException $exception) {
            throw ValidationException::withMessages(['mobile' => $exception->getMessage()]);
        }
    }

    private function normalizedMobile(CustomerOtpService $otp, string $mobile): string
    {
        try {
            return $otp->normalizeMobile($mobile);
        } catch (OtpException $exception) {
            throw ValidationException::withMessages(['mobile' => $exception->getMessage()]);
        }
    }

    private function assertSmsMode(): void
    {
        if (app(SettingsService::class)->get('auth.customer_auth_mode') !== 'sms_otp') {
            abort(404);
        }
    }
}
