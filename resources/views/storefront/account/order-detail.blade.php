@extends('storefront.layouts.account')

@section('account-content')
    @include('storefront.partials.account-sidebar')
    <section class="account-content">
        <div class="account-heading"><div><h1>جزئیات سفارش</h1><p><a class="account-link" href="{{ route('storefront.account.orders') }}">بازگشت به سفارش‌ها</a></p></div></div>
        <section class="account-card">
            <div class="detail-top"><div><h2>سفارش #{{ $order['order_number'] }}</h2><p>ثبت شده در {{ \App\Support\JalaliDate::format($order['created_at'], 'j F Y H:i') }}</p></div><div class="detail-top__meta"><span class="status-badge status-badge--info">{{ $order['status']['label'] }}</span><strong>{{ number_format($order['totals']['grand_total']) }} ریال</strong></div></div>
            @if ($order['timeline'] !== [])
                <div class="timeline" aria-label="روند سفارش">
                    @foreach ($order['timeline'] as $entry)
                        <div class="timeline-step is-complete"><div class="timeline-dot">✓</div>{{ $entry['label'] }}</div>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="detail-grid">
            <section class="account-card">
                <div class="account-card__heading"><h2>محصولات سفارش</h2><p>{{ collect($order['items'])->sum('quantity') }} کالا</p></div>
                <div class="order-products">
                    @foreach ($order['items'] as $item)
                        <div class="detail-product">
                            <span class="detail-product__media {{ $item['image'] ? '' : 'media-placeholder' }}">@if ($item['image'])<img src="{{ $item['image']['url'] }}" alt="{{ $item['image']['alt'] ?: $item['name'] }}" loading="lazy">@endif</span>
                            <span><strong>@if ($item['url'])<a href="{{ $item['url'] }}">{{ $item['name'] }}</a>@else{{ $item['name'] }}@endif</strong><small>{{ $item['sku'] ? 'SKU: '.$item['sku'].' · ' : '' }}تعداد {{ $item['quantity'] }}@foreach ($item['variation_attributes'] as $attribute => $value) · {{ $attribute }}: {{ $value }}@endforeach</small></span>
                            <b>{{ number_format($item['line_total']) }} ریال</b>
                        </div>
                    @endforeach
                </div>
            </section>
            <div class="detail-stack">
                <section class="account-card"><div class="account-card__heading"><h2>مبلغ سفارش</h2></div><div class="detail-totals"><div class="detail-total-row"><span>جمع کالاها</span><strong>{{ number_format($order['totals']['items_subtotal']) }} ریال</strong></div><div class="detail-total-row"><span>تخفیف</span><strong>{{ number_format($order['totals']['discount_total']) }} ریال</strong></div><div class="detail-total-row"><span>مالیات</span><strong>{{ number_format($order['totals']['tax_total']) }} ریال</strong></div><div class="detail-total-row"><span>هزینه ارسال</span><strong>{{ number_format($order['totals']['shipping_total']) }} ریال</strong></div><div class="detail-total-row detail-total-row--grand"><span>مبلغ نهایی</span><strong>{{ number_format($order['totals']['grand_total']) }} ریال</strong></div></div></section>
                <section class="account-card"><div class="account-card__heading"><h2>وضعیت پرداخت</h2></div><dl class="info-list"><div><dt>وضعیت</dt><dd><span class="status-badge status-badge--{{ $order['payment_status']['value'] === 'paid' ? 'success' : ($order['payment_status']['value'] === 'unpaid' ? 'warning' : 'info') }}">{{ $order['payment_status']['label'] }}</span></dd></div>@if ($order['payment'])<div><dt>آخرین تلاش</dt><dd>{{ $order['payment']['status']['label'] }}</dd></div>@endif</dl>@if ($paymentRetryAllowed)<form class="detail-actions" method="POST" action="{{ route('storefront.payment.initiate', ['order' => $order['order_number']]) }}">@csrf<button class="account-button account-button--pink" type="submit">ادامه به پرداخت</button></form>@endif</section>
            </div>
        </div>

        @if ($order['coupon'])<section class="account-card"><div class="account-card__heading"><h2>تخفیف سفارش</h2></div><p>کد تخفیف: <strong>{{ $order['coupon']['code'] }}</strong> · {{ number_format($order['coupon']['discount_amount']) }} ریال</p></section>@endif
        <section class="account-card"><div class="account-card__heading"><h2>اطلاعات ارسال</h2></div><dl class="info-list">@if ($order['shipping'])<div><dt>روش ارسال</dt><dd>{{ $order['shipping']['service'] ?? '—' }}</dd></div><div><dt>هزینه ارسال</dt><dd>{{ number_format($order['shipping']['amount']) }} ریال</dd></div>@endif @if ($order['shipping_address'])<div><dt>گیرنده</dt><dd>{{ $order['shipping_address']['first_name'] }} {{ $order['shipping_address']['last_name'] }} · {{ $order['shipping_address']['mobile'] }}</dd></div><div><dt>نشانی</dt><dd>{{ $order['shipping_address']['province_name'] }}، {{ $order['shipping_address']['city_name'] }}، {{ $order['shipping_address']['address_line'] }}</dd></div>@if ($order['shipping_address']['postal_code'])<div><dt>کد پستی</dt><dd>{{ $order['shipping_address']['postal_code'] }}</dd></div>@endif @endif</dl></section>
        @if ($order['billing_address'] && $order['billing_address'] !== $order['shipping_address'])<section class="account-card"><div class="account-card__heading"><h2>نشانی صورتحساب</h2></div><p>{{ $order['billing_address']['province_name'] }}، {{ $order['billing_address']['city_name'] }}، {{ $order['billing_address']['address_line'] }}</p></section>@endif
        <section class="account-card"><div class="account-card__heading"><h2>ارسال سفارش</h2></div>@if ($order['shipment'])<dl class="info-list"><div><dt>وضعیت</dt><dd><span class="status-badge status-badge--info">{{ $order['shipment']['status']['label'] }}</span></dd></div>@if ($order['shipment']['carrier'])<div><dt>حامل</dt><dd>{{ $order['shipment']['carrier'] }}</dd></div>@endif @if ($order['shipment']['tracking_number'])<div><dt>کد رهگیری</dt><dd dir="ltr">{{ $order['shipment']['tracking_number'] }}</dd></div>@endif @if ($order['shipment']['shipped_at'])<div><dt>ارسال شده</dt><dd>{{ \App\Support\JalaliDate::format($order['shipment']['shipped_at'], 'Y/m/d H:i') }}</dd></div>@endif @if ($order['shipment']['delivered_at'])<div><dt>تحویل شده</dt><dd>{{ \App\Support\JalaliDate::format($order['shipment']['delivered_at'], 'Y/m/d H:i') }}</dd></div>@endif</dl>@else<p>سفارش شما در حال پردازش است؛ اطلاعات ارسال پس از ایجاد مرسوله نمایش داده می‌شود.</p>@endif</section>
        @if ($order['customer_note'])<section class="account-card"><div class="account-card__heading"><h2>یادداشت سفارش</h2></div><p>{{ $order['customer_note'] }}</p></section>@endif
    </section>
@endsection
