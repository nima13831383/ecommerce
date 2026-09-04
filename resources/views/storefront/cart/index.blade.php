@extends('storefront.layouts.app')

@section('bodyClass', 'cart-page-body')

@section('content')
    @php
        $cart = $cartView;
        $cart['grand_total'] = $cart['grand_total_with_shipping'] ?? $cart['grand_total'];
        $lines = $cart['lines'];
    @endphp
    <div class="cart-page {{ $lines ? 'has-items' : '' }}" data-cart-page>
        <div class="site-container">
            <nav class="cart-breadcrumb" aria-label="مسیر صفحه">
                <a href="{{ route('storefront.home') }}">خانه</a><span aria-hidden="true">/</span><span aria-current="page">سبد خرید</span>
            </nav>

            @if (session('status'))
                <p class="storefront-alert storefront-alert--success" role="status">{{ session('status') }}</p>
            @endif
            @if ($errors->has('cart'))
                <p class="storefront-alert storefront-alert--error" role="alert">{{ $errors->first('cart') }}</p>
            @endif
            @if ($errors->has('coupon'))
                <p class="storefront-alert storefront-alert--error" role="alert">{{ $errors->first('coupon') }}</p>
            @endif
            @if ($errors->has('shipping'))
                <p class="storefront-alert storefront-alert--error" role="alert">{{ $errors->first('shipping') }}</p>
            @endif
            @if ($cart['issues'])
                <div class="storefront-alert storefront-alert--error" role="alert">برخی کالاها دیگر قابل خرید نیستند؛ موجودی و وضعیت محصول را بررسی کنید.</div>
            @endif

            <div class="cart-page__heading">
                <div><h1>سبد خرید</h1><p data-cart-count>{{ $cart['item_count'] }} کالا در سبد خرید</p></div>
                <a class="cart-continue" href="{{ route('storefront.products.index') }}">ادامه خرید</a>
            </div>

            <div class="cart-layout" data-cart-content>
                <section class="cart-items-panel" aria-labelledby="cart-items-title">
                    <div class="cart-panel-heading"><h2 id="cart-items-title">محصولات انتخاب‌شده</h2><span>قیمت و تعداد</span></div>
                    <div class="cart-items" data-cart-items>
                        @foreach ($lines as $line)
                            <article class="cart-page-item" data-cart-item>
                                <div class="cart-page-item__media {{ $line['image'] ? '' : 'media-placeholder' }}" role="img" aria-label="تصویر {{ $line['name'] }}">
                                    @if ($line['image'])<img src="{{ $line['image']['url'] }}" alt="{{ $line['image']['alt'] ?: $line['name'] }}" loading="lazy">@endif
                                </div>
                                <div class="cart-page-item__info">
                                    @if ($line['url'])<a href="{{ $line['url'] }}" class="cart-page-item__title">{{ $line['name'] }}</a>@else<span class="cart-page-item__title">{{ $line['name'] }}</span>@endif
                                    @foreach ($line['options'] as $option)<span class="cart-page-item__variant">{{ $option['attribute'] }}: {{ $option['value'] }}</span>@endforeach
                                    @if (! $line['available'])<span class="cart-page-item__variant cart-line-unavailable">این محصول دیگر موجود نیست</span>@endif
                                </div>
                                <div class="cart-page-item__price"><span>قیمت واحد</span><strong>{{ number_format($line['unit_price']) }} ریال</strong></div>
                                <form method="post" action="{{ route('storefront.cart.items.update', ['item' => $line['id']]) }}" class="quantity-control">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" name="quantity" value="{{ max(1, $line['quantity'] - 1) }}" aria-label="کاهش تعداد">−</button>
                                    <output aria-label="تعداد">{{ $line['quantity'] }}</output>
                                    <button type="submit" name="quantity" value="{{ $line['quantity'] + 1 }}" aria-label="افزایش تعداد">+</button>
                                </form>
                                <div class="cart-page-item__total"><span>جمع</span><strong>{{ number_format($line['line_total']) }} ریال</strong></div>
                                <form method="post" action="{{ route('storefront.cart.items.remove', ['item' => $line['id']]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="cart-remove" aria-label="حذف {{ $line['name'] }} از سبد خرید">حذف</button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                    @if ($lines)<p class="cart-items-note">قیمت‌ها شامل مالیات هستند و موجودی هنگام ثبت سفارش دوباره بررسی می‌شود.</p>@endif
                </section>

                <aside class="cart-summary" id="cart-summary" aria-labelledby="cart-summary-title" @if (! $lines) hidden @endif>
                    <div class="cart-summary__inner">
                        <h2 id="cart-summary-title">خلاصه سفارش</h2>
                        <dl>
                            <div><dt>جمع قیمت کالاها</dt><dd data-cart-subtotal>{{ number_format($cart['subtotal']) }} ریال</dd></div>
                            <div><dt>تخفیف کالاها</dt><dd data-cart-discount>{{ number_format($cart['discount_total']) }} ریال</dd></div>
                            <div><dt>مالیات</dt><dd>{{ number_format($cart['tax_total']) }} ریال</dd></div>
                            <div><dt>هزینه ارسال</dt><dd class="cart-summary__muted">در مرحله بعد محاسبه می‌شود</dd></div>
                            <div class="cart-summary__total"><dt>جمع نهایی</dt><dd data-cart-total>{{ number_format($cart['grand_total']) }} ریال</dd></div>
                        </dl>
                        @if ($cart['coupon'])
                            <div class="coupon-box coupon-box--active">
                                <span>کد تخفیف: <strong>{{ $cart['coupon'] }}</strong></span>
                                <form method="post" action="{{ route('storefront.cart.coupon.remove') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="cart-remove">حذف کد تخفیف</button>
                                </form>
                            </div>
                        @else
                            <form method="post" action="{{ route('storefront.cart.coupon.apply') }}" class="coupon-form">
                                @csrf
                                <label for="coupon-code">کد تخفیف</label>
                                <div><input id="coupon-code" name="coupon" placeholder="مثلاً LUXIRA10" autocomplete="off" value="{{ old('coupon') }}"><button type="submit">اعمال</button></div>
                            </form>
                        @endif
                        @auth
                            @if ($lines)
                                <form method="post" action="{{ route('storefront.cart.shipping.quote') }}" class="shipping-quote-form">
                                    @csrf
                                    <h3>محاسبه هزینه ارسال</h3>
                                    <label for="shipping-address">آدرس تحویل</label>
                                    <select id="shipping-address" name="address_id" required>
                                        <option value="">انتخاب آدرس</option>
                                        @foreach ($addresses as $address)
                                            <option value="{{ $address->id }}" @selected((int) ($shippingSelection['address_id'] ?? 0) === $address->id)>{{ $address->first_name }} {{ $address->last_name }} - {{ $address->address_line }}</option>
                                        @endforeach
                                    </select>
                                    <label for="shipping-service">روش ارسال</label>
                                    <select id="shipping-service" name="service" required>
                                        @foreach ($shippingServices as $key => $label)
                                            <option value="{{ $key }}" @selected(($shippingSelection['service'] ?? 'pishtaz') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <label for="shipping-payment">روش پرداخت ارسال</label>
                                    <select id="shipping-payment" name="payment_type" required>
                                        @foreach ($shippingPaymentTypes as $key => $label)
                                            <option value="{{ $key }}" @selected(($shippingSelection['payment_type'] ?? 'online') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit">محاسبه هزینه ارسال</button>
                                </form>
                            @endif
                        @else
                            <p class="cart-checkout-note">برای محاسبه هزینه ارسال، ابتدا وارد حساب کاربری شوید.</p>
                        @endauth
                        @if ($shipping)
                            <p class="shipping-quote-result" role="status">روش {{ $shipping['service_label'] }}: {{ number_format($shipping['amount']) }} ریال</p>
                        @elseif ($shippingError)
                            <p class="storefront-alert storefront-alert--error" role="alert">{{ $shippingError }}</p>
                        @endif
                        <a class="cart-checkout" href="{{ route('storefront.checkout.show') }}">ادامه فرایند خرید</a>
                        <p class="cart-checkout-note">انتخاب آدرس و روش ارسال در مرحله بعد انجام می‌شود.</p>
                    </div>
                </aside>
            </div>

            <section class="cart-empty" @if ($lines) hidden @endif aria-labelledby="cart-empty-title">
                <div class="cart-empty__visual media-placeholder" aria-hidden="true">♡</div>
                <h2 id="cart-empty-title">سبد خرید شما خالی است</h2>
                <p>هنوز محصولی به سبد خرید اضافه نکرده‌اید.</p>
                <a class="pill-button" href="{{ route('storefront.products.index') }}">ادامه خرید</a>
            </section>

            @if ($lines)
                <form method="post" action="{{ route('storefront.cart.clear') }}" class="cart-clear-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cart-remove">خالی کردن سبد خرید</button>
                </form>
            @endif
        </div>
    </div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/cart/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/cart/cart-items.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/cart/summary.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/cart/empty-state.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/cart/responsive.css') }}">
@endpush
