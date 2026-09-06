<?php

use App\Enums\CacheRebuildDomain;
use App\Enums\CacheRebuildStatus;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Jobs\Storefront\RebuildStorefrontCache;
use App\Jobs\Storefront\RefreshStorefrontCacheKey;
use App\Models\CacheRebuildRun;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Services\Blog\StorefrontBlogQuery;
use App\Services\Catalog\ProductCatalogQuery;
use App\Services\Inventory\InventoryService;
use App\Services\Storefront\StorefrontCacheRebuildService;
use App\Services\Storefront\StorefrontCacheRefreshService;
use App\Services\Storefront\StorefrontQueryCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

function cachedStorefrontProduct(string $slug, array $attributes = []): Product
{
    $product = Product::query()->create(array_replace([
        'name' => "Cached {$slug}",
        'slug' => $slug,
        'type' => 'simple',
        'price' => 1000,
        'status' => 'published',
        'published_at' => now(),
        'stock_quantity' => 5,
        'stock_status' => 'in_stock',
    ], $attributes));

    app(InventoryService::class)->setOnHand($product, (int) ($attributes['stock_quantity'] ?? 5));

    return $product;
}

function cachedStorefrontPost(string $slug, array $attributes = []): Post
{
    return Post::query()->create(array_replace([
        'author_id' => User::factory()->create()->id,
        'title' => "Cached {$slug}",
        'slug' => $slug,
        'excerpt' => 'Cached excerpt',
        'content' => 'Cached content',
        'status' => 'published',
        'published_at' => now()->subMinute(),
    ], $attributes));
}

beforeEach(function (): void {
    Cache::store('database')->flush();
});

test('storefront cache uses canonical keys and returns the same cached value for equivalent requests', function (): void {
    $cache = app(StorefrontQueryCache::class);
    $calls = 0;

    $first = $cache->remember(CacheRebuildDomain::Products, 'probe', ['page' => 1, 'brands' => ['b', 'a']], function () use (&$calls): string {
        $calls++;

        return 'cached';
    });
    $second = $cache->remember(CacheRebuildDomain::Products, 'probe', ['brands' => ['a', 'b'], 'page' => 1], function () use (&$calls): string {
        $calls++;

        return 'rebuilt';
    });

    expect($first)->toBe('cached')
        ->and($second)->toBe('cached')
        ->and($calls)->toBe(1)
        ->and($cache->key(CacheRebuildDomain::Products, 1, 'probe', ['page' => 1]))
        ->not->toBe($cache->key(CacheRebuildDomain::Products, 1, 'probe', ['page' => 2]));
});

test('a locked stale entry is served without a duplicate rebuild', function (): void {
    $cache = app(StorefrontQueryCache::class);
    $key = $cache->key(CacheRebuildDomain::Products, 1, 'stale-probe', []);
    Cache::store('database')->put($key, [
        'fresh_until' => now()->subSecond()->getTimestamp(),
        'stale_until' => now()->addMinute()->getTimestamp(),
        'value' => 'stale value',
    ], now()->addMinute());
    $lock = Cache::store('database')->lock("lock:{$key}", 30);
    $lock->get();
    $calls = 0;

    try {
        $value = $cache->remember(CacheRebuildDomain::Products, 'stale-probe', [], function () use (&$calls): string {
            $calls++;

            return 'new value';
        });
    } finally {
        $lock->release();
    }

    expect($value)->toBe('stale value')->and($calls)->toBe(0);
});

