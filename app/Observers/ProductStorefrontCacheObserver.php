<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Storefront\StorefrontDetailCacheRefreshService;

class ProductStorefrontCacheObserver
{
    public bool $afterCommit = true;

    public function __construct(private readonly StorefrontDetailCacheRefreshService $refreshes) {}

    public function updated(Product $product): void
    {
        if (! $this->hasMetadataChange($product)) {
            return;
        }

        $previous = $product->storefrontCachePrevious ?? $product->getPrevious();
        $product->storefrontCachePrevious = null;

        $this->refreshes->requestProduct(
            $product->getKey(),
            $previous['slug'] ?? $product->slug,
            ($previous['status'] ?? $product->status) === 'published',
        );
    }

    public function deleted(Product $product): void
    {
        $this->refreshes->requestProduct($product->getKey(), $product->slug, true);
    }

    public function restored(Product $product): void
    {
        $this->refreshes->requestProduct($product->getKey(), $product->slug, false);
    }

    private function hasMetadataChange(Product $product): bool
    {
        return array_diff(array_keys($product->getChanges()), ['stock_quantity', 'stock_status', 'updated_at']) !== [];
    }
}
