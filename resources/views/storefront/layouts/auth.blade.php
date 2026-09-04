@extends('storefront.layouts.app')

@section('bodyClass', 'auth-body')

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/auth/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/auth/forms.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/auth/auth-card.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/auth/responsive.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('storefront/assets/js/auth/password-toggle.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/auth/auth-validation.js') }}" defer></script>
@endpush
