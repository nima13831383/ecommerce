<?php

namespace App\Services\Storefront;

use App\Enums\CacheRebuildDomain;
use App\Enums\CacheRebuildStatus;
use App\Jobs\Storefront\PruneStorefrontCacheGenerations;
use App\Jobs\Storefront\RebuildStorefrontCache;
use App\Models\CacheRebuildRun;
use App\Models\Post;
use App\Models\Product;
use App\Models\StorefrontCacheGenerationEntry;
use App\Models\StorefrontCacheGenerationPrune;
use App\Services\Blog\StorefrontBlogQuery;
use App\Services\Catalog\ProductCatalogQuery;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class StorefrontCacheRebuildService
{
    private const GenerationGraceHours = 24;

    private const RebuildRunRetentionDays = 60;

    public function __construct(
        private readonly StorefrontQueryCache $cache,
        private readonly StorefrontCacheStoreResolver $stores,
        private readonly ProductCatalogQuery $products,
        private readonly StorefrontBlogQuery $blog,
        private readonly SettingsService $settings,
    ) {}

    public function request(CacheRebuildDomain $domain, ?int $requestedBy = null): CacheRebuildRun
    {
        $backend = $this->stores->name();
        $lock = Cache::store('database')->lock("storefront:rebuild-request:{$backend}:{$domain->value}", 30);

        $run = $lock->block(5, function () use ($backend, $domain, $requestedBy): CacheRebuildRun {
            return DB::transaction(function () use ($backend, $domain, $requestedBy): CacheRebuildRun {
                $existing = CacheRebuildRun::query()
                    ->where('domain', $domain->value)
                    ->where('target_backend', $backend)
                    ->whereIn('status', [CacheRebuildStatus::Pending->value, CacheRebuildStatus::Queued->value, CacheRebuildStatus::Running->value])
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                return CacheRebuildRun::query()->create([
                    'domain' => $domain,
                    'requested_by' => $requestedBy,
                    'requested_at' => now(),
                    'status' => CacheRebuildStatus::Pending,
                    'target_backend' => $backend,
                    'target_generation' => $this->cache->nextGeneration($domain, $backend),
                ]);
            });
        });

        if ($run->status === CacheRebuildStatus::Pending) {
            $run->update(['status' => CacheRebuildStatus::Queued]);
            RebuildStorefrontCache::dispatch($run->id)->onQueue('cache-rebuild');
        }

        return $run->fresh();
    }

    public function rebuild(CacheRebuildRun $run): void
    {
        $claimed = CacheRebuildRun::query()
            ->whereKey($run->id)
            ->whereIn('status', [CacheRebuildStatus::Pending->value, CacheRebuildStatus::Queued->value])
            ->update([
                'status' => CacheRebuildStatus::Running,
                'started_at' => now(),
                'error_summary' => null,
            ]);

        if ($claimed === 0) {
            return;
        }

        $run->refresh();

        try {
            match ($run->domain) {
                CacheRebuildDomain::Products => $this->warmProducts($run->target_generation, $run->target_backend),
                CacheRebuildDomain::Blog => $this->warmBlog($run->target_generation, $run->target_backend),
            };

            $run->update([
                'status' => CacheRebuildStatus::Succeeded,
                'finished_at' => now(),
            ]);

            $this->requestPrune($run->target_backend);
        } catch (Throwable $exception) {
            $run->update([
                'status' => CacheRebuildStatus::Failed,
                'finished_at' => now(),
                'error_summary' => str($exception->getMessage())->limit(1000),
            ]);

            throw $exception;
        }
    }

    public function recoverQueuedRuns(): void
    {
        CacheRebuildRun::query()
            ->whereIn('status', [CacheRebuildStatus::Pending->value, CacheRebuildStatus::Queued->value])
            ->orderBy('id')
            ->each(function (CacheRebuildRun $run): void {
                $run->update(['status' => CacheRebuildStatus::Queued]);
                RebuildStorefrontCache::dispatch($run->id)->onQueue('cache-rebuild');
            });
    }

    public function requestPrune(?string $backend = null): void
    {
        PruneStorefrontCacheGenerations::dispatch($backend ?? $this->stores->name())
            ->onQueue('cache-rebuild');
    }

    public function prune(string $backend): void
    {
        $lock = Cache::store('database')->lock("storefront:cache-prune:{$backend}", 60);

        if (! $lock->get()) {
            return;
        }

        $prune = StorefrontCacheGenerationPrune::query()->create([
            'backend' => $backend,
            'started_at' => now(),
            'status' => 'running',
        ]);

        try {
            $deletedEntries = $this->pruneGenerationEntries($backend);
            $deletedRuns = $this->pruneTerminalRuns($backend);

            $prune->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'deleted_entries' => $deletedEntries,
                'deleted_runs' => $deletedRuns,
            ]);
        } catch (Throwable $exception) {
            $prune->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_summary' => str($exception->getMessage())->limit(1000),
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /** @return array<int, int> */
    public function retainedGenerations(CacheRebuildDomain $domain, ?string $backend = null): array
    {
        return $this->cache->retainedGenerations($domain, $backend);
    }

    public function pendingGenerationCleanup(CacheRebuildDomain $domain, ?string $backend = null): int
    {
        $backend ??= $this->stores->name();

        return StorefrontCacheGenerationEntry::query()
            ->where('domain', $domain->value)
            ->where('backend', $backend)
            ->whereNotIn('generation', $this->retainedGenerations($domain, $backend))
            ->count();
    }

    public function hasActiveManualRebuild(CacheRebuildDomain $domain, ?string $backend = null): bool
    {
        return CacheRebuildRun::query()
            ->where('domain', $domain->value)
            ->where('target_backend', $backend ?? $this->stores->name())
            ->whereIn('status', [CacheRebuildStatus::Pending->value, CacheRebuildStatus::Queued->value, CacheRebuildStatus::Running->value])
            ->exists();
    }

    private function pruneGenerationEntries(string $backend): int
    {
        $deleted = 0;
        $store = $this->stores->store($backend);
        $graceThreshold = now()->subHours(self::GenerationGraceHours);

        foreach (CacheRebuildDomain::cases() as $domain) {
            $retained = $this->retainedGenerations($domain, $backend);

            StorefrontCacheGenerationEntry::query()
                ->where('domain', $domain->value)
                ->where('backend', $backend)
                ->whereNotIn('generation', $retained)
                ->where('created_at', '<=', $graceThreshold)
                ->orderBy('id')
                ->chunkById(100, function ($entries) use ($store, &$deleted): void {
                    foreach ($entries as $entry) {
                        $store->forget($entry->cache_key);
                        $entry->delete();
                        $deleted++;
                    }
                });
        }

        return $deleted;
    }

    private function pruneTerminalRuns(string $backend): int
    {
        $retainedRunIds = [];

        foreach (CacheRebuildDomain::cases() as $domain) {
            $retainedRunIds = array_merge($retainedRunIds, CacheRebuildRun::query()
                ->where('domain', $domain->value)
                ->where('target_backend', $backend)
                ->where('status', CacheRebuildStatus::Succeeded->value)
                ->latest('finished_at')
                ->limit(2)
                ->pluck('id')
                ->all());
        }

        return CacheRebuildRun::query()
            ->where('target_backend', $backend)
            ->whereIn('status', [CacheRebuildStatus::Succeeded->value, CacheRebuildStatus::Failed->value])
            ->where('finished_at', '<=', now()->subDays(self::RebuildRunRetentionDays))
            ->when($retainedRunIds !== [], fn ($query) => $query->whereNotIn('id', $retainedRunIds))
            ->delete();
    }

    private function warmProducts(int $generation, string $backend): void
    {
        $perPage = (int) $this->settings->get('catalog.products_per_page');
        $total = Product::query()->where('status', 'published')->count();

        foreach (range(1, max(1, (int) ceil($total / $perPage))) as $page) {
            $this->cache->remember(
                CacheRebuildDomain::Products,
                'archive',
                ['per_page' => $perPage, 'page' => $page, 'sort' => 'newest'],
                fn () => $this->products->paginateUncached(['per_page' => $perPage, 'page' => $page, 'sort' => 'newest']),
                $generation,
                $backend,
            );
        }

        Product::query()->where('status', 'published')->select(['id', 'slug'])->orderBy('id')->chunkById(100, function ($products) use ($backend, $generation): void {
            foreach ($products as $product) {
                $this->cache->remember(
                    CacheRebuildDomain::Products,
                    'detail',
                    ['slug' => $product->slug],
                    fn () => $this->products->findPublicBySlugUncached($product->slug),
                    $generation,
                    $backend,
                );
            }
        });
    }

    private function warmBlog(int $generation, string $backend): void
    {
        $perPage = (int) $this->settings->get('blog.posts_per_page');
        $total = Post::query()->published()->count();

        foreach (range(1, max(1, (int) ceil($total / $perPage))) as $page) {
            $this->cache->remember(
                CacheRebuildDomain::Blog,
                'archive',
                ['category' => null, 'search' => null, 'per_page' => $perPage, 'page' => $page],
                fn () => $this->blog->paginateUncached(null, null, $perPage, $page),
                $generation,
                $backend,
            );
        }

        Post::query()->published()->select(['id', 'slug'])->orderBy('id')->chunkById(100, function ($posts) use ($backend, $generation): void {
            foreach ($posts as $post) {
                $this->cache->remember(
                    CacheRebuildDomain::Blog,
                    'detail',
                    ['slug' => $post->slug],
                    fn () => $this->blog->findPublishedUncached($post->slug),
                    $generation,
                    $backend,
                );
            }
        });
    }
}
