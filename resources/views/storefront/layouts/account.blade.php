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
            <div class="account-shell">
                @yield('account-content')
            </div>
        </div>
    </div>
@endsection
