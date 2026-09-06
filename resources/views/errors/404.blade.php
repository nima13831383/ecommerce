@extends('storefront.layouts.app')

@push('head')
    @foreach ([
        'components/public-page.css', 'product/layout.css', 'product/gallery.css',
        'product/product-info.css', 'product/details.css', 'product/reviews.css',
        'product/responsive.css', 'static/pages.css', 'static/responsive.css', 'static/not-found.css',
    ] as $stylesheet)
        <link rel="stylesheet" href="{{ asset('storefront/assets/css/'.$stylesheet) }}">
    @endforeach
@endpush

@section('content')
    <div class="public-page not-found-page">
        <div class="site-container">
            <section class="not-found-hero" aria-labelledby="not-found-title">
                <div class="not-found-hero__content">
                    <p class="not-found-hero__number" aria-label="۴۰۴">۴۰۴</p>
                    <h1 id="not-found-title">صفحه مورد نظر پیدا نشد</h1>
                    <p>ممکن است آدرس اشتباه وارد شده باشد<br>یا صفحه حذف شده باشد.</p>
                    <div class="not-found-hero__actions">
                        <a class="public-button" href="{{ route('storefront.home') }}">بازگشت به خانه <svg class="icon" aria-hidden="true"><use href="#i-home"></use></svg></a>
                        <a class="public-button public-button--secondary" href="{{ route('storefront.products.index') }}">مشاهده محصولات <svg class="icon" aria-hidden="true"><use href="#i-bag"></use></svg></a>
                    </div>
                </div>
            </section>

            <section class="recovery-card" aria-labelledby="recovery-title">
                <div class="recovery-card__copy"><h2 id="recovery-title">به دنبال چیزی خاص هستید؟</h2><p>نام محصول یا برند مورد نظر خود را جستجو کنید.</p></div>
                <form class="recovery-form" action="{{ route('storefront.products.index') }}" method="GET" data-recovery-search role="search">
                    <label class="sr-only" for="not-found-search">جستجوی محصول، برند یا دسته</label>
                    <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
                    <input id="not-found-search" name="search" type="search" placeholder="جستجوی محصول، برند یا دسته..." autocomplete="off">
                    <button type="submit">جستجو</button>
                </form>
            </section>

            <section class="not-found-section products-wrap" aria-labelledby="suggested-products-title">
                <div class="section-top"><h2 id="suggested-products-title" class="section-heading">شاید این محصولات برای شما مناسب باشند</h2><div class="product-nav"><button class="icon-button arrow-button" data-product-action="prev" aria-label="محصولات قبلی"><svg class="icon"><use href="#i-chevron-right"></use></svg></button><button class="icon-button arrow-button" data-product-action="next" aria-label="محصولات بعدی"><svg class="icon"><use href="#i-chevron-left"></use></svg></button></div></div>
                <div class="product-grid" data-product-grid>
                    @foreach ([
                        ['name' => 'عطر زنانه بلوم شنیل', 'old' => '۳,۲۰۰,۰۰۰ تومان', 'price' => '۲,۴۵۰,۰۰۰ تومان', 'discount' => '۲۰٪'],
                        ['name' => 'ست رژ لب مات ۴ عددی', 'old' => '۲,۳۰۰,۰۰۰ تومان', 'price' => '۱,۹۵۵,۰۰۰ تومان', 'discount' => '۱۵٪'],
                        ['name' => 'سرم نیاسینامید اوردینری', 'old' => '۱,۲۵۰,۰۰۰ تومان', 'price' => '۱,۰۰۰,۰۰۰ تومان', 'discount' => '۲۰٪'],
                        ['name' => 'ماسک لب شب لانیژ', 'old' => '۹۸۰,۰۰۰ تومان', 'price' => '۸۳۳,۰۰۰ تومان', 'discount' => '۱۵٪'],
                        ['name' => 'عطر زنانه مای وی جورجیو آرمانی', 'old' => '۶,۹۰۰,۰۰۰ تومان', 'price' => '۵,۵۲۰,۰۰۰ تومان', 'discount' => '۲۰٪'],
                        ['name' => 'کرم مرطوب کننده سوپر هیدراته', 'old' => '۸۵۰,۰۰۰ تومان', 'price' => '۶۸۰,۰۰۰ تومان', 'discount' => '۲۰٪'],
                        ['name' => 'دستبند چرم و استیل با طراحی خاص', 'old' => '۴۵۰,۰۰۰ تومان', 'price' => '۴۰۵,۰۰۰ تومان', 'discount' => '۱۰٪'],
                    ] as $suggestion)
                        <article class="product-card" data-product-card><div class="product-card__media media-placeholder"><button class="favorite" type="button" aria-label="افزودن {{ $suggestion['name'] }} به علاقه‌مندی‌ها"><svg class="icon"><use href="#i-heart"></use></svg></button><span class="discount">{{ $suggestion['discount'] }}</span></div><div class="product-card__body"><h3 class="product-card__title">{{ $suggestion['name'] }}</h3><span class="product-card__old">{{ $suggestion['old'] }}</span><div class="product-card__price">{{ $suggestion['price'] }}</div></div></article>
                    @endforeach
                </div>
            </section>

            <section class="features not-found-trust" aria-label="مزایای خرید از لوکسیر">
                <div class="feature"><svg class="icon" aria-hidden="true"><use href="#i-truck"></use></svg><div><strong>ارسال رایگان</strong><span>برای سفارش‌های بالای ۱ میلیون تومان</span></div></div>
                <div class="feature"><svg class="icon" aria-hidden="true"><use href="#i-medal"></use></svg><div><strong>ضمانت اصالت کالا</strong><span>تضمین ۱۰۰٪ اصل بودن محصولات</span></div></div>
                <div class="feature"><svg class="icon" aria-hidden="true"><use href="#i-wallet"></use></svg><div><strong>پرداخت امن</strong><span>پرداخت آنلاین با کارت‌های شتاب</span></div></div>
                <div class="feature"><svg class="icon" aria-hidden="true"><use href="#i-headset"></use></svg><div><strong>پشتیبانی ۴۲۷۷</strong><span>همیشه همراه شما هستیم</span></div></div>
            </section>

            @include('storefront.partials.newsletter')
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('storefront/assets/js/static/not-found.js') }}" defer></script>
@endpush
