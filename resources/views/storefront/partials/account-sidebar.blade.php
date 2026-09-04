<aside class="account-sidebar">
        <div class="account-sidebar__profile">
            <div class="account-avatar">{{ mb_substr($user->name, 0, 1) }}</div>
            <div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div>
        </div>
        <nav class="account-nav" aria-label="حساب کاربری">
            <a class="{{ request()->routeIs('storefront.account') ? 'is-active' : '' }}" href="{{ route('storefront.account') }}"><svg class="icon"><use href="#i-grid"></use></svg>پیشخوان</a>
            <a class="{{ request()->routeIs('storefront.account.profile') ? 'is-active' : '' }}" href="{{ route('storefront.account.profile') }}"><svg class="icon"><use href="#i-user"></use></svg>اطلاعات حساب</a>
            <a class="{{ request()->routeIs('storefront.account.orders*') ? 'is-active' : '' }}" href="{{ route('storefront.account.orders') }}"><svg class="icon"><use href="#i-package"></use></svg>سفارش‌های من</a>
            <a class="{{ request()->routeIs('storefront.account.addresses*') ? 'is-active' : '' }}" href="{{ route('storefront.account.addresses') }}"><svg class="icon"><use href="#i-map-pin"></use></svg>آدرس‌های من</a>
            <form method="POST" action="{{ route('logout') }}">@csrf <button class="text-button text-button--danger" type="submit"><svg class="icon"><use href="#i-logout"></use></svg>خروج</button></form>
        </nav>
</aside>
