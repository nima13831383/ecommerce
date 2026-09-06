@extends('storefront.layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/account/shell.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/account/components.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/account/responsive.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('storefront/assets/js/account/account-nav.js') }}" defer></script>
@endpush

@section('content')
    <div class="account-page">
        <div class="site-container">
            <nav class="account-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('storefront.home') }}">خانه</a><span>/</span><a href="{{ route('storefront.account') }}">حساب کاربری</a><span>/</span><span aria-current="page">حساب کاربری من</span></nav>
            <div class="account-mobile-nav" data-account-mobile-nav>
                <button class="account-mobile-nav__toggle" type="button" data-account-nav-toggle aria-expanded="false" aria-controls="account-mobile-menu">حساب کاربری · پیشخوان<svg class="icon"><use href="#i-menu"></use></svg></button>
                <div id="account-mobile-menu" class="account-mobile-nav__menu" data-account-nav-menu>
                    <a class="{{ request()->routeIs('storefront.account') ? 'is-active' : '' }}" href="{{ route('storefront.account') }}">پیشخوان</a>
                    <a class="{{ request()->routeIs('storefront.account.profile') ? 'is-active' : '' }}" href="{{ route('storefront.account.profile') }}">اطلاعات حساب</a>
                    <a class="{{ request()->routeIs('storefront.account.orders*') ? 'is-active' : '' }}" href="{{ route('storefront.account.orders') }}">سفارش‌های من</a>
                    <a class="{{ request()->routeIs('storefront.account.addresses*') ? 'is-active' : '' }}" href="{{ route('storefront.account.addresses') }}">آدرس‌های من</a>
                </div>
            </div>
            <div class="account-shell">
                @yield('account-content')
            </div>
        </div>
    </div>
@endsection
