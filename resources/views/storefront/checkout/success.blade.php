@extends('storefront.layouts.app')

@section('bodyClass', 'checkout-page-body')

@section('content')
    <div class="site-container checkout-success-page">
        <div class="checkout-card" role="status">
            <h1>سفارش شما ثبت شد</h1>
            <p>شماره سفارش: <strong>{{ $order->order_number }}</strong></p>
            <p>وضعیت سفارش: <strong>{{ $order->status->value }}</strong></p>
            <p>وضعیت پرداخت: <strong>{{ $order->payment_status->value }}</strong></p>
            <p>مبلغ نهایی: <strong>{{ number_format((int) $order->grand_total) }} ریال</strong></p>
            @if ($errors->has('payment'))
                <p class="storefront-alert storefront-alert--error" role="alert">{{ $errors->first('payment') }}</p>
            @endif
            @if (($paymentAvailable ?? false) && $order->payment_status->value === 'unpaid' && $order->status->value !== 'cancelled')
                <form method="post" action="{{ route('storefront.payment.initiate', ['order' => $order->order_number]) }}">
                    @csrf
                    <button class="pill-button" type="submit">ادامه به پرداخت</button>
                </form>
            @else
                <p class="storefront-alert storefront-alert--error" role="alert">درگاه پرداخت در حال حاضر در دسترس نیست. لطفاً بعداً دوباره تلاش کنید.</p>
            @endif
            <a class="pill-button" href="{{ route('storefront.products.index') }}">ادامه خرید</a>
        </div>
    </div>
@endsection
