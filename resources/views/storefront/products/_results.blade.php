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
