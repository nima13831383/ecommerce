@extends('storefront.layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/static/pages.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/static/responsive.css') }}">
@endpush

@section('content')
    <div class="public-page"><div class="site-container">
        <div class="public-breadcrumb"><a href="{{ route('storefront.home') }}">خانه</a><span>/</span><span>سوالات متداول</span></div>
        <div class="public-heading"><div><h1>سوالات متداول</h1><p>پاسخ کوتاه به پرسش‌های رایج درباره خرید از لوکسیر.</p></div></div>
        <section class="public-card"><div class="faq-toolbar"><div class="faq-categories"><button class="faq-category is-active" data-faq-category="all" type="button">همه</button><button class="faq-category" data-faq-category="order" type="button">سفارش و خرید</button><button class="faq-category" data-faq-category="shipping" type="button">ارسال</button><button class="faq-category" data-faq-category="account" type="button">حساب کاربری</button></div><label class="faq-search"><span class="sr-only">جستجو در سوالات متداول</span><input type="search" data-faq-search placeholder="جستجو در سوالات متداول"></label></div><div class="faq-list"><article class="faq-item" data-faq-item data-faq-category="order"><button class="faq-trigger" data-faq-trigger aria-expanded="false" aria-controls="faq-a1">چگونه سفارش خود را پیگیری کنم؟</button><div id="faq-a1" class="faq-answer" data-faq-answer>از بخش سفارش‌های من می‌توانید وضعیت سفارش نمونه را مشاهده کنید.</div></article><article class="faq-item" data-faq-item data-faq-category="shipping"><button class="faq-trigger" data-faq-trigger aria-expanded="false" aria-controls="faq-a2">هزینه ارسال چگونه محاسبه می‌شود؟</button><div id="faq-a2" class="faq-answer" data-faq-answer>هزینه ارسال بر اساس روش انتخابی در مرحله تسویه نمایش داده می‌شود.</div></article><article class="faq-item" data-faq-item data-faq-category="order"><button class="faq-trigger" data-faq-trigger aria-expanded="false" aria-controls="faq-a3">چه روش‌های پرداختی وجود دارد؟</button><div id="faq-a3" class="faq-answer" data-faq-answer>در این قالب، پرداخت آنلاین و پرداخت در محل به صورت نمایشی نمایش داده شده‌اند.</div></article><article class="faq-item" data-faq-item data-faq-category="account"><button class="faq-trigger" data-faq-trigger aria-expanded="false" aria-controls="faq-a4">چگونه آدرس خود را تغییر دهم؟</button><div id="faq-a4" class="faq-answer" data-faq-answer>از بخش آدرس‌های من، گزینه ویرایش را انتخاب کنید.</div></article></div></section>
        <div class="static-cta"><a class="public-button" href="{{ route('storefront.contact') }}">تماس با ما</a></div>
    </div></div>
@endsection

@push('scripts')
    <script src="{{ asset('storefront/assets/js/static/faq.js') }}" defer></script>
@endpush
