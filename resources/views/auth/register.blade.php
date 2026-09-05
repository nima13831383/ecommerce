@extends('storefront.layouts.auth')

@section('auth-content')
    <div class="auth-container auth-shell">
        <section class="auth-card" aria-labelledby="register-title">
            <div class="auth-card__icon" aria-hidden="true">✦</div>
            <h1 id="register-title">ایجاد حساب کاربری</h1>
            <p class="auth-card__support">برای تجربه بهتر خرید ثبت‌نام کنید.</p>
            @if (session('status') === 'otp-sent') <p class="auth-message" role="status">کد تأیید ارسال شد.</p> @endif
            @if ($errors->any()) <div class="auth-message" role="alert">{{ $errors->first() }}</div> @endif
            @if (($customerAuthMode ?? 'email_password') === 'sms_otp')
                @php($otpProfile = session('auth.otp.register_profile'))
                @if (is_array($otpProfile))
                    <form class="auth-form" method="POST" action="{{ route('register.otp.verify') }}">
                        @csrf
                        <input type="hidden" name="mobile" value="{{ $otpProfile['mobile'] }}">
                        <label class="auth-field" for="code">کد تأیید
                            <span class="auth-input-wrap"><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus></span>
                        </label>
                        @if (app()->environment(['local', 'testing']) && app(\App\Services\Settings\SettingsService::class)->get('sms.smsir.sandbox') === true)
                            <p class="auth-message" role="status">کد تست Sandbox: ۱۲۳۴۵</p>
                        @endif
                        <button class="auth-submit" type="submit">تأیید و ایجاد حساب</button>
                    </form>
                    <form class="auth-form" method="POST" action="{{ route('register.otp.resend') }}">
                        @csrf
                        <input type="hidden" name="name" value="{{ $otpProfile['name'] }}">
                        <input type="hidden" name="mobile" value="{{ $otpProfile['mobile'] }}">
                        <button class="auth-link" type="submit">ارسال مجدد کد</button>
                    </form>
                @else
                    <form class="auth-form" method="POST" action="{{ route('register.otp.request') }}">
                        @csrf
                        <label class="auth-field" for="name">نام
                            <span class="auth-input-wrap"><input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"></span>
                        </label>
                        <label class="auth-field" for="mobile">شماره موبایل
                            <span class="auth-input-wrap"><input id="mobile" name="mobile" value="{{ old('mobile') }}" inputmode="tel" autocomplete="tel" required></span>
                        </label>
                        <button class="auth-submit" type="submit">ارسال کد تأیید</button>
                    </form>
                @endif
            @else
            <form class="auth-form" method="POST" action="{{ route('register') }}">
                @csrf
                <label class="auth-field" for="name">نام
                    <span class="auth-input-wrap"><input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"></span>
                </label>
                <label class="auth-field" for="email">ایمیل
                    <span class="auth-input-wrap"><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username"></span>
                </label>
                <label class="auth-field" for="password">رمز عبور
                    <span class="auth-input-wrap password-wrap"><input id="password" name="password" type="password" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="نمایش رمز عبور"><svg aria-hidden="true"><use href="#i-eye"></use></svg></button></span>
                </label>
                <label class="auth-field" for="password_confirmation">تکرار رمز عبور
                    <span class="auth-input-wrap password-wrap"><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-controls="password_confirmation" aria-label="نمایش تکرار رمز عبور"><svg aria-hidden="true"><use href="#i-eye"></use></svg></button></span>
                </label>
                <button class="auth-submit" type="submit">ثبت‌نام</button>
            </form>
            @endif
            <p class="auth-switch">قبلاً ثبت‌نام کرده‌اید؟ <a href="{{ route('login') }}">ورود</a></p>
        </section>
    </div>
@endsection
