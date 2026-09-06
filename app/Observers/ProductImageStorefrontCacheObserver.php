<?php

namespace App\Observers;

use App\Models\ProductImage;
use App\Services\Storefront\StorefrontDetailCacheRefreshService;

class ProductImageStorefrontCacheObserver
{
    public bool $afterCommit = true;

    public function __construct(private readonly StorefrontDetailCacheRefreshService $refreshes) {}

    public function saved(ProductImage $image): void
    {
        $this->request($image);
    }

    public function deleted(ProductImage $image): void
    {
        $this->request($image);
    }

    private function request(ProductImage $image): void
    {
        $product = $image->product()->withTrashed()->first();

        if ($product === null) {
            return;
        }

        $this->refreshes->requestProduct($product->getKey(), $product->slug, $product->status === 'published');
    }
}
