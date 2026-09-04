@extends('storefront.layouts.auth')

@section('content')
    <div class="auth-container auth-shell">
        <section class="auth-card" aria-labelledby="reset-title">
            <h1 id="reset-title">تنظیم رمز عبور جدید</h1>
            @if ($errors->any()) <div class="auth-message" role="alert">{{ $errors->first() }}</div> @endif
            <form class="auth-form" method="POST" action="{{ route('password.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <label class="auth-field" for="email">ایمیل
                    <span class="auth-input-wrap"><input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autocomplete="username"></span>
                </label>
                <label class="auth-field" for="password">رمز عبور جدید
                    <span class="auth-input-wrap"><input id="password" name="password" type="password" required autocomplete="new-password"></span>
                </label>
                <label class="auth-field" for="password_confirmation">تکرار رمز عبور
                    <span class="auth-input-wrap"><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></span>
                </label>
                <button class="auth-submit" type="submit">ذخیره رمز عبور</button>
            </form>
        </section>
    </div>
@endsection
