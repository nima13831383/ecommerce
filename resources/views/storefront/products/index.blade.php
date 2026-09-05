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
                <span class="category-heading__count">نمایش {{ \App\Support\PersianNumber::digits($paginator->firstItem() ?? 0) }}–{{ \App\Support\PersianNumber::digits($paginator->lastItem() ?? 0) }} از {{ \App\Support\PersianNumber::digits($paginator->total()) }} محصول</span>
            </section>
            @php($activeCategories = (array) ($filters['categories'] ?? ($filters['category'] ?? [])))
            @php($activeBrands = (array) ($filters['brands'] ?? ($filters['brand'] ?? [])))
            @if ($activeCategories !== [] || $activeBrands !== [] || ! empty($filters['search']) || ! empty($filters['in_stock']))
                <div class="active-filters" aria-label="فیلترهای فعال">
                    @foreach ($activeCategories as $active)<a href="{{ route('storefront.products.index', array_merge($filters, ['categories' => array_values(array_diff($activeCategories, [$active])), 'page' => null])) }}">دسته: {{ $active }} ×</a>@endforeach
                    @foreach ($activeBrands as $active)<a href="{{ route('storefront.products.index', array_merge($filters, ['brands' => array_values(array_diff($activeBrands, [$active])), 'page' => null])) }}">برند: {{ $active }} ×</a>@endforeach
                    <button type="reset" form="product-filter-form" data-filter-reset>پاک کردن همه</button>
                </div>
            @endif

            <div class="category-content">
                <form id="product-filter-form" class="category-filters" method="get" action="{{ route('storefront.products.index') }}" aria-label="فیلتر محصولات">
                    <div class="category-filter-drawer__header"><h2>فیلتر محصولات</h2></div>
                    <div class="filter-group"><details><summary>دسته‌بندی</summary><div class="filter-options">
                        @foreach ($categories as $category)
                            <label class="filter-check"><input type="checkbox" name="categories[]" value="{{ $category->slug }}" @checked(in_array($category->slug, (array) ($filters['categories'] ?? ($filters['category'] ?? [])), true))> {{ $category->name }}</label>
                        @endforeach
                    </div></details></div>
                    <div class="filter-group"><details><summary>برند</summary><div class="filter-options">
                        @foreach ($brands as $brand)
                            <label class="filter-check"><input type="checkbox" name="brands[]" value="{{ $brand->slug }}" @checked(in_array($brand->slug, (array) ($filters['brands'] ?? ($filters['brand'] ?? [])), true))> {{ $brand->name }}</label>
                        @endforeach
                    </div></details></div>
                    <div class="filter-group"><details><summary>محدوده قیمت (ریال)</summary><div class="price-fields">
                        <label>از<input type="number" min="0" name="min_price" value="{{ $filters['min_price'] ?? '' }}"></label>
                        <label>تا<input type="number" min="0" name="max_price" value="{{ $filters['max_price'] ?? '' }}"></label>
                    </div></details></div>
                    <div class="filter-group"><details><summary>وضعیت</summary><div class="filter-options">
                        <label class="filter-check"><input type="checkbox" name="in_stock" value="1" @checked(filter_var($filters['in_stock'] ?? false, FILTER_VALIDATE_BOOLEAN))> فقط کالاهای موجود</label>
                    </div></details></div>
                    <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                    <input type="hidden" name="type" value="{{ $filters['type'] ?? '' }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'newest' }}">
                    <div class="filter-actions"><button class="filter-actions__reset" type="reset" data-filter-reset>پاک کردن همه</button></div>
                </form>

                <section class="category-main" aria-labelledby="products-title">
                    <div class="category-toolbar">
                        <div class="category-toolbar__mobile"><button class="category-mobile-button" type="button" data-filter-open aria-controls="category-filter-drawer" aria-expanded="false">فیلترها</button><div class="category-sort"><label for="mobile-sort">مرتب‌سازی</label><select id="mobile-sort" name="sort" form="product-sort-form" onchange="document.getElementById('product-sort-form').submit()">
                            <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>جدیدترین</option><option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>ارزان‌ترین</option><option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>گران‌ترین</option><option value="name_asc" @selected(($filters['sort'] ?? null) === 'name_asc')>الفبا (صعودی)</option><option value="name_desc" @selected(($filters['sort'] ?? null) === 'name_desc')>الفبا (نزولی)</option>
                        </select></div></div>
                        <span class="category-result" id="products-title">{{ \App\Support\PersianNumber::digits($paginator->total()) }} محصول</span>
                        <div class="category-sort category-sort--desktop"><label for="desktop-sort">مرتب‌سازی:</label><select id="desktop-sort" name="sort" form="product-sort-form">
                            <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>جدیدترین</option><option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>ارزان‌ترین</option><option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>گران‌ترین</option><option value="name_asc" @selected(($filters['sort'] ?? null) === 'name_asc')>الفبا (صعودی)</option><option value="name_desc" @selected(($filters['sort'] ?? null) === 'name_desc')>الفبا (نزولی)</option>
                        </select></div>
                    </div>

                    <form id="product-sort-form" method="get" action="{{ route('storefront.products.index') }}">
                        @foreach ($filters as $key => $value)
                            @if ($key !== 'sort' && $value !== null && $value !== '')<input type="hidden" name="{{ $key }}" value="{{ is_bool($value) ? (int) $value : $value }}">@endif
                        @endforeach
                    </form>

                    @include('storefront.products._results')
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
