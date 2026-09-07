# Targeted Product / Article Detail Cache Refresh After Write

## 1. Previous Policy

The storefront cache intentionally left all Product and Post metadata cached until TTL expiry or a manual generation rebuild. That was correct for archives but too broad for public detail content after an edit.

## 2. New Policy

| Surface | Policy |
| --- | --- |
| Product archive/search/filter/pagination | Unchanged: TTL/SWR or explicit full rebuild. |
| Product detail | After-commit targeted stale-and-refresh for only the edited Product. |
| Blog archive/category/search/pagination | Unchanged: TTL/SWR or explicit full rebuild. |
| Article detail | After-commit targeted stale-and-refresh for only the edited Post. |

No targeted refresh creates or swaps a cache generation.

## 3. Integration Point

Product and Post model observers use Laravel after-commit delivery. They capture the previous public slug/state before persistence, then call the central `StorefrontDetailCacheRefreshService` only after a successful commit. Product media and variation presentation updates use the same coordinator; `ProductVariantService` requests an after-commit refresh after synchronization.

## 4. Detail Freshness Invalidation

`StorefrontQueryCache::markStale()` marks only the active-generation detail envelope as `refresh_needed`, removing fresh-hit eligibility while retaining the existing stale lifetime. `invalidateDetail()` removes an obsolete detail key across retained generations for slug/visibility/deletion correctness.

## 5. No-Cache-Hole Strategy

Normal public edits retain the old detail value as SWR fallback. A request that arrives before the job can acquire the existing detail lock and rebuild; lock losers receive stale data. The entry is atomically replaced after the rebuild owns the lock.

## 6. Product Refresh Job

`RefreshStorefrontDetailAfterWrite` loads the current Product by database ID, confirms current public visibility, resolves the active generation at execution time, and uses the central detail cache writer. It runs on `cache-refresh` and never carries a Product snapshot.

## 7. Article Refresh Job

The same job handles Blog domain Post IDs and re-reads the current public Post at execution time. It uses `StorefrontBlogQuery` only through the shared cache coordinator.

## 8. Deduplication

Dispatch uses an atomic, backend-neutral cache marker keyed by domain and database ID, while the job also implements `ShouldBeUniqueUntilProcessing`. Rapid commits therefore enqueue one pending effective refresh; the worker reads latest persisted state.

## 9. Latest-State-Wins

The job payload contains only `{domain, entityId}`. It reloads the entity and its current slug/content at execution, so a queued refresh rebuilds the latest committed value rather than an event snapshot.

## 10. Slug Change

The pre-commit slug is captured. After commit its old detail entries are removed from retained generations, and a public new slug is queued for warm. No slug history or redirect behavior was added.

## 11. Unpublish / Republish

Unpublishing invalidates the public detail key and does not queue a public warm. Republishing queues a targeted detail warm. Archives deliberately remain eventually consistent.

## 12. Delete / Soft Delete

Soft-deleting a Product or Post invalidates its public detail cache without touching archive keys or deletion-domain behavior.

## 13. Inventory Exception

Stock quantity/status-only Product and variation writes do not request metadata detail refreshes. Live inventory composition remains authoritative on reads.

## 14. Generation Safety

The job resolves the active generation just before lock acquisition and verifies it again before atomic write. It no-ops if a manual full rebuild is active, because that rebuild warms the entity into its own build-before-swap generation.

## 15. Manual Full Rebuild Interaction

The existing full Product/Blog rebuild buttons and `cache-rebuild` jobs are unchanged. A targeted detail refresh never activates a generation and yields while a domain rebuild is pending, queued, or running.

## 16. Queue / Horizon

Targeted refreshes run on `cache-refresh` with Horizon tags for cache domain, detail type, entity ID, and `operation:refresh-after-write`. `DEPLOYMENT.md` now documents Horizon supervision and the local database worker command:

```text
php artisan queue:work database --queue=cache-refresh
```

## 17. Archive Unchanged Evidence

Focused Product and Post tests cache an archive before an edit, assert its old title remains, then run the detail job and assert only the detail exposes the latest content. Product and Blog generation values remain unchanged after targeted invalidation.

## 18. Focused Tests

- `tests/Feature/Storefront/StorefrontDetailCacheRefreshAfterWriteTest.php`: 7 passed, 21 assertions.
- Storefront, Product Filament, Post Filament, and cache-settings regression set: 129 passed, 1,010 assertions.

## 19. MySQL Concurrency

Isolated `tests/MySqlCommerce/StorefrontQueryCacheConcurrencyTest.php`: 1 passed, 4 assertions.

## 20. Full Suite

`php artisan test --compact`: 450 passed, 2,871 assertions, 0 failures, 0 skipped.

## 21. Database Safety

No destructive database command was run. `php artisan migrate` reported `Nothing to migrate`; `php artisan migrate:status` reported all migrations ran. The development `ecommerce` database was not reset.

## 22. AGENTS.md

The permanent cache policy now distinguishes archive eventual consistency from targeted after-commit Product/Post detail refresh, including the slug, visibility, deletion, inventory, generation, and SWR constraints.

## 23. DEPLOYMENT.md

Added the targeted `cache-refresh` detail-job/Horizon operational note and local database-worker command.

## 24. Raw Frontend

`D:\uni-shop-project\front` was not modified.

## 25. Remaining Limitations

Archive/search/filter caches remain intentionally eventually consistent after individual Product/Post edits. Browser-visible propagation depends on the configured `cache-refresh` worker; while delayed, safe public details use stale fallback only for still-valid routes. No per-entity operator button, generation swap, or new Site Setting was introduced.

`TARGETED PRODUCT/ARTICLE DETAIL CACHE REFRESH: VERIFIED PASS`
