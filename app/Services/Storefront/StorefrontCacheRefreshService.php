<?php

namespace App\Services\Storefront;

use App\Enums\CacheRebuildDomain;
use App\Services\Blog\StorefrontBlogQuery;
use App\Services\Catalog\ProductCatalogQuery;

class StorefrontCacheRefreshService
{
    public function __construct(
        private readonly StorefrontQueryCache $cache,
        private readonly StorefrontCacheRefreshAhead $refreshAhead,
        private readonly StorefrontCacheRebuildService $rebuilds,
        private readonly ProductCatalogQuery $products,
        private readonly StorefrontBlogQuery $blog,
        private readonly StorefrontCacheObservability $observability,
    ) {}

    public function refresh(CacheRebuildDomain $domain, string $scope, array $parameters, int $generation, string $backend): void
    {
        if ($this->cache->activeGeneration($domain, $backend) !== $generation || $this->rebuilds->hasActiveManualRebuild($domain, $backend)) {
            $this->observability->event('refresh_ahead_suppressed', $domain, $scope, ['reason' => 'generation_or_manual_rebuild']);

            return;
        }
        if (! $this->refreshAhead->isEligible($domain, $scope, $parameters)) {
            return;
        }

        $builder = match ($domain) {
            CacheRebuildDomain::Products => fn () => $this->products->paginateUncached($parameters),
            CacheRebuildDomain::Blog => fn () => $this->blog->paginateUncached(null, null, (int) $parameters['per_page'], 1),
        };

        if ($this->cache->refreshIfNeeded($domain, $scope, $parameters, $builder, $generation, $backend, $this->refreshAhead->windowSeconds($domain))) {
            $this->observability->event('refresh_ahead_succeeded', $domain, $scope, ['generation' => $generation]);
        }
    }
}
