<?php

namespace App\Services\Storefront;

use App\Enums\CacheRebuildDomain;
use App\Jobs\Storefront\RefreshStorefrontCacheKey;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;

class StorefrontCacheRefreshAhead
{
    public function __construct(
        private readonly StorefrontCacheStoreResolver $stores,
        private readonly SettingsService $settings,
        private readonly StorefrontCacheObservability $observability,
    ) {}

    public function windowSeconds(CacheRebuildDomain $domain): int
    {
        $ttl = (int) $this->settings->get($domain === CacheRebuildDomain::Products ? 'cache.products.ttl_seconds' : 'cache.blog.ttl_seconds');

        return max(1, (int) floor($ttl * ((int) $this->settings->get('cache.refresh_ahead_percent') / 100)));
    }

    public function isEligible(CacheRebuildDomain $domain, string $scope, array $parameters): bool
    {
        if ($scope !== 'archive') {
            return false;
        }

        return match ($domain) {
            CacheRebuildDomain::Products => count($parameters) === 3
                && ($parameters['page'] ?? null) === 1
                && ($parameters['sort'] ?? null) === 'newest'
                && isset($parameters['per_page']),
            CacheRebuildDomain::Blog => count($parameters) === 4
                && ($parameters['page'] ?? null) === 1
                && array_key_exists('category', $parameters)
                && $parameters['category'] === null
                && array_key_exists('search', $parameters)
                && $parameters['search'] === null
                && isset($parameters['per_page']),
        };
    }

    public function maybeDispatch(CacheRebuildDomain $domain, string $scope, array $parameters, int $generation, string $backend, string $key, mixed $item): void
    {
        if (! $this->settings->get('cache.refresh_ahead.enabled') || ! $this->isEligible($domain, $scope, $parameters) || ! is_array($item)) {
            return;
        }

        if (($item['fresh_until'] ?? 0) - now()->getTimestamp() > $this->windowSeconds($domain)) {
            return;
        }

        $store = $this->stores->store($backend);
        $lock = $store->lock('lock:storefront:refresh-dispatch:'.$key, 15);
        if (! $lock->get()) {
            $this->observability->event('refresh_ahead_suppressed', $domain, $scope, ['reason' => 'dispatch_lock']);

            return;
        }

        $marker = 'storefront:refresh-dispatched:'.$key;
        try {
            if (! $store->add($marker, true, now()->addSeconds(60))) {
                $this->observability->event('refresh_ahead_suppressed', $domain, $scope, ['reason' => 'already_queued']);

                return;
            }

            RefreshStorefrontCacheKey::dispatch($domain, $scope, $parameters, $generation, $backend)->onQueue('cache-refresh');
            $this->observability->event('refresh_ahead_dispatched', $domain, $scope, ['generation' => $generation]);
        } catch (\Throwable $exception) {
            $store->forget($marker);
            Log::warning('storefront_cache.refresh_ahead_dispatch_failed', ['domain' => $domain->value, 'scope' => $scope, 'exception' => $exception::class]);
            $this->observability->event('refresh_ahead_failed', $domain, $scope, ['stage' => 'dispatch']);
        } finally {
            $lock->release();
        }
    }
}
