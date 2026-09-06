@extends('storefront.layouts.app')

@section('content')
    @php
        $heroSlides = [
            ['desktop' => 'storefront/assets/homepage/hero/desktop/1.png', 'mobile' => 'storefront/assets/homepage/hero/mobile/1.png', 'title' => 'عطرهایی برای', 'strong' => 'لحظات فراموش‌نشدنی', 'text' => 'جدیدترین مجموعه عطرهای زنانه و مردانه', 'action' => 'مشاهده و خرید', 'alt' => 'عطر و گل'],
            ['desktop' => 'storefront/assets/homepage/hero/desktop/2.png', 'mobile' => 'storefront/assets/homepage/hero/mobile/2.png', 'title' => 'زیبایی،', 'strong' => 'به سبک خودت', 'text' => 'محصولات منتخب برای روتین روزانه شما', 'action' => 'مشاهده محصولات', 'alt' => 'محصولات زیبایی'],
            ['desktop' => 'storefront/assets/homepage/hero/desktop/3.png', 'mobile' => 'storefront/assets/homepage/hero/mobile/3.png', 'title' => 'اکسسوری‌های', 'strong' => 'خاص و ماندگار', 'text' => 'جزئیات کوچک، تفاوت‌های بزرگ می‌سازند', 'action' => 'کشف مجموعه', 'alt' => 'اکسسوری‌های خاص'],
        ];
    @endphp
    <section class="hero site-container" aria-labelledby="hero-title"><div class="hero-shell" data-component="hero-slider" tabindex="0"><div class="slider-viewport"><div class="slider-track hero-track">
        @foreach ($heroSlides as $index => $slide)
            <article class="slider-slide hero-slide hero-slide--{{ $index + 1 }}" data-slide-index="{{ $index }}"><picture class="hero-media"><source media="(max-width: 600px)" srcset="{{ asset($slide['mobile']) }}"><img src="{{ asset($slide['desktop']) }}" alt="{{ $slide['alt'] }}" @if ($index === 0) fetchpriority="high" @endif></picture><div class="hero-copy"><h1 @if ($index === 0) id="hero-title" @endif>{{ $slide['title'] }}<br><strong>{{ $slide['strong'] }}</strong></h1><p>{{ $slide['text'] }}</p><button class="pill-button" type="button">{{ $slide['action'] }}</button></div></article>
        @endforeach
    </div></div><div class="hero-controls"><button class="icon-button arrow-button slider-control prev" data-action="prev" aria-label="اسلاید قبلی"><svg class="icon"><use href="#i-chevron-right"></use></svg></button><button class="icon-button arrow-button slider-control next" data-action="next" aria-label="اسلاید بعدی"><svg class="icon"><use href="#i-chevron-left"></use></svg></button><div class="slider-dots hero-dots" role="tablist" aria-label="انتخاب اسلاید">@foreach ($heroSlides as $index => $slide)<button class="slider-dot {{ $index === 0 ? 'is-active' : '' }}" data-slide="{{ $index }}" role="tab" aria-label="اسلاید {{ $index + 1 }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}"></button>@endforeach</div></div></div></section>

    <section id="categories" class="section site-container" aria-labelledby="categories-title"><div class="section-top"><h2 id="categories-title" class="section-heading">دسته‌بندی محصولات</h2><a class="section-link" href="{{ route('storefront.products.index') }}">مشاهده همه ←</a></div><div class="category-row">@forelse ($categories as $category)<a class="category-item" href="{{ route('storefront.products.index', ['categories' => [$category->slug]]) }}"><span class="category-icon"><svg class="icon"><use href="#i-gift"></use></svg></span>{{ $category->name }}</a>@empty<p class="storefront-empty">دسته‌بندی فعالی برای نمایش وجود ندارد.</p>@endforelse</div></section>

    @if (! empty($featuredProducts))
        <section id="special-products" class="section site-container products-wrap" aria-labelledby="products-title">
            <div class="section-top"><h2 id="products-title" class="section-heading">پیشنهاد ویژه ✦</h2><div class="product-nav"><button class="icon-button arrow-button" data-product-action="prev" aria-label="محصولات قبلی"><svg class="icon"><use href="#i-chevron-right"></use></svg></button><button class="icon-button arrow-button" data-product-action="next" aria-label="محصولات بعدی"><svg class="icon"><use href="#i-chevron-left"></use></svg></button></div></div>
            <div class="product-grid" data-product-grid>
                @foreach ($featuredProducts as $product)
                    @include('storefront.components.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @else
        <section id="special-products" class="section site-container products-wrap" aria-labelledby="products-title">
            <div class="section-top"><h2 id="products-title" class="section-heading">پیشنهاد ویژه ✦</h2></div>
            <p class="storefront-empty">در حال حاضر محصول ویژه‌ای برای نمایش وجود ندارد.</p>
        </section>
    @endif

    @if (false)
    <section id="legacy-static-special-products" class="section site-container products-wrap" aria-labelledby="products-title"><div class="section-top"><h2 id="products-title" class="section-heading">پیشنهاد ویژه ✦</h2><div class="product-nav"><button class="icon-button arrow-button" data-product-action="prev" aria-label="محصولات قبلی"><svg class="icon"><use href="#i-chevron-right"></use></svg></button><button class="icon-button arrow-button" data-product-action="next" aria-label="محصولات بعدی"><svg class="icon"><use href="#i-chevron-left"></use></svg></button></div></div><div class="product-grid" data-product-grid>
        <article class="product-card" data-product-card><div class="product-card__media media-placeholder"><button class="favorite" aria-label="افزودن عطر شنل به علاقه‌مندی‌ها"><svg class="icon"><use href="#i-heart"></use></svg></button><span class="discount">۱۸٪</span></div><div class="product-card__body"><h3 class="product-card__title">عطر زنانه بلو شنل</h3><span class="product-card__old">۳,۲۰۰,۰۰۰ تومان</span><div class="product-card__price">۲,۶۲۴,۰۰۰ تومان</div></div></article>
        <article class="product-card" data-product-card><div class="product-card__media media-placeholder"><button class="favorite" aria-label="افزودن کرم مرطوب کننده به علاقه‌مندی‌ها"><svg class="icon"><use href="#i-heart"></use></svg></button><span class="discount">۲۰٪</span></div><div class="product-card__body"><h3 class="product-card__title">کرم مرطوب کننده سوپر هیدراته</h3><span class="product-card__old">۸۵۰,۰۰۰ تومان</span><div class="product-card__price">۶۸۰,۰۰۰ تومان</div></div></article>
        <article class="product-card" data-product-card><div class="product-card__media media-placeholder"><button class="favorite" aria-label="افزودن دستبند چرم به علاقه‌مندی‌ها"><svg class="icon"><use href="#i-heart"></use></svg></button><span class="discount">۱۰٪</span></div><div class="product-card__body"><h3 class="product-card__title">دستبند چرم و استیل با طراحی خاص</h3><span class="product-card__old">۴۵۰,۰۰۰ تومان</span><div class="product-card__price">۴۰۵,۰۰۰ تومان</div></div></article>
        <article class="product-card" data-product-card><div class="product-card__media media-placeholder"><button class="favorite" aria-label="افزودن تینت لب به علاقه‌مندی‌ها"><svg class="icon"><use href="#i-heart"></use></svg></button><span class="discount">۱۵٪</span></div><div class="product-card__body"><h3 class="product-card__title">ست سه عددی تینت لب با رنگ‌بندی جذاب</h3><span class="product-card__old">۱,۰۵۰,۰۰۰ تومان</span><div class="product-card__price">۸۹۲,۵۰۰ تومان</div></div></article>
        <article class="product-card" data-product-card><div class="product-card__media media-placeholder"><button class="favorite" aria-label="افزودن عطر کوکو مادمازل به علاقه‌مندی‌ها"><svg class="icon"><use href="#i-heart"></use></svg></button><span class="discount">۲۱٪</span></div><div class="product-card__body"><h3 class="product-card__title">عطر زنانه کوکو مادمازل؛ انتخابی برای روزهای خاص</h3><span class="product-card__old">۳,۸۰۰,۰۰۰ تومان</span><div class="product-card__price">۳,۰۰۲,۰۰۰ تومان</div></div></article>
        <article class="product-card" data-product-card><div class="product-card__media media-placeholder"><button class="favorite" aria-label="افزودن سرم آبرسان به علاقه‌مندی‌ها"><svg class="icon"><use href="#i-heart"></use></svg></button><span class="discount">۱۲٪</span></div><div class="product-card__body"><h3 class="product-card__title">سرم آبرسان ویتامین سی برای درخشش پوست</h3><span class="product-card__old">۹۵۰,۰۰۰ تومان</span><div class="product-card__price">۸۳۶,۰۰۰ تومان</div></div></article>
        <article class="product-card" data-product-card><div class="product-card__media media-placeholder"><button class="favorite" aria-label="افزودن رژ لب مات به علاقه‌مندی‌ها"><svg class="icon"><use href="#i-heart"></use></svg></button><span class="discount">۱۴٪</span></div><div class="product-card__body"><h3 class="product-card__title">رژ لب مات با ماندگاری بالا</h3><span class="product-card__old">۵۲۰,۰۰۰ تومان</span><div class="product-card__price">۴۴۷,۲۰۰ تومان</div></div></article>
    </div></section>

    </section>
    @endif

    <section class="site-container features" aria-label="مزایای خرید از لوکسیر"><div class="feature"><svg class="icon"><use href="#i-truck"></use></svg><div><strong>ارسال سریع</strong><span>برای سفارش‌های بالای ۱ میلیون تومان</span></div></div><div class="feature"><svg class="icon"><use href="#i-medal"></use></svg><div><strong>ضمانت اصالت کالا</strong><span>تضمین ۱۰۰٪ اصل بودن محصولات</span></div></div><div class="feature"><svg class="icon"><use href="#i-wallet"></use></svg><div><strong>پرداخت امن</strong><span>پرداخت آنلاین با کارت‌های شتاب</span></div></div><div class="feature"><svg class="icon"><use href="#i-headset"></use></svg><div><strong>پشتیبانی ۴۲۷۷</strong><span>همیشه همراه شما هستیم</span></div></div></section>

    <section class="section site-container banner-grid" aria-label="پیشنهادهای ویژه"><article class="promo-banner"><div class="promo-copy"><strong>جدیدترین اکسسوری‌ها</strong><p>برای استایل خاص شما</p><button class="pill-button" type="button">مشاهده محصولات</button></div><img class="promo-banner__media" src="{{ asset('storefront/assets/homepage/promos/accessories.png') }}" alt="کیف و اکسسوری‌های جدید"></article><article class="promo-banner"><div class="promo-copy"><strong>مراقبت از پوست</strong><p>زیبایی از درون</p><button class="pill-button" type="button">مشاهده محصولات</button></div><img class="promo-banner__media" src="{{ asset('storefront/assets/homepage/promos/skincare.png') }}" alt="محصولات مراقبت از پوست"></article></section>

    <section id="brands" class="section site-container brands" aria-labelledby="brands-title"><div class="section-top"><h2 id="brands-title" class="section-heading">برندهای محبوب ♡</h2><a class="section-link" href="#">مشاهده همه ←</a></div><div class="brand-list" data-brand-slider><div class="brand-card">CHANEL</div><div class="brand-card">Dior</div><div class="brand-card">LANCÔME<small>PARIS</small></div><div class="brand-card">ESTÉE LAUDER</div><div class="brand-card">NYX<small>PROFESSIONAL MAKEUP</small></div><div class="brand-card">The<br>Ordinary.</div></div></section>

    @include('storefront.partials.newsletter')
@endsection
