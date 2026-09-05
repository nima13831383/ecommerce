@extends('storefront.layouts.auth')

@section('auth-content')
    <div class="auth-container auth-shell">
        <section class="auth-card" aria-labelledby="login-title">
            <div class="auth-card__icon" aria-hidden="true">🔒</div>
            <h1 id="login-title">ورود به حساب کاربری</h1>
            <p class="auth-card__support">برای ادامه خرید وارد حساب کاربری خود شوید.</p>

            @if (session('status') === 'otp-sent')
                <p class="auth-message" role="status">کد تأیید ارسال شد.</p>
            @elseif (session('status'))
                <p class="auth-message" role="status">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <div class="auth-message" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (($customerAuthMode ?? 'email_password') === 'sms_otp')
                @php($otpMobile = session('auth.otp.login_mobile'))
                @if ($otpMobile)
                    @php($remainingSeconds = (int) ($resendState['remaining_seconds'] ?? 0))
                    @php($formattedRemaining = sprintf('%02d:%02d', intdiv($remainingSeconds, 60), $remainingSeconds % 60))
                    <form class="auth-form" method="POST" action="{{ route('login.otp.verify') }}">
                        @csrf
                        <input type="hidden" name="mobile" value="{{ $otpMobile }}">
                        <label class="auth-field" for="code">کد تأیید
                            <span class="auth-input-wrap"><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus></span>
                        </label>
                        @if (app()->environment(['local', 'testing']) && app(\App\Services\Settings\SettingsService::class)->get('sms.smsir.sandbox') === true)
                            <p class="auth-message" role="status">کد تست Sandbox: ۱۲۳۴۵</p>
                        @endif
                        <button class="auth-submit" type="submit">تأیید و ورود</button>
                    </form>
                    <div class="otp-resend" data-otp-resend data-resend-available-at="{{ $resendState['available_at'] ?? now()->getTimestamp() }}">
                        <form method="POST" action="{{ route('login.otp.resend') }}">
                            @csrf
                            <input type="hidden" name="mobile" value="{{ $otpMobile }}">
                            <button class="otp-resend__button" type="submit" @disabled($remainingSeconds > 0) aria-disabled="{{ $remainingSeconds > 0 ? 'true' : 'false' }}">
                                <span data-otp-resend-label>@if ($remainingSeconds > 0)ارسال مجدد کد تا {{ \App\Support\PersianNumber::digits($formattedRemaining) }}@else ارسال مجدد کد @endif</span>
                            </button>
                        </form>
                    </div>
                    <form class="otp-change-mobile" method="POST" action="{{ route('login.otp.changeMobile') }}">
                        @csrf
                        <button type="submit">تغییر شماره موبایل</button>
                    </form>
                @else
                    <form class="auth-form" method="POST" action="{{ route('login.otp.request') }}">
                        @csrf
                        <label class="auth-field" for="mobile">شماره موبایل
                            <span class="auth-input-wrap"><input id="mobile" name="mobile" value="{{ old('mobile') }}" inputmode="tel" autocomplete="tel" required autofocus></span>
                        </label>
                        <button class="auth-submit" type="submit">ارسال کد تأیید</button>
                    </form>
                @endif
            @else
                <form class="auth-form" method="POST" action="{{ route('login') }}">
                    @csrf
                    <label class="auth-field" for="email">ایمیل
                        <span class="auth-input-wrap">
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                        </span>
                    </label>
                    <label class="auth-field" for="password">رمز عبور
                        <span class="auth-input-wrap password-wrap">
                            <input id="password" name="password" type="password" required autocomplete="current-password">
                            <button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="نمایش رمز عبور"><svg aria-hidden="true"><use href="#i-eye"></use></svg></button>
                        </span>
                    </label>
                    <div class="auth-row">
                        <label class="auth-check"><input type="checkbox" name="remember"> مرا به خاطر بسپار</label>
                        <a class="auth-link" href="{{ route('password.request') }}">رمز عبور را فراموش کرده‌اید؟</a>
                    </div>
                    <button class="auth-submit" type="submit">ورود</button>
                </form>
            @endif
            <p class="auth-switch">حساب ندارید؟ <a href="{{ route('register') }}">ثبت‌نام کنید</a></p>
        </section>
    </div>
@endsection
