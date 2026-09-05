@extends('storefront.layouts.app')

@section('bodyClass', 'auth-body')

@section('withoutFooter', 'true')

@section('content')
    <section class="auth-page">
        @yield('auth-content')
    </section>
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
