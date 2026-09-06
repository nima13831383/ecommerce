<?php

namespace App\Services\Storefront;

use App\Enums\CacheRebuildDomain;
use App\Enums\CacheRebuildStatus;
use App\Exceptions\StorefrontCacheUnavailableException;
use App\Models\CacheRebuildRun;
use App\Models\StorefrontCacheGenerationEntry;
use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Contracts\Cache\Lock;

class StorefrontQueryCache
{
    public function __construct(
        private readonly StorefrontCacheStoreResolver $stores,
        private readonly SettingsService $settings,
        private readonly StorefrontCacheObservability $observability,
        private readonly StorefrontCacheRefreshAhead $refreshAhead,
    ) {}

    public function remember(
        CacheRebuildDomain $domain,
        string $scope,
        array $parameters,
        Closure $builder,
        ?int $generation = null,
        ?string $backend = null,
    ): mixed {
        $backend ??= $this->stores->name();
        $generation ??= $this->activeGeneration($domain, $backend);
        $key = $this->key($domain, $generation, $scope, $parameters);
        $store = $this->stores->store($backend);
        $item = $store->get($key);

        if ($this->isFresh($item) && ! $this->isRefreshNeeded($item)) {
            $this->observability->event('fresh_hit', $domain, $scope);
            $this->refreshAhead->maybeDispatch($domain, $scope, $parameters, $generation, $backend, $key, $item);

            return $item['value'];
        }

        $lock = $store->lock("lock:{$key}", $this->lockSeconds());

        if (! $lock->get()) {
            $this->observability->event('lock_contended', $domain, $scope);
            if ($this->isStale($item)) {
                $this->observability->event('stale_hit', $domain, $scope);

                return $item['value'];
            }

            $this->observability->event('hard_miss', $domain, $scope);

            return $this->waitForHardMiss($store, $key, $domain, $scope, $parameters, $generation, $backend);
        }

        try {
            $this->observability->event('lock_acquired', $domain, $scope);
            $rechecked = $store->get($key);
            if ($this->isFresh($rechecked) || ($this->isStale($rechecked) && ! $this->isRefreshNeeded($rechecked))) {
                return $rechecked['value'];
            }

            $this->observability->event('hard_miss', $domain, $scope);

            return $this->rebuild($store, $lock, $key, $builder, $domain, $scope, $parameters, $generation, $backend);
        } finally {
            $lock->release();
        }
    }

    public function refreshIfNeeded(CacheRebuildDomain $domain, string $scope, array $parameters, Closure $builder, int $generation, string $backend, int $windowSeconds): bool
    {
        if ($this->activeGeneration($domain, $backend) !== $generation) {
            return false;
        }
        $key = $this->key($domain, $generation, $scope, $parameters);
        $store = $this->stores->store($backend);
        $item = $store->get($key);
        if (! $this->isFresh($item) || (($item['fresh_until'] ?? 0) - now()->getTimestamp()) > $windowSeconds) {
            return false;
        }
        $lock = $store->lock("lock:{$key}", $this->lockSeconds());
        if (! $lock->get()) {
            return false;
        }
        try {
            $item = $store->get($key);
            if ($this->activeGeneration($domain, $backend) !== $generation || ! $this->isFresh($item) || (($item['fresh_until'] ?? 0) - now()->getTimestamp()) > $windowSeconds) {
                return false;
            }
            $this->rebuild($store, $lock, $key, $builder, $domain, $scope, $parameters, $generation, $backend);

            return true;
        } finally {
            $lock->release();
        }
    }

    public function markStale(CacheRebuildDomain $domain, string $scope, array $parameters, ?int $generation = null, ?string $backend = null): bool
    {
        $backend ??= $this->stores->name();
        $generation ??= $this->activeGeneration($domain, $backend);
        $key = $this->key($domain, $generation, $scope, $parameters);
        $store = $this->stores->store($backend);
        $item = $store->get($key);

        if (! $this->isStale($item)) {
            return false;
        }

        $item['fresh_until'] = now()->subSecond()->getTimestamp();
        $item['refresh_needed'] = true;
        $store->put($key, $item, now()->setTimestamp((int) $item['stale_until']));

        return true;
    }

