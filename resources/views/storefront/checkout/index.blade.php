@extends('storefront.layouts.app')

@section('bodyClass', 'checkout-page-body')

@php
    $cart = $cartView;
    $lines = $cart['lines'];
    $subtotal = $preview?->subtotal ?? $cart['subtotal'];
    $discount = $preview?->discountTotal ?? $cart['discount_total'];
    $tax = $preview?->taxTotal ?? $cart['tax_total'];
    $shipping = $preview?->shippingTotal ?? 0;
    $grandTotal = $preview?->grandTotal ?? ($subtotal - $discount + $tax + $shipping);
@endphp

@section('content')
<div class="checkout-page" data-checkout-page>
    <div class="site-container">
        <nav class="checkout-breadcrumb" aria-label="مسیر صفحه">
            <a href="{{ route('storefront.home') }}">خانه</a><span>/</span>
            <a href="{{ route('storefront.cart.show') }}">سبد خرید</a><span>/</span>
            <span aria-current="page">تسویه حساب</span>
        </nav>
        <div class="checkout-heading"><h1>تسویه حساب</h1><p>اطلاعات سفارش خود را تکمیل کنید</p></div>

        @if ($errors->has('checkout'))
            <p class="storefront-alert storefront-alert--error" role="alert">{{ $errors->first('checkout') }}</p>
        @endif
        @if ($previewError)
            <p class="storefront-alert storefront-alert--error" role="alert">{{ $previewError }}</p>
        @endif
        @if (session('status'))
            <p class="storefront-alert storefront-alert--success" role="status">{{ session('status') }}</p>
        @endif

        <form class="checkout-layout" method="post" action="{{ route('storefront.checkout.store') }}" data-checkout-form>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
            <div class="checkout-flow">
                <section class="checkout-card" aria-labelledby="address-title">
                    <div class="checkout-card__heading"><h2 id="address-title">آدرس تحویل</h2><span>یک آدرس را انتخاب کنید</span></div>
                    @if ($addresses->isEmpty())
                        <p class="storefront-alert storefront-alert--error">برای ثبت سفارش ابتدا یک آدرس در حساب کاربری خود ثبت کنید.</p>
                    @else
                        <fieldset class="choice-list address-choices">
                            <legend class="sr-only">انتخاب آدرس</legend>
                            @foreach ($addresses as $address)
                                <label class="choice-card">
                                    <input type="radio" name="shipping_address_id" value="{{ $address->id }}" required @checked($selectedAddressId === $address->id)>
                                    <span><strong>{{ $address->first_name }} {{ $address->last_name }}</strong><small>{{ $address->address_line }}</small><small>{{ $address->mobile }}</small></span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endif
                    <p><a href="{{ route('storefront.account.addresses') }}">مدیریت آدرس‌ها</a></p>
                </section>

                <section class="checkout-card" aria-labelledby="shipping-title">
                    <div class="checkout-card__heading"><h2 id="shipping-title">روش ارسال</h2><span>انتخاب ارسال مناسب</span></div>
                    <div class="field-grid">
                        <label for="shipping-service">روش ارسال</label>
                        <select id="shipping-service" name="shipping_service" required>
                            @foreach ($shippingServices as $key => $label)
                                <option value="{{ $key }}" @selected($selectedService === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label for="shipping-payment-type">روش پرداخت ارسال</label>
                        <select id="shipping-payment-type" name="shipping_payment_type" required>
                            @foreach ($shippingPaymentTypes as $key => $label)
                                <option value="{{ $key }}" @selected($selectedPaymentType === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="checkout-summary__muted">هزینه ارسال هنگام ثبت سفارش دوباره توسط سرور محاسبه می‌شود.</p>
                </section>

                <section class="checkout-card checkout-note">
                    <label for="customer-note">توضیحات سفارش</label>
                    <textarea id="customer-note" name="customer_note" rows="3" maxlength="1000">{{ old('customer_note') }}</textarea>
                </section>
                <div class="checkout-submit-area">
                    <button class="checkout-submit" type="submit" @disabled($addresses->isEmpty() || $previewError)>ثبت سفارش و ادامه به پرداخت</button>
                    <p class="checkout-summary__muted">پرداخت در مرحله بعد و پس از اتصال درگاه انجام می‌شود.</p>
                </div>
            </div>

            <aside class="checkout-summary" id="order-summary" aria-labelledby="summary-title">
                <div class="checkout-summary__inner">
                    <h2 id="summary-title">خلاصه سفارش</h2>
                    <div class="summary-products">
                        @foreach ($lines as $line)
                            <div>
                                <span class="summary-product__media {{ $line['image'] ? '' : 'media-placeholder' }}" aria-hidden="true">
                                    @if ($line['image'])<img src="{{ $line['image']['url'] }}" alt="" loading="lazy">@endif
                                </span>
                                <span><strong>{{ $line['name'] }}</strong><small>{{ $line['quantity'] }} × {{ number_format($line['unit_price']) }} ریال</small></span>
                                <b>{{ number_format($line['line_total']) }} ریال</b>
                            </div>
                        @endforeach
                    </div>
                    <dl>
                        <div><dt>جمع قیمت کالاها</dt><dd>{{ number_format($subtotal) }} ریال</dd></div>
                        <div><dt>تخفیف</dt><dd>{{ number_format($discount) }} ریال</dd></div>
                        <div><dt>مالیات</dt><dd>{{ number_format($tax) }} ریال</dd></div>
                        <div><dt>هزینه ارسال</dt><dd>{{ number_format($shipping) }} ریال</dd></div>
                        <div class="summary-total"><dt>مبلغ نهایی</dt><dd>{{ number_format($grandTotal) }} ریال</dd></div>
                    </dl>
                    @if ($cart['coupon'])<p class="checkout-summary__muted">کد تخفیف: {{ $cart['coupon'] }}</p>@endif
                </div>
            </aside>
        </form>
    </div>
</div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/checkout/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/checkout/forms.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/checkout/summary.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/checkout/responsive.css') }}">
@endpush
