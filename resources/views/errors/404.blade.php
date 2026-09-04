@extends('storefront.layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('storefront/assets/css/static/not-found.css') }}">@endpush
@section('content')<main class="not-found-page"><div class="site-container"><div class="not-found-card"><span class="not-found-code">۴۰۴</span><h1>صفحه پیدا نشد</h1><p>آدرس موردنظر وجود ندارد یا دیگر در دسترس نیست.</p><a class="public-button public-button--secondary" href="{{ route('storefront.home') }}">بازگشت به خانه</a><a class="account-link" href="{{ route('storefront.products.index') }}">مشاهده محصولات</a></div></div></main>@endsection
@push('scripts')<script src="{{ asset('storefront/assets/js/static/not-found.js') }}" defer></script>@endpush
