<?php

use App\Enums\CacheRebuildDomain;
use App\Enums\CacheRebuildStatus;
use App\Jobs\Storefront\PruneStorefrontCacheGenerations;
use App\Models\CacheRebuildRun;
use App\Models\StorefrontCacheGenerationEntry;
use App\Models\StorefrontCacheGenerationPrune;
use App\Services\Storefront\StorefrontCacheRebuildService;
use App\Services\Storefront\StorefrontQueryCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Cache::store('database')->flush();
});

function successfulGeneration(CacheRebuildDomain $domain, int $generation, $finishedAt): CacheRebuildRun
{
    return CacheRebuildRun::query()->create([
        'domain' => $domain,
        'requested_at' => $finishedAt,
        'status' => CacheRebuildStatus::Succeeded,
        'target_backend' => 'database',
        'target_generation' => $generation,
        'finished_at' => $finishedAt,
    ]);
}

function generationEntry(CacheRebuildDomain $domain, int $generation, string $scope): string
{
    $cache = app(StorefrontQueryCache::class);
    $key = $cache->key($domain, $generation, $scope, []);

    $cache->remember($domain, $scope, [], fn (): string => "{$domain->value}-{$generation}", $generation, 'database');

    return $key;
}

test('a successful swap retains active and previous generations while pruning older database cache rows after grace', function (): void {
    successfulGeneration(CacheRebuildDomain::Products, 1, now()->subDays(3));
    successfulGeneration(CacheRebuildDomain::Products, 2, now()->subDays(2));
    successfulGeneration(CacheRebuildDomain::Products, 3, now()->subDay());

    $firstKey = generationEntry(CacheRebuildDomain::Products, 1, 'generation-one');
    $secondKey = generationEntry(CacheRebuildDomain::Products, 2, 'generation-two');
    $thirdKey = generationEntry(CacheRebuildDomain::Products, 3, 'generation-three');
    StorefrontCacheGenerationEntry::query()->where('generation', 1)->update(['created_at' => now()->subHours(25)]);
    $before = DB::table('cache')->count();

    app(StorefrontCacheRebuildService::class)->prune('database');

    expect(app(StorefrontCacheRebuildService::class)->retainedGenerations(CacheRebuildDomain::Products))->toBe([3, 2])
        ->and(Cache::store('database')->has($firstKey))->toBeFalse()
        ->and(Cache::store('database')->has($secondKey))->toBeTrue()
        ->and(Cache::store('database')->has($thirdKey))->toBeTrue()
        ->and(StorefrontCacheGenerationEntry::query()->where('domain', CacheRebuildDomain::Products)->count())->toBe(2)
        ->and(DB::table('cache')->count())->toBeLessThan($before);
});

test('previous and grace-period generations remain available and product and blog namespaces stay isolated', function (): void {
    successfulGeneration(CacheRebuildDomain::Products, 1, now()->subDays(3));
    successfulGeneration(CacheRebuildDomain::Products, 2, now()->subDays(2));
    successfulGeneration(CacheRebuildDomain::Products, 3, now()->subDay());
    successfulGeneration(CacheRebuildDomain::Blog, 1, now()->subDay());

    $productOldKey = generationEntry(CacheRebuildDomain::Products, 1, 'grace-product');
    $blogKey = generationEntry(CacheRebuildDomain::Blog, 1, 'blog-active');

    app(StorefrontCacheRebuildService::class)->prune('database');

    expect(Cache::store('database')->has($productOldKey))->toBeTrue()
        ->and(Cache::store('database')->has($blogKey))->toBeTrue()
        ->and(app(StorefrontCacheRebuildService::class)->pendingGenerationCleanup(CacheRebuildDomain::Products))->toBe(1)
        ->and(app(StorefrontCacheRebuildService::class)->pendingGenerationCleanup(CacheRebuildDomain::Blog))->toBe(0);
});

test('failed target entries are pruned without deleting the healthy active generation and duplicate jobs are idempotent', function (): void {
    successfulGeneration(CacheRebuildDomain::Blog, 4, now()->subDay());
    CacheRebuildRun::query()->create([
        'domain' => CacheRebuildDomain::Blog,
        'requested_at' => now()->subDays(2),
        'status' => CacheRebuildStatus::Failed,
        'target_backend' => 'database',
        'target_generation' => 5,
        'finished_at' => now()->subDays(2),
    ]);
    $activeKey = generationEntry(CacheRebuildDomain::Blog, 4, 'active');
    $failedKey = generationEntry(CacheRebuildDomain::Blog, 5, 'failed');
    StorefrontCacheGenerationEntry::query()->where('generation', 5)->update(['created_at' => now()->subHours(25)]);
    $service = app(StorefrontCacheRebuildService::class);

    $service->prune('database');
    $service->prune('database');

    expect(Cache::store('database')->has($activeKey))->toBeTrue()
        ->and(Cache::store('database')->has($failedKey))->toBeFalse()
        ->and(StorefrontCacheGenerationPrune::query()->where('status', 'succeeded')->count())->toBe(2)
        ->and(StorefrontCacheGenerationPrune::query()->latest('id')->first()?->deleted_entries)->toBe(0);
});

test('old terminal rebuild runs are bounded while active and non-terminal runs are never pruned', function (): void {
    $old = now()->subDays(61);
    $first = successfulGeneration(CacheRebuildDomain::Products, 1, $old);
    $second = successfulGeneration(CacheRebuildDomain::Products, 2, $old->copy()->addMinute());
    $third = successfulGeneration(CacheRebuildDomain::Products, 3, $old->copy()->addMinutes(2));
    $failed = CacheRebuildRun::query()->create([
        'domain' => CacheRebuildDomain::Products,
        'requested_at' => $old,
        'status' => CacheRebuildStatus::Failed,
        'target_backend' => 'database',
        'target_generation' => 4,
        'finished_at' => $old,
    ]);
    $pending = CacheRebuildRun::query()->create([
        'domain' => CacheRebuildDomain::Products,
        'requested_at' => $old,
        'status' => CacheRebuildStatus::Pending,
        'target_backend' => 'database',
        'target_generation' => 5,
    ]);
    $running = CacheRebuildRun::query()->create([
        'domain' => CacheRebuildDomain::Products,
        'requested_at' => $old,
        'status' => CacheRebuildStatus::Running,
        'target_backend' => 'database',
        'target_generation' => 6,
    ]);

    app(StorefrontCacheRebuildService::class)->prune('database');

    expect(CacheRebuildRun::query()->find($first->id))->toBeNull()
        ->and(CacheRebuildRun::query()->find($second->id))->not->toBeNull()
        ->and(CacheRebuildRun::query()->find($third->id))->not->toBeNull()
        ->and(CacheRebuildRun::query()->find($failed->id))->toBeNull()
        ->and(CacheRebuildRun::query()->find($pending->id))->not->toBeNull()
        ->and(CacheRebuildRun::query()->find($running->id))->not->toBeNull();
});

test('generation cleanup is queued asynchronously on the cache rebuild queue', function (): void {
    Queue::fake();

    app(StorefrontCacheRebuildService::class)->requestPrune('database');

    Queue::assertPushedOn('cache-rebuild', PruneStorefrontCacheGenerations::class, function (PruneStorefrontCacheGenerations $job): bool {
        return $job->backend === 'database';
    });
});
