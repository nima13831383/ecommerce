@extends('storefront.layouts.auth')

@section('auth-content')
    <div class="auth-container auth-shell">
        <section class="auth-card" aria-labelledby="login-title">
            <div class="auth-card__icon" aria-hidden="true">🔒</div>
            <h1 id="login-title">ورود به حساب کاربری</h1>
            <p class="auth-card__support">برای ادامه خرید وارد حساب کاربری خود شوید.</p>

            @if (session('status'))
                <p class="auth-message" role="status">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <div class="auth-message" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

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
            <p class="auth-switch">حساب ندارید؟ <a href="{{ route('register') }}">ثبت‌نام کنید</a></p>
        </section>
    </div>
@endsection
