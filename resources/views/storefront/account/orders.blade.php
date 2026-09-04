@extends('storefront.layouts.account')

@section('account-content')
    @include('storefront.partials.account-sidebar')
    <section class="account-content">
        <div class="account-heading"><div><h1>سفارش‌های من</h1><p>تاریخچه سفارش‌ها و وضعیت ارسال آن‌ها را ببینید.</p></div></div>
        <section class="account-card">
            <form class="order-toolbar" method="GET" action="{{ route('storefront.account.orders') }}">
                <div class="account-filters" role="tablist" aria-label="فیلتر سفارش‌ها">
                    <a class="account-filter {{ $status === null ? 'is-active' : '' }}" href="{{ route('storefront.account.orders') }}">همه</a>
                    @foreach ([
                        'awaiting_payment' => 'در انتظار پرداخت',
                        'processing' => 'در حال پردازش',
                        'shipped' => 'ارسال شده',
                        'delivered' => 'تحویل شده',
                        'cancelled' => 'لغو شده',
                    ] as $value => $label)
                        <a class="account-filter {{ $status === $value ? 'is-active' : '' }}" href="{{ route('storefront.account.orders', ['status' => $value]) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </form>
            <div class="orders-list">
                @forelse ($orders as $order)
                    <article class="order-card">
                        <div class="order-card__grid">
                            <div><span class="order-card__label">شماره سفارش</span><strong>#{{ $order['order_number'] }}</strong></div>
                            <div><span class="order-card__label">تاریخ</span><strong>{{ \App\Support\JalaliDate::format($order['created_at'], 'j F Y') }}</strong></div>
                            <div><span class="order-card__label">مبلغ</span><strong class="order-card__amount">{{ number_format($order['grand_total']) }} ریال</strong></div>
                            <div><span class="order-card__label">وضعیت</span><span class="status-badge status-badge--{{ in_array($order['status']['value'], ['cancelled', 'failed'], true) ? 'danger' : ($order['status']['value'] === 'delivered' ? 'success' : 'info') }}">{{ $order['status']['label'] }}</span></div>
                            <a class="order-card__action" href="{{ route('storefront.account.orders.show', ['order' => $order['order_number']]) }}">مشاهده جزئیات</a>
                        </div>
                    </article>
                @empty
                    <div class="empty-state"><h2>هنوز سفارشی ثبت نکرده‌اید.</h2><p>محصولات موردنظر خود را پیدا کنید و اولین سفارش‌تان را ثبت کنید.</p><a class="account-button account-button--pink" href="{{ route('storefront.products.index') }}">مشاهده محصولات</a></div>
                @endforelse
            </div>
            {{ $orders->links('storefront.components.pagination') }}
        </section>
    </section>
@endsection