    public function invalidateDetail(CacheRebuildDomain $domain, string $slug, ?string $backend = null): void
    {
        $backend ??= $this->stores->name();
        $store = $this->stores->store($backend);

        foreach ($this->retainedGenerations($domain, $backend) as $generation) {
            $key = $this->key($domain, $generation, 'detail', ['slug' => $slug]);
            $store->forget($key);
            StorefrontCacheGenerationEntry::query()
                ->where('backend', $backend)
                ->where('cache_key', $key)
                ->delete();
        }
    }

    public function refreshDetail(CacheRebuildDomain $domain, string $slug, Closure $builder, ?string $backend = null): bool
    {
        $backend ??= $this->stores->name();
        $generation = $this->activeGeneration($domain, $backend);
        $key = $this->key($domain, $generation, 'detail', ['slug' => $slug]);
        $store = $this->stores->store($backend);
        $lock = $store->lock("lock:{$key}", $this->lockSeconds());

        if (! $lock->get()) {
            return false;
        }

        try {
            if ($this->activeGeneration($domain, $backend) !== $generation) {
                return false;
            }

            $value = $builder();

            if (! $lock->isOwnedByCurrentProcess() || $this->activeGeneration($domain, $backend) !== $generation) {
                return false;
            }

            $this->put($store, $key, $value, $domain, $generation, $backend);

            return true;
        } finally {
            $lock->release();
        }
    }

    public function activeGeneration(CacheRebuildDomain $domain, ?string $backend = null): int
    {
        $backend ??= $this->stores->name();

        return (int) (CacheRebuildRun::query()
            ->where('domain', $domain->value)
            ->where('target_backend', $backend)
            ->where('status', CacheRebuildStatus::Succeeded->value)
            ->latest('finished_at')
            ->value('target_generation') ?? 1);
    }

    public function nextGeneration(CacheRebuildDomain $domain, ?string $backend = null): int
    {
        return $this->activeGeneration($domain, $backend) + 1;
    }

    /** @return array<int, int> */
    public function retainedGenerations(CacheRebuildDomain $domain, ?string $backend = null): array
    {
        $backend ??= $this->stores->name();
        $generations = CacheRebuildRun::query()
            ->where('domain', $domain->value)
            ->where('target_backend', $backend)
            ->where('status', CacheRebuildStatus::Succeeded->value)
            ->latest('finished_at')
            ->pluck('target_generation')
            ->unique()
            ->take(2)
            ->map(static fn (mixed $generation): int => (int) $generation)
            ->values()
            ->all();

        if ($generations === []) {
            return [1];
        }

        return $generations;
    }

    public function key(CacheRebuildDomain $domain, int $generation, string $scope, array $parameters): string
    {
        return 'storefront:'.$domain->value.':g:'.$generation.':'.$scope.':'.hash('sha256', json_encode($this->normalize($parameters), JSON_THROW_ON_ERROR));
    }

    private function rebuild($store, Lock $lock, string $key, Closure $builder, CacheRebuildDomain $domain, string $scope, array $parameters, int $generation, string $backend): mixed
    {
        $started = microtime(true);
        $this->observability->event('builder_started', $domain, $scope, ['generation' => $generation]);
        try {
            $value = $builder();
        } catch (\Throwable $exception) {
            $this->observability->event('builder_failed', $domain, $scope, ['generation' => $generation, 'exception' => $exception::class]);
            throw $exception;
        }

        if (! $lock->isOwnedByCurrentProcess()) {
            $this->observability->event('builder_failed', $domain, $scope, ['generation' => $generation, 'reason' => 'lease_lost']);
            $replacement = $store->get($key);
            if ($this->isFresh($replacement) || $this->isStale($replacement)) {
                return $replacement['value'];
            }

            return $this->previousGenerationFallback($store, $domain, $scope, $parameters, $generation, $backend)
                ?? throw new StorefrontCacheUnavailableException('Storefront cache lease was lost before write.');
        }
        $this->put($store, $key, $value, $domain, $generation, $backend);

        $duration = (int) round((microtime(true) - $started) * 1000);
        $this->observability->event('builder_succeeded', $domain, $scope, ['generation' => $generation, 'duration_ms' => $duration, 'lease_seconds' => $this->lockSeconds()]);

        return $value;
    }

