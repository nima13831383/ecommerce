<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="لوکسیر، فروشگاه آنلاین عطر، لوازم آرایشی و اکسسوری">
    <title>{{ $title ?? 'لوکسیر | فروشگاه زیبایی' }}</title>
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/generated/tailwind.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/base/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/base/reset.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/base/typography.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/base/utilities.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/components/buttons.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/components/image-placeholder.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/components/product-card.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/components/slider.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/homepage/header.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/homepage/hero.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/homepage/categories.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/homepage/special-products.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/homepage/features.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/homepage/banners.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/homepage/brands.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/homepage/newsletter.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/homepage/footer.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/homepage/responsive.css') }}">
    @stack('head')
</head>
<body class="@yield('bodyClass')">
    <a class="sr-only" href="#main-content">پرش به محتوای اصلی</a>

    @include('storefront.partials.header')

    <main id="main-content">
        @yield('content')
    </main>

    @include('storefront.partials.footer')
    @include('storefront.partials.icon-sprite')

    <script src="{{ asset('storefront/assets/vendor/jquery/jquery.min.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/homepage/mobile-menu.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/homepage/cart-dropdown.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/homepage/sticky-header.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/homepage/hero-slider.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/homepage/product-slider.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/homepage/newsletter.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/core/main.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
