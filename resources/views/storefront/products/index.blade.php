@extends('storefront.layouts.app')

@section('content')
    <div class="category-page" id="products-page">
        <div class="site-container">
            <nav class="category-breadcrumb" aria-label="مسیر صفحه">
                <a href="{{ route('storefront.home') }}">خانه</a>
                <span class="category-breadcrumb__separator" aria-hidden="true">/</span>
                <span aria-current="page">محصولات</span>
            </nav>

            <section class="category-heading" aria-labelledby="category-title">
                <div class="category-heading__copy">
                    <h1 id="category-title">محصولات</h1>
                    <p>محصولات منتخب لوکسیر را جست‌وجو و بررسی کنید.</p>
                </div>
                <span class="category-heading__count">نمایش {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} از {{ $paginator->total() }} محصول</span>
            </section>

            <div class="category-content">
                <form class="category-filters" method="get" action="{{ route('storefront.products.index') }}" aria-label="فیلتر محصولات">
                    <div class="category-filter-drawer__header"><h2>فیلتر محصولات</h2></div>
                    <div class="filter-group"><details open><summary>دسته‌بندی</summary><div class="filter-options">
                        @foreach ($categories as $category)
                            <label class="filter-check"><input type="radio" name="category" value="{{ $category->slug }}" @checked(($filters['category'] ?? null) === $category->slug)> {{ $category->name }}</label>
                        @endforeach
                    </div></details></div>
                    <div class="filter-group"><details open><summary>برند</summary><div class="filter-options">
                        @foreach ($brands as $brand)
                            <label class="filter-check"><input type="radio" name="brand" value="{{ $brand->slug }}" @checked(($filters['brand'] ?? null) === $brand->slug)> {{ $brand->name }}</label>
                        @endforeach
                    </div></details></div>
                    <div class="filter-group"><details open><summary>محدوده قیمت (ریال)</summary><div class="price-fields">
                        <label>از<input type="number" min="0" name="min_price" value="{{ $filters['min_price'] ?? '' }}"></label>
                        <label>تا<input type="number" min="0" name="max_price" value="{{ $filters['max_price'] ?? '' }}"></label>
                    </div></details></div>
                    <div class="filter-group"><details open><summary>وضعیت</summary><div class="filter-options">
                        <label class="filter-check"><input type="checkbox" name="in_stock" value="1" @checked(filter_var($filters['in_stock'] ?? false, FILTER_VALIDATE_BOOLEAN))> فقط کالاهای موجود</label>
                    </div></details></div>
                    <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                    <input type="hidden" name="type" value="{{ $filters['type'] ?? '' }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'newest' }}">
                    <div class="filter-actions"><button class="filter-actions__reset" type="reset" data-filter-reset>پاک کردن</button><button class="filter-actions__apply" type="submit" data-filter-apply>اعمال فیلترها</button></div>
                </form>

                <section class="category-main" aria-labelledby="products-title">
                    <div class="category-toolbar">
                        <div class="category-toolbar__mobile"><button class="category-mobile-button" type="button" data-filter-open aria-controls="category-filter-drawer" aria-expanded="false">فیلترها</button><div class="category-sort"><label for="mobile-sort">مرتب‌سازی</label><select id="mobile-sort" name="sort" form="product-sort-form" onchange="document.getElementById('product-sort-form').submit()">
                            <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>جدیدترین</option><option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>ارزان‌ترین</option><option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>گران‌ترین</option><option value="name_asc" @selected(($filters['sort'] ?? null) === 'name_asc')>الفبا (صعودی)</option><option value="name_desc" @selected(($filters['sort'] ?? null) === 'name_desc')>الفبا (نزولی)</option>
                        </select></div></div>
                        <span class="category-result" id="products-title">{{ $paginator->total() }} محصول</span>
                        <div class="category-sort category-sort--desktop"><label for="desktop-sort">مرتب‌سازی:</label><select id="desktop-sort" name="sort" form="product-sort-form">
                            <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>جدیدترین</option><option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>ارزان‌ترین</option><option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>گران‌ترین</option><option value="name_asc" @selected(($filters['sort'] ?? null) === 'name_asc')>الفبا (صعودی)</option><option value="name_desc" @selected(($filters['sort'] ?? null) === 'name_desc')>الفبا (نزولی)</option>
                        </select></div>
                    </div>

                    <form id="product-sort-form" method="get" action="{{ route('storefront.products.index') }}">
                        @foreach ($filters as $key => $value)
                            @if ($key !== 'sort' && $value !== null && $value !== '')<input type="hidden" name="{{ $key }}" value="{{ is_bool($value) ? (int) $value : $value }}">@endif
                        @endforeach
                    </form>

                    @if (count($products))
                        <div id="products" class="category-products" data-category-products>
                            @foreach ($products as $product)
                                @include('storefront.components.product-card', ['product' => $product])
                            @endforeach
                        </div>
                    @else
                        <div class="storefront-empty" role="status"><h2>محصولی پیدا نشد</h2><p>فیلترها یا عبارت جست‌وجو را تغییر دهید.</p></div>
                    @endif

                    @include('storefront.components.pagination', ['paginator' => $paginator])
                </section>
            </div>
        </div>
    </div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/category/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/category/filters.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/category/toolbar.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/category/pagination.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/category/responsive.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('storefront/assets/js/category/filter-drawer.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/category/sort-dropdown.js') }}" defer></script>
@endpush
