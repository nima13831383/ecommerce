<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductVariation;

class ProductPriceResolver
{
    public function pricesForVariation(ProductVariation $variation): array
    {
        $regularPrice = (int) $variation->price;
        $salePrice = $variation->sale_price === null ? null : (int) $variation->sale_price;
        $effectivePrice = $this->isVariationOnSale($variation)
            ? $salePrice
            : $regularPrice;

        return [
            'regular_price' => $regularPrice,
            'sale_price' => $salePrice,
            'effective_price' => $effectivePrice,
        ];
    }

    public function effectivePriceForVariation(ProductVariation $variation): int
    {
        return $this->pricesForVariation($variation)['effective_price'];
    }

    public function isVariationOnSale(ProductVariation $variation): bool
    {
        if ($variation->sale_price === null) {
            return false;
        }

        $now = now();

        if ($variation->sale_starts_at?->isAfter($now)) {
            return false;
        }

        if ($variation->sale_ends_at?->isBefore($now)) {
            return false;
        }

        return true;
    }

    public function pricesForProduct(Product $product): array
    {
        if ($product->type !== 'variable') {
            return $this->pricesForSimpleProduct($product);
        }

        $variations = $product->variations()
            ->where('is_active', true)
            ->select(['id', 'product_id', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at'])
            ->cursor();

        $minimumPrice = null;
        $maximumPrice = null;

        foreach ($variations as $variation) {
            $price = $this->effectivePriceForVariation($variation);
            $minimumPrice = $minimumPrice === null ? $price : min($minimumPrice, $price);
            $maximumPrice = $maximumPrice === null ? $price : max($maximumPrice, $price);
        }

        return [
            'regular_price' => null,
            'sale_price' => null,
            'effective_price' => $minimumPrice,
            'minimum_price' => $minimumPrice,
            'maximum_price' => $maximumPrice,
        ];
    }

    private function pricesForSimpleProduct(Product $product): array
    {
        $regularPrice = (int) $product->price;
        $salePrice = $product->sale_price === null ? null : (int) $product->sale_price;
        $isOnSale = $salePrice !== null
            && (! $product->sale_starts_at || ! $product->sale_starts_at->isAfter(now()))
            && (! $product->sale_ends_at || ! $product->sale_ends_at->isBefore(now()));
        $effectivePrice = $isOnSale ? $salePrice : $regularPrice;

        return [
            'regular_price' => $regularPrice,
            'sale_price' => $salePrice,
            'effective_price' => $effectivePrice,
            'minimum_price' => $effectivePrice,
            'maximum_price' => $effectivePrice,
        ];
    }
}
