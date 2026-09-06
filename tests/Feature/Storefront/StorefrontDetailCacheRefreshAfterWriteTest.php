<?php

use App\Enums\CacheRebuildDomain;
use App\Enums\PostStatus;
use App\Jobs\Storefront\RefreshStorefrontDetailAfterWrite;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Services\Blog\StorefrontBlogQuery;
use App\Services\Catalog\ProductCatalogQuery;
use App\Services\Inventory\InventoryService;
use App\Services\Storefront\StorefrontDetailCacheRefreshService;
use App\Services\Storefront\StorefrontQueryCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Cache::store('database')->flush();
});

function detailRefreshProduct(string $slug): Product
{
    $product = Product::query()->create([
        'name' => "Cached {$slug}",
        'slug' => $slug,
        'type' => 'simple',
        'price' => 1000,
        'status' => 'published',
        'published_at' => now(),
        'stock_quantity' => 5,
        'stock_status' => 'in_stock',
    ]);

    app(InventoryService::class)->setOnHand($product, 5);

    return $product;
}

function detailRefreshPost(string $slug): Post
{
    return Post::query()->create([
        'author_id' => User::factory()->create()->id,
        'title' => "Cached {$slug}",
        'slug' => $slug,
        'excerpt' => 'Cached excerpt',
        'content' => 'Cached content',
        'status' => 'published',
        'published_at' => now()->subMinute(),
    ]);
}

test('a product update marks only its detail stale, preserves the archive, and refreshes the latest state', function (): void {
    $product = detailRefreshProduct('targeted-product');
    $catalog = app(ProductCatalogQuery::class);
    $cache = app(StorefrontQueryCache::class);
    $filters = ['per_page' => 24, 'page' => 1, 'sort' => 'newest'];

    $catalog->paginate($filters);
    $catalog->findPublicBySlug($product->slug);
    $archiveKey = $cache->key(CacheRebuildDomain::Products, 1, 'archive', $filters);
    $detailKey = $cache->key(CacheRebuildDomain::Products, 1, 'detail', ['slug' => $product->slug]);

    Queue::fake();
    $product->update(['name' => 'Updated targeted product']);
    $product->update(['name' => 'Latest targeted product']);

    expect(Cache::store('database')->get($detailKey)['refresh_needed'])->toBeTrue()
        ->and(Cache::store('database')->get($archiveKey)['value']->first()->name)->toBe("Cached {$product->slug}");
    expect(Queue::pushed(RefreshStorefrontDetailAfterWrite::class))->toHaveCount(1);

    (new RefreshStorefrontDetailAfterWrite(CacheRebuildDomain::Products, $product->id))
        ->handle(app(StorefrontDetailCacheRefreshService::class));

    expect($catalog->findPublicBySlug($product->slug)?->name)->toBe('Latest targeted product');
});

test('a post update marks only its detail stale and leaves the blog archive untouched', function (): void {
    $post = detailRefreshPost('targeted-post');
    $blog = app(StorefrontBlogQuery::class);
    $cache = app(StorefrontQueryCache::class);

    $blog->paginate();
    $blog->findPublished($post->slug);
    $archiveKey = $cache->key(CacheRebuildDomain::Blog, 1, 'archive', ['category' => null, 'search' => null, 'per_page' => 9, 'page' => 1]);
    $detailKey = $cache->key(CacheRebuildDomain::Blog, 1, 'detail', ['slug' => $post->slug]);

    Queue::fake();
    $post->update(['title' => 'Latest targeted post']);

    expect(Cache::store('database')->get($detailKey)['refresh_needed'])->toBeTrue()
        ->and(Cache::store('database')->get($archiveKey)['value']->first()->title)->toBe("Cached {$post->slug}");
    expect(Queue::pushed(RefreshStorefrontDetailAfterWrite::class))->toHaveCount(1);

    (new RefreshStorefrontDetailAfterWrite(CacheRebuildDomain::Blog, $post->id))
        ->handle(app(StorefrontDetailCacheRefreshService::class));

    expect($blog->findPublished($post->slug)->title)->toBe('Latest targeted post');
});