    private function waitForHardMiss($store, string $key, CacheRebuildDomain $domain, string $scope, array $parameters, int $generation, string $backend): mixed
    {
        $deadline = microtime(true) + 5;
        $delay = 50;
        do {
            usleep($delay * 1000);
            $item = $store->get($key);
            if ($this->isFresh($item) || $this->isStale($item)) {
                $this->observability->event('hard_miss_wait_success', $domain, $scope);

                return $item['value'];
            }
            $delay = min(500, $delay * 2);
        } while (microtime(true) < $deadline);

        if (($fallback = $this->previousGenerationFallback($store, $domain, $scope, $parameters, $generation, $backend)) !== null) {
            return $fallback;
        }

        $this->observability->event('hard_miss_timeout', $domain, $scope, ['generation' => $generation]);
        throw new StorefrontCacheUnavailableException('Storefront cache is temporarily unavailable.');
    }

    private function previousGenerationFallback($store, CacheRebuildDomain $domain, string $scope, array $parameters, int $generation, string $backend): mixed
    {
        foreach ($this->retainedGenerations($domain, $backend) as $candidate) {
            if ($candidate === $generation) {
                continue;
            }
            $item = $store->get($this->key($domain, $candidate, $scope, $parameters));
            if ($this->isFresh($item) || $this->isStale($item)) {
                $this->observability->event('previous_generation_fallback', $domain, $scope, ['generation' => $candidate]);

                return $item['value'];
            }
        }

        return null;
    }

    private function isFresh(mixed $item): bool
    {
        return is_array($item) && isset($item['fresh_until'], $item['value']) && $item['fresh_until'] >= now()->getTimestamp();
    }

    private function isStale(mixed $item): bool
    {
        return is_array($item) && isset($item['stale_until'], $item['value']) && $item['stale_until'] >= now()->getTimestamp();
    }

    private function isRefreshNeeded(mixed $item): bool
    {
        return is_array($item) && ($item['refresh_needed'] ?? false) === true;
    }

    private function put($store, string $key, mixed $value, CacheRebuildDomain $domain, int $generation, string $backend): void
    {
        $freshUntil = now()->addSeconds($this->ttlSeconds($domain));
        $staleUntil = $freshUntil->copy()->addSeconds($this->staleSeconds());

        $store->put($key, [
            'fresh_until' => $freshUntil->getTimestamp(),
            'stale_until' => $staleUntil->getTimestamp(),
            'value' => $value,
        ], $staleUntil);

        StorefrontCacheGenerationEntry::query()->upsert([
            [
                'domain' => $domain->value,
                'backend' => $backend,
                'generation' => $generation,
                'cache_key' => $key,
                'expires_at' => $staleUntil,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['backend', 'cache_key'], ['domain', 'generation', 'expires_at', 'updated_at']);
    }

    private function ttlSeconds(CacheRebuildDomain $domain): int
    {
        $key = $domain === CacheRebuildDomain::Products
            ? 'cache.products.ttl_seconds'
            : 'cache.blog.ttl_seconds';

        return max(60, (int) $this->settings->get($key));
    }

    private function staleSeconds(): int
    {
        return max(60, (int) $this->settings->get('cache.stale_seconds'));
    }

    private function lockSeconds(): int
    {
        return max(60, (int) $this->settings->get('cache.lock_seconds'));
    }

    private function normalize(array $parameters): array
    {
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $parameters[$key] = $this->normalize($value);
            }
        }

        if (array_is_list($parameters)) {
            sort($parameters);

            return $parameters;
        }

        ksort($parameters);

        return $parameters;
    }
}
