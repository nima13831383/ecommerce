@extends('storefront.layouts.app')

@section('bodyClass', 'payment-result-page-body')

@section('content')
    <div class="payment-result-page" data-payment-result>
        <div class="site-container">
            <nav class="result-breadcrumb" aria-label="مسیر صفحه">
                <a href="{{ route('storefront.home') }}">خانه</a><span>/</span>
                <a href="{{ route('storefront.cart.show') }}">سبد خرید</a><span>/</span>
                <span aria-current="page">نتیجه پرداخت</span>
            </nav>
            <div class="result-heading"><h1>نتیجه پرداخت</h1><p>وضعیت سفارش و پرداخت شما</p></div>

            @php
                $heading = match ($state) {
                    'success' => 'پرداخت با موفقیت تأیید شد',
                    'failed' => 'پرداخت ناموفق بود',
                    'review' => 'وضعیت پرداخت در حال بررسی است',
                    default => 'وضعیت پرداخت در انتظار بررسی است',
                };
                $message = match ($state) {
                    'success' => 'رزرو موجودی سفارش شما ثبت نهایی شد.',
                    'failed' => 'پرداخت تکمیل نشد؛ می‌توانید دوباره تلاش کنید.',
                    'review' => 'نتیجه پرداخت نیازمند بررسی است و هنوز نهایی نشده است.',
                    default => 'نتیجه نهایی پرداخت هنوز دریافت نشده است.',
                };
            @endphp

            <section class="status-card" aria-labelledby="payment-status-title">
                <h2 id="payment-status-title">{{ $heading }}</h2>
                <p>{{ $message }}</p>
                @if ($error)<p class="storefront-alert storefront-alert--error" role="alert">{{ $error }}</p>@endif
                <dl class="result-card">
                    <div><dt>شماره سفارش</dt><dd>{{ $payment['order_number'] }}</dd></div>
                    <div><dt>وضعیت پرداخت</dt><dd>{{ $payment['status'] }}</dd></div>
                    <div><dt>مبلغ</dt><dd>{{ \App\Support\PersianNumber::money($payment['amount']) }}</dd></div>
                </dl>
                <div class="status-actions">
                    @if ($state === 'failed')
                        <a class="result-button result-button--primary" href="{{ route('storefront.checkout.success', ['order' => $payment['order_id']]) }}">تلاش مجدد</a>
                    @endif
                    <a class="result-button result-button--secondary" href="{{ route('storefront.products.index') }}">ادامه خرید</a>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/payment-result/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/payment-result/status.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/payment-result/order-summary.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/payment-result/responsive.css') }}">
@endpush
