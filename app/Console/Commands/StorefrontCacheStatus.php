<?php

namespace App\Console\Commands;

use App\Enums\CacheRebuildDomain;
use App\Models\CacheRebuildRun;
use App\Models\StorefrontCacheGenerationPrune;
use App\Services\Settings\SettingsService;
use App\Services\Storefront\StorefrontCacheObservability;
use App\Services\Storefront\StorefrontCacheRebuildService;
use App\Services\Storefront\StorefrontCacheStoreResolver;
use App\Services\Storefront\StorefrontQueryCache;
use Illuminate\Console\Command;
use Laravel\Horizon\Horizon;

class StorefrontCacheStatus extends Command
{
    protected $signature = 'cache:storefront-status';

    protected $description = 'Display storefront cache, queue, and Horizon readiness without secrets.';

    public function handle(StorefrontCacheStoreResolver $stores, StorefrontQueryCache $cache, StorefrontCacheRebuildService $rebuilds, SettingsService $settings, StorefrontCacheObservability $observability): int
    {
        $this->table(['Field', 'Value'], [
            ['Selected backend', $stores->name()],
            ['Backend operational', $stores->selectedStoreReady() ? 'yes' : 'no'],
            ['Redis readiness', $stores->redisReady() ? 'yes' : 'no'],
            ['Products generation', (string) $cache->activeGeneration(CacheRebuildDomain::Products)],
            ['Blog generation', (string) $cache->activeGeneration(CacheRebuildDomain::Blog)],
            ['Queue connection', (string) config('queue.default')],
            ['Horizon available', $this->horizonAvailable() ? 'yes' : 'no'],
            ['Refresh ahead', $settings->get('cache.refresh_ahead.enabled') ? 'enabled' : 'disabled'],
            ['Refresh window', $settings->get('cache.refresh_ahead_percent').'% of fresh TTL; canonical archive page 1 only'],
        ]);

        foreach (CacheRebuildDomain::cases() as $domain) {
            $run = CacheRebuildRun::query()->where('domain', $domain)->latest('id')->first();
            $retained = implode(', ', $rebuilds->retainedGenerations($domain));
            $pending = $rebuilds->pendingGenerationCleanup($domain);

            $this->line("{$domain->value} retained generations: {$retained}");
            $this->line("{$domain->value} old generations pending cleanup: {$pending}");
            $this->line("Last {$domain->value} rebuild: ".($run ? "{$run->status->value} (#{$run->id})" : 'none'));
        }

        $lastPrune = StorefrontCacheGenerationPrune::query()->latest('id')->first();
        $this->line('Last generation prune: '.($lastPrune ? "{$lastPrune->status} (#{$lastPrune->id})" : 'none'));
        $this->line('Recent cache observations: '.json_encode(array_filter($observability->summary()), JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function horizonAvailable(): bool
    {
        return class_exists(Horizon::class) && config('queue.default') === 'redis';
    }
}
