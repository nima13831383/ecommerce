<?php

namespace App\Observers;

use App\Models\ProductVariation;
use App\Services\Storefront\StorefrontDetailCacheRefreshService;

class ProductVariationStorefrontCacheObserver
{
    public bool $afterCommit = true;

    public function __construct(private readonly StorefrontDetailCacheRefreshService $refreshes) {}

    public function saved(ProductVariation $variation): void
    {
        if ($variation->exists && ! $this->hasPresentationChange($variation)) {
            return;
        }

        $this->request($variation);
    }

    public function deleted(ProductVariation $variation): void
    {
        $this->request($variation);
    }

    private function request(ProductVariation $variation): void
    {
        $product = $variation->product()->withTrashed()->first();

        if ($product === null) {
            return;
        }

        $this->refreshes->requestProduct($product->getKey(), $product->slug, $product->status === 'published');
    }

    private function hasPresentationChange(ProductVariation $variation): bool
    {
        return array_diff(array_keys($variation->getChanges()), ['stock_quantity', 'stock_status', 'updated_at']) !== [];
    }
}