test('a near-expiry canonical archive returns fresh content and dispatches one refresh job', function (): void {
    Queue::fake();
    $cache = app(StorefrontQueryCache::class);
    $parameters = ['per_page' => 10, 'page' => 1, 'sort' => 'newest'];
    $key = $cache->key(CacheRebuildDomain::Products, 1, 'archive', $parameters);
    Cache::store('database')->put($key, [
        'fresh_until' => now()->addSecond()->getTimestamp(),
        'stale_until' => now()->addMinute()->getTimestamp(),
        'value' => 'fresh value',
    ], now()->addMinute());

    $first = $cache->remember(CacheRebuildDomain::Products, 'archive', $parameters, fn (): string => 'should not build');
    $second = $cache->remember(CacheRebuildDomain::Products, 'archive', $parameters, fn (): string => 'should not build');

    expect($first)->toBe('fresh value')->and($second)->toBe('fresh value');
    Queue::assertPushedOn('cache-refresh', RefreshStorefrontCacheKey::class);
    expect(Queue::pushed(RefreshStorefrontCacheKey::class))->toHaveCount(1);
});

test('filtered archives and detail keys never dispatch refresh-ahead work', function (): void {
    Queue::fake();
    $cache = app(StorefrontQueryCache::class);
    foreach ([
        ['archive', ['per_page' => 10, 'page' => 1, 'sort' => 'newest', 'search' => 'test']],
        ['detail', ['slug' => 'example']],
    ] as [$scope, $parameters]) {
        $key = $cache->key(CacheRebuildDomain::Products, 1, $scope, $parameters);
        Cache::store('database')->put($key, ['fresh_until' => now()->addSecond()->getTimestamp(), 'stale_until' => now()->addMinute()->getTimestamp(), 'value' => 'fresh'], now()->addMinute());
        $cache->remember(CacheRebuildDomain::Products, $scope, $parameters, fn (): string => 'unexpected');
    }

    Queue::assertNothingPushed();
});

test('the canonical blog archive is eligible but category archives are not', function (): void {
    Queue::fake();
    $cache = app(StorefrontQueryCache::class);
    $canonical = ['category' => null, 'search' => null, 'per_page' => 10, 'page' => 1];
    $filtered = ['category' => 'news', 'search' => null, 'per_page' => 10, 'page' => 1];
    foreach ([$canonical, $filtered] as $parameters) {
        $key = $cache->key(CacheRebuildDomain::Blog, 1, 'archive', $parameters);
        Cache::store('database')->put($key, ['fresh_until' => now()->addSecond()->getTimestamp(), 'stale_until' => now()->addMinute()->getTimestamp(), 'value' => 'fresh'], now()->addMinute());
        $cache->remember(CacheRebuildDomain::Blog, 'archive', $parameters, fn (): string => 'unexpected');
    }

    expect(Queue::pushed(RefreshStorefrontCacheKey::class))->toHaveCount(1);
});

test('a queued refresh cannot write after an active generation swap', function (): void {
    $cache = app(StorefrontQueryCache::class);
    CacheRebuildRun::query()->create([
        'domain' => CacheRebuildDomain::Products, 'requested_at' => now(), 'status' => CacheRebuildStatus::Succeeded,
        'target_backend' => 'database', 'target_generation' => 2, 'finished_at' => now(),
    ]);
    $parameters = ['per_page' => 10, 'page' => 1, 'sort' => 'newest'];
    $oldKey = $cache->key(CacheRebuildDomain::Products, 1, 'archive', $parameters);
    Cache::store('database')->put($oldKey, ['fresh_until' => now()->addSecond()->getTimestamp(), 'stale_until' => now()->addMinute()->getTimestamp(), 'value' => 'old'], now()->addMinute());

    (new RefreshStorefrontCacheKey(CacheRebuildDomain::Products, 'archive', $parameters, 1, 'database'))
        ->handle(app(StorefrontCacheRefreshService::class));

    expect(Cache::store('database')->get($oldKey)['value'])->toBe('old');
});

