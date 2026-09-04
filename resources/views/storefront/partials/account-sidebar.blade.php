<aside class="account-sidebar">
        <div class="account-sidebar__profile">
            <div class="account-avatar">{{ mb_substr($user->name, 0, 1) }}</div>
            <div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div>
        </div>
        <nav class="account-nav" aria-label="حساب کاربری">
            <a class="{{ request()->routeIs('storefront.account') ? 'is-active' : '' }}" href="{{ route('storefront.account') }}">پیشخوان</a>
            <a class="{{ request()->routeIs('storefront.account.profile') ? 'is-active' : '' }}" href="{{ route('storefront.account.profile') }}">اطلاعات حساب</a>
            <a class="{{ request()->routeIs('storefront.account.addresses*') ? 'is-active' : '' }}" href="{{ route('storefront.account.addresses') }}">آدرس‌های من</a>
            <form method="POST" action="{{ route('logout') }}">@csrf <button class="text-button text-button--danger" type="submit">خروج</button></form>
        </nav>
</aside>
