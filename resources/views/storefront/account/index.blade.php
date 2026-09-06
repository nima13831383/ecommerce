@extends('storefront.layouts.account')

@section('account-content')
    @include('storefront.partials.account-sidebar')
    <section class="account-content">
        <div class="account-heading"><div><h1>حساب کاربری من</h1><p>سلام، کاربر عزیز<br>از این بخش می‌توانید سفارش‌ها، اطلاعات حساب و آدرس‌های خود را مدیریت کنید.</p></div></div>
        @if (session('status')) <p class="form-feedback" role="status">{{ session('status') }}</p> @endif
        <div class="account-grid">
            <article class="account-summary-card"><span>سفارش‌های در انتظار</span><strong>{{ \App\Support\PersianNumber::digits($pendingOrderCount) }}</strong><small>در حال پیگیری</small></article>
            <article class="account-summary-card"><span>سفارش‌های تکمیل‌شده</span><strong>{{ \App\Support\PersianNumber::digits($completedOrderCount) }}</strong><small>خرید موفق</small></article>
            <article class="account-summary-card"><span>آدرس‌های ثبت‌شده</span><strong>{{ \App\Support\PersianNumber::digits($addressCount) }}</strong><small>آدرس فعال</small></article>
            <article class="account-summary-card"><span>علاقه‌مندی‌ها</span><strong>—</strong><small>این بخش به‌زودی فعال می‌شود</small></article>
        </div>
        <div class="account-columns">
            <article class="account-card"><div class="account-card__heading"><h2>آخرین سفارش‌ها</h2><a class="account-link" href="{{ route('storefront.account.orders') }}">مشاهده همه سفارش‌ها</a></div><div class="orders-preview">
            @forelse($recentOrders as $order)
                <div class="order-row"><div><strong>#{{ $order['order_number'] }}</strong><small>{{ \App\Support\JalaliDate::format($order['created_at'], 'j F Y') }}</small></div><span>{{ \App\Support\PersianNumber::digits($order['item_count']) }} کالا</span><strong class="order-row__amount">{{ \App\Support\PersianNumber::money($order['grand_total']) }}</strong><span class="status-badge status-badge--info">{{ $order['status']['label'] }}</span><a class="account-link" href="{{ route('storefront.account.orders.show', ['order' => $order['order_number']]) }}">مشاهده جزئیات</a></div>
            @empty
                <p>هنوز سفارشی ثبت نکرده‌اید.</p>
            @endforelse
            </div></article>
            <article class="account-card"><div class="account-card__heading"><h2>اطلاعات حساب</h2><a href="{{ route('storefront.account.profile') }}">ویرایش اطلاعات</a></div><p class="account-note">{{ $user->name }}<br>{{ $user->mobile ?? '—' }}<br>{{ $user->email }}</p></article>
        </div>
    </section>
@endsection
