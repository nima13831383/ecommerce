@extends('storefront.layouts.account')

@section('account-content')
    @include('storefront.partials.account-sidebar')
    <section class="account-content">
        <div class="account-heading"><div><h1>حساب کاربری</h1><p>سلام {{ $user->name }}، به حساب کاربری خود خوش آمدید.</p></div></div>
        @if (session('status')) <p class="form-feedback" role="status">{{ session('status') }}</p> @endif
        <div class="account-grid">
            <article class="account-card"><h2>اطلاعات حساب</h2><p>{{ $user->email }}</p><a class="account-button account-button--pink" href="{{ route('storefront.account.profile') }}">ویرایش پروفایل</a></article>
            <article class="account-card"><h2>آدرس‌ها</h2><p>{{ $addressCount }} آدرس ذخیره شده</p><a class="account-button account-button--light" href="{{ route('storefront.account.addresses') }}">مدیریت آدرس‌ها</a></article>
        </div>
        <section class="account-card orders-preview"><div class="account-card__heading"><h2>آخرین سفارش‌ها</h2><a class="account-link" href="{{ route('storefront.account.orders') }}">مشاهده همه ({{ $orderCount }})</a></div>
            @forelse($recentOrders as $order)
                <div class="order-row"><div><strong>#{{ $order['order_number'] }}</strong><small>{{ $order['created_at']?->format('Y/m/d') }}</small></div><span class="status-badge status-badge--info">{{ $order['status']['label'] }}</span><strong class="order-row__amount">{{ number_format($order['grand_total']) }} ریال</strong><a class="account-link" href="{{ route('storefront.account.orders.show', ['order' => $order['order_number']]) }}">جزئیات</a></div>
            @empty
                <p>هنوز سفارشی ثبت نکرده‌اید.</p>
            @endforelse
        </section>
    </section>
@endsection
