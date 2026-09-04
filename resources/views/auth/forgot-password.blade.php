@extends('storefront.layouts.auth')

@section('content')
    <div class="auth-container auth-shell">
        <section class="auth-card" aria-labelledby="forgot-title">
            <h1 id="forgot-title">بازیابی رمز عبور</h1>
            <p class="auth-card__support">ایمیل خود را وارد کنید تا لینک بازیابی ارسال شود.</p>
            @if (session('status')) <p class="auth-message" role="status">{{ session('status') }}</p> @endif
            @if ($errors->any()) <div class="auth-message" role="alert">{{ $errors->first() }}</div> @endif
            <form class="auth-form" method="POST" action="{{ route('password.email') }}">
                @csrf
                <label class="auth-field" for="email">ایمیل
                    <span class="auth-input-wrap"><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"></span>
                </label>
                <button class="auth-submit" type="submit">ارسال لینک بازیابی</button>
            </form>
            <p class="auth-switch"><a href="{{ route('login') }}">بازگشت به ورود</a></p>
        </section>
    </div>
@endsection
