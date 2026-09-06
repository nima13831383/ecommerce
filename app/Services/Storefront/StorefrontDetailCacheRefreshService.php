<?php

namespace App\Services\Storefront;

use App\Enums\CacheRebuildDomain;
use App\Jobs\Storefront\RefreshStorefrontDetailAfterWrite;
use App\Models\Post;
use App\Models\Product;
use App\Services\Blog\StorefrontBlogQuery;
use App\Services\Catalog\ProductCatalogQuery;

class StorefrontDetailCacheRefreshService
{
    public function __construct(
        private readonly StorefrontQueryCache $cache,
        private readonly StorefrontCacheStoreResolver $stores,
        private readonly StorefrontCacheRebuildService $rebuilds,
        private readonly ProductCatalogQuery $products,
        private readonly StorefrontBlogQuery $blog,
        private readonly StorefrontCacheObservability $observability,
    ) {}

    public function requestProduct(int $productId, ?string $oldSlug, bool $wasPublic): void
    {
        $this->request(CacheRebuildDomain::Products, $productId, $oldSlug, $wasPublic);
    }

    public function requestPost(int $postId, ?string $oldSlug, bool $wasPublic): void
    {
        $this->request(CacheRebuildDomain::Blog, $postId, $oldSlug, $wasPublic);
    }

    public function refreshProduct(int $productId): void
    {
        $this->releaseDispatchMarker(CacheRebuildDomain::Products, $productId);
        $product = Product::withTrashed()->find($productId);

        if ($product === null || ! $this->isPublicProduct($product)) {
            return;
        }

        $this->refresh(CacheRebuildDomain::Products, $productId, $product->slug, fn (): ?Product => $this->products->findPublicBySlugUncached($product->slug));
    }

    public function refreshPost(int $postId): void
    {
        $this->releaseDispatchMarker(CacheRebuildDomain::Blog, $postId);
        $post = Post::withTrashed()->find($postId);

        if ($post === null || ! $this->isPublicPost($post)) {
            return;
        }

        $this->refresh(CacheRebuildDomain::Blog, $postId, $post->slug, fn (): Post => $this->blog->findPublishedUncached($post->slug));
    }

    private function request(CacheRebuildDomain $domain, int $entityId, ?string $oldSlug, bool $wasPublic): void
    {
        $backend = $this->stores->name();
        $entity = $domain === CacheRebuildDomain::Products
            ? Product::withTrashed()->find($entityId)
            : Post::withTrashed()->find($entityId);

        $isPublic = $entity instanceof Product
            ? $this->isPublicProduct($entity)
            : ($entity instanceof Post && $this->isPublicPost($entity));

        if ($wasPublic && ($entity === null || ! $isPublic || $oldSlug !== $entity->slug)) {
            $this->cache->invalidateDetail($domain, (string) $oldSlug, $backend);
            $this->observability->event('detail_old_slug_invalidated', $domain, 'detail', ['entity_id' => $entityId]);
        }

        if (! $isPublic) {
            if ($oldSlug !== null) {
                $this->cache->invalidateDetail($domain, $oldSlug, $backend);
            }
            $this->observability->event('detail_unpublished_invalidated', $domain, 'detail', ['entity_id' => $entityId]);

            return;
        }

        $this->cache->markStale($domain, 'detail', ['slug' => $entity->slug], backend: $backend);
        $this->dispatch($domain, $entityId);
    }

    private function refresh(CacheRebuildDomain $domain, int $entityId, string $slug, callable $builder): void
    {
        $backend = $this->stores->name();

        if ($this->rebuilds->hasActiveManualRebuild($domain, $backend)) {
            $this->observability->event('detail_refresh_suppressed', $domain, 'detail', ['entity_id' => $entityId, 'reason' => 'manual_rebuild']);

            return;
        }

        if ($this->cache->refreshDetail($domain, $slug, $builder, $backend)) {
            $this->observability->event('detail_refresh_succeeded', $domain, 'detail', ['entity_id' => $entityId]);

            return;
        }

        $this->observability->event('detail_refresh_suppressed', $domain, 'detail', ['entity_id' => $entityId, 'reason' => 'lock_or_generation']);
    }

    private function dispatch(CacheRebuildDomain $domain, int $entityId): void
    {
        $backend = $this->stores->name();
        $store = $this->stores->store($backend);
        $marker = "storefront:detail-refresh-dispatched:{$domain->value}:{$entityId}";

        if (! $store->add($marker, true, now()->addSeconds(60))) {
            $this->observability->event('detail_refresh_deduplicated', $domain, 'detail', ['entity_id' => $entityId]);

            return;
        }

        RefreshStorefrontDetailAfterWrite::dispatch($domain, $entityId)->onQueue('cache-refresh');
        $this->observability->event('detail_refresh_requested', $domain, 'detail', ['entity_id' => $entityId]);
    }

    private function releaseDispatchMarker(CacheRebuildDomain $domain, int $entityId): void
    {
        $this->stores->store($this->stores->name())
            ->forget("storefront:detail-refresh-dispatched:{$domain->value}:{$entityId}");
    }

    private function isPublicProduct(Product $product): bool
    {
        return ! $product->trashed() && $product->status === 'published';
    }

    private function isPublicPost(Post $post): bool
    {
        return ! $post->trashed() && $post->published()->whereKey($post->getKey())->exists();
    }
}