test('slug changes invalidate old product detail keys and warm the new public slug', function (): void {
    $product = detailRefreshProduct('old-targeted-product');
    $catalog = app(ProductCatalogQuery::class);
    $cache = app(StorefrontQueryCache::class);
    $catalog->findPublicBySlug($product->slug);
    $oldKey = $cache->key(CacheRebuildDomain::Products, 1, 'detail', ['slug' => $product->slug]);

    Queue::fake();
    $product->update(['slug' => 'new-targeted-product']);

    expect(Cache::store('database')->get($oldKey))->toBeNull();

    (new RefreshStorefrontDetailAfterWrite(CacheRebuildDomain::Products, $product->id))
        ->handle(app(StorefrontDetailCacheRefreshService::class));

    expect($catalog->findPublicBySlug('new-targeted-product')?->id)->toBe($product->id);
});

test('slug changes invalidate old article detail keys and warm the new public slug', function (): void {
    $post = detailRefreshPost('old-targeted-post');
    $blog = app(StorefrontBlogQuery::class);
    $cache = app(StorefrontQueryCache::class);
    $blog->findPublished($post->slug);
    $oldKey = $cache->key(CacheRebuildDomain::Blog, 1, 'detail', ['slug' => $post->slug]);

    Queue::fake();
    $post->update(['slug' => 'new-targeted-post']);

    expect(Cache::store('database')->get($oldKey))->toBeNull();

    (new RefreshStorefrontDetailAfterWrite(CacheRebuildDomain::Blog, $post->id))
        ->handle(app(StorefrontDetailCacheRefreshService::class));

    expect($blog->findPublished('new-targeted-post')->id)->toBe($post->id);
});

test('unpublishing invalidates public detail without rebuilding it', function (): void {
    $product = detailRefreshProduct('unpublished-targeted-product');
    $post = detailRefreshPost('unpublished-targeted-post');
    $catalog = app(ProductCatalogQuery::class);
    $blog = app(StorefrontBlogQuery::class);
    $cache = app(StorefrontQueryCache::class);
    $catalog->findPublicBySlug($product->slug);
    $blog->findPublished($post->slug);

    Queue::fake();
    $product->update(['status' => 'draft']);
    $post->update(['status' => PostStatus::Draft]);

    expect(Cache::store('database')->get($cache->key(CacheRebuildDomain::Products, 1, 'detail', ['slug' => $product->slug])))->toBeNull()
        ->and(Cache::store('database')->get($cache->key(CacheRebuildDomain::Blog, 1, 'detail', ['slug' => $post->slug])))->toBeNull();
    Queue::assertNothingPushed();
});

test('deleting cached public details invalidates them without changing their archive generation', function (): void {
    $product = detailRefreshProduct('deleted-targeted-product');
    $post = detailRefreshPost('deleted-targeted-post');
    $catalog = app(ProductCatalogQuery::class);
    $blog = app(StorefrontBlogQuery::class);
    $cache = app(StorefrontQueryCache::class);
    $catalog->findPublicBySlug($product->slug);
    $blog->findPublished($post->slug);
    $productGeneration = $cache->activeGeneration(CacheRebuildDomain::Products);
    $blogGeneration = $cache->activeGeneration(CacheRebuildDomain::Blog);

    Queue::fake();
    $product->delete();
    $post->delete();

    expect(Cache::store('database')->get($cache->key(CacheRebuildDomain::Products, $productGeneration, 'detail', ['slug' => $product->slug])))->toBeNull()
        ->and(Cache::store('database')->get($cache->key(CacheRebuildDomain::Blog, $blogGeneration, 'detail', ['slug' => $post->slug])))->toBeNull()
        ->and($cache->activeGeneration(CacheRebuildDomain::Products))->toBe($productGeneration)
        ->and($cache->activeGeneration(CacheRebuildDomain::Blog))->toBe($blogGeneration);
    Queue::assertNothingPushed();
});

test('rolled back updates and inventory-only writes do not request detail refreshes', function (): void {
    $product = detailRefreshProduct('rollback-targeted-product');
    $post = detailRefreshPost('rollback-targeted-post');
    Queue::fake();

    try {
        DB::transaction(function () use ($product, $post): void {
            $product->update(['name' => 'Rolled back']);
            $post->update(['title' => 'Rolled back']);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    app(InventoryService::class)->setOnHand($product, 0);

    Queue::assertNothingPushed();
});
