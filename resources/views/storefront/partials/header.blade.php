@php
    $storefrontCart ??= [
        'lines' => [],
        'item_count' => 0,
        'grand_total' => 0,
    ];
@endphp

<header>
    <div class="announcement header-row header-row--announcement"><div class="site-container announcement__inner"><span>ارسال رایگان برای سفارش‌های بالای ۳,۰۰۰,۰۰۰ تومان</span><span>پشتیبانی همیشه همراه شماست</span></div></div>
    <div class="desktop-header">
        <div class="main-header"><div class="site-container header-grid">
            <a class="brand-mark" href="{{ route('storefront.home') }}" aria-label="لوکسیر"><img src="{{ asset('storefront/luxira-icon.png') }}" alt="لوکسیر" width="1448" height="1086"></a>
            <form class="search-box" role="search" method="get" action="{{ route('storefront.products.index') }}"><label class="sr-only" for="desktop-search">جستجو در محصولات</label><input id="desktop-search" name="search" type="search" placeholder="جستجو در محصولات..." autocomplete="off"><svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg></form>
            <div class="header-actions">@auth<a class="icon-button" href="{{ route('storefront.account') }}" aria-label="حساب کاربری {{ auth()->user()->name }}"><svg class="icon"><use href="#i-user"></use></svg></a><form method="POST" action="{{ route('logout') }}" class="header-logout">@csrf<button type="submit" aria-label="خروج">خروج</button></form>@else<a class="icon-button" href="{{ route('login') }}" aria-label="ورود"><svg class="icon"><use href="#i-user"></use></svg></a><a class="header-auth-link" href="{{ route('register') }}">ثبت‌نام</a>@endauth<button class="icon-button" aria-label="علاقه‌مندی‌ها"><svg class="icon"><use href="#i-heart"></use></svg></button><a class="icon-button" href="{{ route('storefront.cart.show') }}" aria-label="سبد خرید"><svg class="icon"><use href="#i-bag"></use></svg><span class="header-cart-count" data-cart-count>{{ $storefrontCart['item_count'] }}</span></a></div>
        </div></div>
        <nav class="desktop-nav header-row" aria-label="منوی اصلی"><div class="site-container"><ul><li><a class="active" href="{{ route('storefront.home') }}">خانه</a></li><li><a href="{{ route('storefront.products.index') }}">محصولات</a></li><li><a href="#categories">دسته‌بندی‌ها</a></li><li><a href="#brands">برندها</a></li><li><a href="{{ route('storefront.blog.index') }}">وبلاگ</a></li><li><a href="{{ route('storefront.contact') }}">تماس با ما</a></li></ul></div></nav>
    </div>
    <div class="mobile-header" data-component="mobile-menu"><div class="site-container">
        <div class="mobile-header__row"><button class="icon-button" data-action="menu" aria-label="باز کردن منو" aria-expanded="false" aria-controls="mobile-nav"><svg class="icon"><use href="#i-menu"></use></svg></button><a class="brand-mark" href="{{ route('storefront.home') }}" aria-label="لوکسیر"><img src="{{ asset('storefront/luxira-icon.png') }}" alt="لوکسیر" width="1448" height="1086"></a><a class="icon-button" href="{{ route('storefront.cart.show') }}" aria-label="سبد خرید"><svg class="icon"><use href="#i-bag"></use></svg><span class="header-cart-count" data-cart-count>{{ $storefrontCart['item_count'] }}</span></a></div>
        <div class="mobile-header__search-row header-row"><form class="search-box" method="get" action="{{ route('storefront.products.index') }}"><label class="sr-only" for="mobile-search">جستجو در محصولات</label><input id="mobile-search" name="search" type="search" placeholder="جستجو در محصولات..."><svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg></form></div>
        <nav id="mobile-nav" class="mobile-nav" hidden aria-label="منوی موبایل"><a href="{{ route('storefront.home') }}">خانه</a><a href="{{ route('storefront.products.index') }}">محصولات</a><a href="#categories">دسته‌بندی‌ها</a><a href="#brands">برندها</a><a href="{{ route('storefront.blog.index') }}">وبلاگ</a><a href="{{ route('storefront.contact') }}">تماس با ما</a></nav>
    </div></div>
    <div id="cart-preview" class="cart-preview" data-component="cart-preview" role="dialog" aria-labelledby="cart-preview-title" aria-hidden="true">
        <div class="cart-preview__header"><h2 id="cart-preview-title">سبد خرید</h2><span>{{ $storefrontCart['item_count'] }} کالا</span></div>
        @forelse (array_slice($storefrontCart['lines'], 0, 3) as $line)
            <article class="cart-item">
                <div class="cart-item__media {{ $line['image'] ? '' : 'media-placeholder' }}">
                    @if ($line['image'])<img src="{{ $line['image']['url'] }}" alt="{{ $line['image']['alt'] ?: $line['name'] }}" loading="lazy">@endif
                </div>
                <div><strong class="cart-item__title">{{ $line['name'] }}</strong><span class="cart-item__meta">{{ $line['quantity'] }} × {{ number_format($line['unit_price']) }} ریال</span></div>
                <span class="cart-item__price">{{ number_format($line['line_total']) }}</span>
            </article>
        @empty
            <p class="cart-preview__empty">سبد خرید شما خالی است.</p>
        @endforelse
        <div class="cart-preview__total"><span>جمع کل</span><strong>{{ number_format($storefrontCart['grand_total']) }} ریال</strong></div>
        <div class="cart-preview__actions"><a href="{{ route('storefront.cart.show') }}">مشاهده سبد خرید</a><a href="{{ route('storefront.cart.show') }}">ادامه خرید</a></div>
    </div>
</header>