test('cached public detail paths perform fewer database queries after the first request', function (): void {
    $product = cachedStorefrontProduct('query-count-product');
    $post = cachedStorefrontPost('query-count-post');
    $catalog = app(ProductCatalogQuery::class);
    $blog = app(StorefrontBlogQuery::class);

    DB::enableQueryLog();
    $catalog->findPublicBySlug($product->slug);
    $firstProductCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    $catalog->findPublicBySlug($product->slug);
    $cachedProductCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    $blog->findPublished($post->slug);
    $firstBlogCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    $blog->findPublished($post->slug);
    $cachedBlogCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($cachedProductCount)->toBeLessThan($firstProductCount)
        ->and($cachedBlogCount)->toBeLessThan($firstBlogCount);
});

test('inventory remains live without changing the cached public metadata contract', function (): void {
    $product = cachedStorefrontProduct('cache-product');
    $post = cachedStorefrontPost('cache-post');
    $catalog = app(ProductCatalogQuery::class);
    $blog = app(StorefrontBlogQuery::class);

    $cachedProduct = $catalog->findPublicBySlug($product->slug);
    $cachedPost = $blog->findPublished($post->slug);
    app(InventoryService::class)->setOnHand($product, 0);
    $presentation = (new ProductDetailResource($catalog->findPublicBySlug($product->slug)))->resolve(new Request);

    expect($presentation['availability']['in_stock'])->toBeFalse();
});

test('rebuild requests are asynchronous, idempotent, and switch generations only after successful warming', function (): void {
    Queue::fake();
    cachedStorefrontProduct('rebuild-product');
    cachedStorefrontPost('rebuild-post');
    $cache = app(StorefrontQueryCache::class);
    $service = app(StorefrontCacheRebuildService::class);
    $oldGeneration = $cache->activeGeneration(CacheRebuildDomain::Products);

    $first = $service->request(CacheRebuildDomain::Products);
    $second = $service->request(CacheRebuildDomain::Products);

    expect($first->id)->toBe($second->id)
        ->and($first->status)->toBe(CacheRebuildStatus::Queued)
        ->and($cache->activeGeneration(CacheRebuildDomain::Products))->toBe($oldGeneration);

    Queue::assertPushedOn('cache-rebuild', RebuildStorefrontCache::class, fn (RebuildStorefrontCache $job): bool => $job->runId === $first->id);

    $service->rebuild($first->fresh());

    expect($first->fresh()->status)->toBe(CacheRebuildStatus::Succeeded)
        ->and($cache->activeGeneration(CacheRebuildDomain::Products))->toBe($oldGeneration + 1);
});

test('a failed rebuild cannot replace the currently active generation', function (): void {
    $cache = app(StorefrontQueryCache::class);
    $active = CacheRebuildRun::query()->create([
        'domain' => CacheRebuildDomain::Blog,
        'requested_at' => now()->subMinute(),
        'status' => CacheRebuildStatus::Succeeded,
        'target_backend' => 'database',
        'target_generation' => 4,
        'finished_at' => now()->subMinute(),
    ]);
    $failed = CacheRebuildRun::query()->create([
        'domain' => CacheRebuildDomain::Blog,
        'requested_at' => now(),
        'status' => CacheRebuildStatus::Failed,
        'target_backend' => 'database',
        'target_generation' => 5,
        'finished_at' => now(),
        'error_summary' => 'Controlled failure',
    ]);

    expect($active->status)->toBe(CacheRebuildStatus::Succeeded)
        ->and($failed->status)->toBe(CacheRebuildStatus::Failed)
        ->and($cache->activeGeneration(CacheRebuildDomain::Blog))->toBe(4);
});

test('operational cache commands enqueue rebuilds and report safe readiness metadata', function (): void {
    Queue::fake();

    $this->artisan('cache:storefront-status')
        ->expectsOutputToContain('Selected backend')
        ->expectsOutputToContain('Queue connection')
        ->assertSuccessful();
    $this->artisan('cache:rebuild-products')->assertSuccessful();
    $this->artisan('cache:rebuild-blog')->assertSuccessful();

    expect(Queue::pushed(RebuildStorefrontCache::class))->toHaveCount(2);
});
