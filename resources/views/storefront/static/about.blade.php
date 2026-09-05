@extends('storefront.layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/static/pages.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/static/responsive.css') }}">
@endpush

@section('content')
    <div class="public-page"><div class="site-container">
        <div class="public-breadcrumb"><a href="{{ route('storefront.home') }}">خانه</a><span>/</span><span>درباره ما</span></div>
        <section class="public-card static-hero"><div><h1>درباره ما</h1><p>لوکسیر یک فروشگاه آنلاین برای انتخاب ساده‌تر عطر، لوازم آرایشی و اکسسوری است؛ با تمرکز بر تجربه‌ای روشن و دلنشین برای مشتری.</p><div class="static-cta"><a class="public-button" href="{{ route('storefront.products.index') }}">مشاهده محصولات</a><a class="public-button" href="{{ route('storefront.contact') }}">تماس با ما</a></div></div><div class="static-placeholder">جای تصویر برند</div></section>
        <div class="story-grid"><section class="public-card"><h2>داستان ما</h2><p>ما این فروشگاه را با علاقه به رایحه‌ها و جزئیات زیبایی آغاز کردیم تا پیدا کردن محصول مناسب، از جستجو تا انتخاب، ساده و قابل اعتماد باشد.</p><p>این صفحه نمونه‌ای استاتیک از معرفی برند است و اطلاعات آن برای نمایش رابط کاربری نوشته شده است.</p></section><section class="public-card"><h2>تجربه خرید بهتر</h2><p>دسته‌بندی روشن، توضیحات قابل فهم و مسیر خرید کوتاه، پایه‌های تجربه‌ای هستند که هر روز بهترش می‌کنیم.</p></section></div>
        <section class="public-card" style="margin-top: 18px"><h2>چرا لوکسیر؟</h2><div class="value-grid"><article class="value-card"><h2>اصالت کالا</h2><p>توجه به انتخاب و معرفی مسئولانه محصولات.</p></article><article class="value-card"><h2>خرید ساده</h2><p>مسیر شفاف برای انتخاب و ثبت سفارش.</p></article><article class="value-card"><h2>ارسال سریع</h2><p>هماهنگی مناسب برای رسیدن سفارش.</p></article><article class="value-card"><h2>پشتیبانی</h2><p>پاسخ‌گویی دوستانه به سوالات مشتریان.</p></article></div></section>
    </div></div>
@endsection
