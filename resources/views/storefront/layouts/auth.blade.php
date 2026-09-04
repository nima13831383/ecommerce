@extends('storefront.layouts.app')

@section('bodyClass', 'auth-body')

@section('content')
    @parent
    <section class="site-container auth-trust" aria-label="مزایای خرید از لوکسیر"><div class="auth-trust__item"><strong>تضمین اصالت کالا</strong><span>خرید مطمئن</span></div><div class="auth-trust__item"><strong>ارسال سریع</strong><span>تحویل به‌موقع</span></div><div class="auth-trust__item"><strong>پرداخت امن</strong><span>با کارت‌های شتاب</span></div><div class="auth-trust__item"><strong>پشتیبانی همیشگی</strong><span>همراه شما</span></div></section>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/auth/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/auth/forms.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/auth/auth-card.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/auth/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/auth/parity.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('storefront/assets/js/auth/password-toggle.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/auth/auth-validation.js') }}" defer></script>
@endpush
