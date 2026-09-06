<?php

namespace App\Services\Storefront;

use App\Enums\CacheRebuildDomain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StorefrontCacheObservability
{
    public function event(string $event, CacheRebuildDomain $domain, string $scope, array $context = []): void
    {
        Log::info('storefront_cache.'.$event, ['domain' => $domain->value, 'scope' => $scope, ...$context]);

        if (! in_array($event, ['builder_succeeded', 'builder_failed', 'hard_miss_timeout', 'previous_generation_fallback', 'refresh_ahead_dispatched', 'refresh_ahead_succeeded', 'refresh_ahead_failed', 'detail_refresh_requested', 'detail_refresh_succeeded', 'detail_refresh_deduplicated', 'detail_old_slug_invalidated', 'detail_unpublished_invalidated'], true)) {
            return;
        }

        Cache::store('database')->put('storefront:cache:observability:'.$event, [
            'event' => $event,
            'domain' => $domain->value,
            'scope' => $scope,
            'context' => $context,
            'at' => now()->toIso8601String(),
        ], now()->addDays(7));
    }

    public function summary(): array
    {
        return collect(['builder_succeeded', 'builder_failed', 'hard_miss_timeout', 'previous_generation_fallback', 'refresh_ahead_dispatched', 'refresh_ahead_succeeded', 'refresh_ahead_failed', 'detail_refresh_requested', 'detail_refresh_succeeded', 'detail_refresh_deduplicated', 'detail_old_slug_invalidated', 'detail_unpublished_invalidated'])
            ->mapWithKeys(fn (string $event): array => [$event => Cache::store('database')->get('storefront:cache:observability:'.$event)])
            ->all();
    }
}
