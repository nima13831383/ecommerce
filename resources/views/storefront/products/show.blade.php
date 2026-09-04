@extends('storefront.layouts.app')

@php
    $pricing = $product['pricing'] ?? [];
    $availability = $product['availability']['in_stock'] ?? false;
    $gallery = $product['gallery'] ?? [];
    $mainImage = $product['image'] ?? ($gallery[0] ?? null);
    $isVariable = ($product['type'] ?? null) === 'variable';
    $hasDiscount = (bool) ($pricing['is_discounted'] ?? false);
    $effectivePrice = $pricing['effective_price'] ?? null;
    $regularPrice = $pricing['regular_price'] ?? null;
    $minimumPrice = $pricing['minimum_price'] ?? null;
    $maximumPrice = $pricing['maximum_price'] ?? null;
@endphp

@section('bodyClass', 'product-page-body')

@section('content')
    <div class="product-page product-detail-page" data-product-detail
         data-product-id="{{ $product['id'] }}"
         data-product-type="{{ $product['type'] }}"
         data-resolve-url="{{ url('/api/v1/products/'.$product['id'].'/resolve-variation') }}"
         data-required-attributes='@json(collect($product['attributes'] ?? [])->pluck("id")->values())'>
        <div class="site-container">
            <nav class="product-breadcrumb" aria-label="مسیر صفحه">
                <a href="{{ route('storefront.home') }}">خانه</a>
                <span class="product-breadcrumb__separator" aria-hidden="true">/</span>
                @if (! empty($product['categories'][0]))
                    <a href="{{ route('storefront.products.index', ['category' => $product['categories'][0]['slug']]) }}">{{ $product['categories'][0]['name'] }}</a>
                    <span class="product-breadcrumb__separator" aria-hidden="true">/</span>
                @endif
                <span aria-current="page">{{ $product['name'] }}</span>
            </nav>

            <section class="product-main" aria-labelledby="product-title">
                <div class="product-gallery" data-gallery>
                    <div class="product-gallery__main {{ $mainImage ? '' : 'media-placeholder' }}" data-gallery-main data-gallery-state="1">
                        @if ($mainImage)
                            <img data-gallery-image src="{{ $mainImage['url'] }}" alt="{{ $mainImage['alt'] ?: $product['name'] }}">
                        @endif
                        @if ($hasDiscount)
                            <span class="product-gallery__badge">تخفیف</span>
                        @endif
                    </div>
                    @if ($gallery)
                        <div class="product-gallery__thumbs" role="list">
                            @foreach ($gallery as $index => $image)
                                <button type="button" class="product-gallery__thumb {{ $index === 0 ? 'is-active' : '' }}"
                                        data-gallery-thumb="{{ $index }}" data-image-url="{{ $image['url'] }}"
                                        data-image-alt="{{ $image['alt'] ?: $product['name'] }}"
                                        aria-label="نمایش تصویر {{ $index + 1 }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                                    <img src="{{ $image['url'] }}" alt="" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="product-info">
                    @if ($product['brand'])
                        <span class="product-info__brand">{{ $product['brand']['name'] }}</span>
                    @endif
                    <h1 id="product-title">{{ $product['name'] }}</h1>
                    @if (! empty($product['short_description']))
                        <p class="product-info__subtitle">{{ $product['short_description'] }}</p>
                    @endif

                    <div class="product-meta">
                        <span>{{ $isVariable ? 'محصول متغیر' : 'محصول ساده' }}</span>
                        @if (! empty($product['categories'][0]))
                            <span>{{ $product['categories'][0]['name'] }}</span>
                        @endif
                    </div>

                    <div class="product-price-box" data-product-pricing>
                        <span class="product-price" data-price>
                            @if ($isVariable && $minimumPrice !== null && $maximumPrice !== null && $minimumPrice !== $maximumPrice)
                                {{ number_format((int) $minimumPrice) }} تا {{ number_format((int) $maximumPrice) }} ریال
                            @elseif ($effectivePrice !== null)
                                {{ number_format((int) $effectivePrice) }} ریال
                            @else
                                —
                            @endif
                        </span>
                        <span class="product-old-price" data-regular-price @if (! $hasDiscount || $regularPrice === null) hidden @endif>
                            @if ($hasDiscount && $regularPrice !== null){{ number_format((int) $regularPrice) }} ریال @endif
                        </span>
                        <span class="product-discount" data-discount @if (! $hasDiscount) hidden @endif>تخفیف</span>
                    </div>

                    <div class="product-stock {{ $availability ? 'is-in-stock' : 'is-out-of-stock' }}" data-stock role="status">
                        {{ $availability ? 'موجود در انبار' : 'ناموجود' }}
                    </div>

                    <div class="product-purchase">
                        @if ($isVariable)
                            <div class="product-selector" data-variant-selector>
                                @foreach ($product['attributes'] ?? [] as $attribute)
                                    <fieldset data-attribute-id="{{ $attribute['id'] }}">
                                        <legend class="product-selector__label">{{ $attribute['name'] }}</legend>
                                        <div class="product-selector__options">
                                            @foreach ($attribute['options'] as $option)
                                                <button type="button" class="product-selector__option"
                                                        data-variant-group="{{ $attribute['id'] }}"
                                                        data-variant-value="{{ $option['id'] }}"
                                                        data-attribute-id="{{ $attribute['id'] }}"
                                                        data-value-id="{{ $option['id'] }}"
                                                        aria-checked="false" role="radio">{{ $option['value'] }}</button>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endforeach
                                <p class="storefront-selection-status" data-selection-status role="status">برای مشاهده قیمت و موجودی، گزینه‌ها را انتخاب کنید.</p>
                            </div>
                        @endif

                        <form class="product-purchase-form" method="post" action="{{ route('storefront.cart.items.store') }}" data-add-cart-form>
                            @csrf
                            <div class="product-purchase__row">
                            <div class="quantity-control" data-quantity>
                                <button type="button" data-quantity-decrease aria-label="کاهش تعداد">−</button>
                                <output data-quantity-value aria-live="polite">۱</output>
                                <button type="button" data-quantity-increase aria-label="افزایش تعداد">+</button>
                            </div>
                            <button type="submit" class="product-add" data-add-cart @disabled(! $availability || $isVariable) aria-disabled="{{ ! $availability || $isVariable ? 'true' : 'false' }}">
                                {{ $availability ? 'افزودن به سبد خرید' : 'ناموجود' }}
                            </button>
                            <button type="button" class="product-favorite" disabled aria-disabled="true" aria-label="افزودن به علاقه‌مندی‌ها">♡</button>
                        </div>
                            <input type="hidden" name="product_id" value="{{ $product['id'] }}" data-product-id-input>
                            <input type="hidden" name="variation_id" value="" data-selected-variation>
                            <input type="hidden" name="quantity" value="1" data-quantity-input>
                        </form>
                        <p class="storefront-form-message" data-add-cart-message role="status" aria-live="polite"></p>
                    </div>

                    <div class="product-benefits"><div class="product-benefits__item"><strong>تضمین اصالت کالا</strong><span>تضمین ۱۰۰٪ اصل بودن محصولات</span></div><div class="product-benefits__item"><strong>ارسال سریع</strong><span>تحویل مطمئن سفارش</span></div><div class="product-benefits__item"><strong>پرداخت امن</strong><span>پرداخت با کارت‌های شتاب</span></div><div class="product-benefits__item"><strong>پشتیبانی</strong><span>همراه شما در خرید</span></div></div>
                </div>
            </section>
            @if ($relatedProducts->isNotEmpty())<section class="product-section related-section" aria-labelledby="related-products-title"><div class="product-section__heading"><h2 id="related-products-title">محصولات مرتبط</h2></div><div class="product-grid">@foreach ($relatedProducts as $relatedProduct)@include('storefront.components.product-card', ['product' => $relatedProduct])@endforeach</div></section>@endif
            <section class="product-section reviews-section" aria-labelledby="reviews-title"><div class="product-section__heading"><h2 id="reviews-title">نظرات کاربران</h2></div><div class="storefront-empty"><p>هنوز نظری ثبت نشده است.</p></div></section>

            <section class="product-section" aria-labelledby="product-description-title">
                <div class="product-section__heading">
                    <h2 id="product-description-title">توضیحات محصول</h2>
                </div>
                <div class="product-details">
                    <div class="product-details__item is-open">
                        <button type="button" class="product-details__trigger" aria-expanded="true">معرفی محصول</button>
                        <div class="product-details__content">
                            {{ $product['description'] ?: ($product['short_description'] ?: 'توضیحی برای این محصول ثبت نشده است.') }}
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/product/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/product/gallery.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/product/product-info.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/product/details.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/product/responsive.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('storefront/assets/js/product/gallery.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/product/details.js') }}" defer></script>
    <script src="{{ asset('storefront/assets/js/product/detail-selection.js') }}" defer></script>
@endpush
