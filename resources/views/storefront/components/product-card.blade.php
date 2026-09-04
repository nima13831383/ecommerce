@props(['product'])

@php
    $pricing = $product['pricing'] ?? [];
    $image = $product['image'] ?? null;
    $isVariable = ($product['type'] ?? null) === 'variable';
    $effective = $pricing['effective_price'] ?? null;
    $regular = $pricing['regular_price'] ?? null;
    $minimum = $pricing['minimum_price'] ?? null;
    $maximum = $pricing['maximum_price'] ?? null;
    $discounted = (bool) ($pricing['is_discounted'] ?? false);
@endphp

<article class="product-card" data-product-card>
    <a class="product-card__link" href="{{ route('storefront.products.show', ['product' => $product['slug']]) }}" aria-label="{{ $product['name'] }}">
        <div class="product-card__media {{ $image ? '' : 'media-placeholder' }}">
            @if ($image)
                <img src="{{ $image['url'] }}" alt="{{ $image['alt'] ?: $product['name'] }}" loading="lazy">
            @endif
            @if ($discounted)
                <span class="discount">تخفیف</span>
            @endif
        </div>
        <div class="product-card__body">
            <h3 class="product-card__title">{{ $product['name'] }}</h3>
            @if ($discounted && $regular !== null)
                <span class="product-card__old">{{ number_format((int) $regular) }} ریال</span>
            @endif
            <div class="product-card__price">
                @if ($isVariable && $minimum !== null && $maximum !== null && $minimum !== $maximum)
                    {{ number_format((int) $minimum) }} تا {{ number_format((int) $maximum) }} ریال
                @elseif ($effective !== null)
                    {{ number_format((int) $effective) }} ریال
                @else
                    —
                @endif
            </div>
            <span class="product-card__availability {{ ($product['availability']['in_stock'] ?? false) ? 'is-in-stock' : 'is-out-of-stock' }}">
                {{ ($product['availability']['in_stock'] ?? false) ? 'موجود' : 'ناموجود' }}
            </span>
        </div>
    </a>
</article>
