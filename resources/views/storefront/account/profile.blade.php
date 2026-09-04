@extends('storefront.layouts.account')

@section('account-content')
    @include('storefront.partials.account-sidebar')
    <section class="account-content">
        <div class="account-heading"><div><h1>اطلاعات حساب</h1><p>اطلاعات حساب خود را به‌روزرسانی کنید.</p></div></div>
        @if (session('status')) <p class="form-feedback" role="status">{{ session('status') }}</p> @endif
        @if ($errors->any()) <div class="form-error" role="alert">{{ $errors->first() }}</div> @endif
        <section class="account-card">
            <form class="account-form" method="POST" action="{{ route('storefront.account.profile.update') }}">
                @csrf @method('PATCH')
                <div class="account-form__grid">
                    <label><span>نام</span><input name="name" value="{{ old('name', $user->name) }}" required></label>
                    <label><span>ایمیل</span><input name="email" type="email" value="{{ old('email', $user->email) }}" required></label>
                </div>
                <button class="account-button account-button--pink" type="submit">ذخیره تغییرات</button>
            </form>
        </section>
        <section class="account-card">
            <h2>تغییر رمز عبور</h2>
            <form class="account-form" method="POST" action="{{ route('password.update') }}">
                @csrf @method('PUT')
                <div class="account-form__grid">
                    <label><span>رمز عبور فعلی</span><input type="password" name="current_password" required></label>
                    <label><span>رمز عبور جدید</span><input type="password" name="password" required></label>
                    <label><span>تکرار رمز عبور جدید</span><input type="password" name="password_confirmation" required></label>
                </div>
                <button class="account-button account-button--light" type="submit">تغییر رمز عبور</button>
            </form>
        </section>
    </section>
@endsection
