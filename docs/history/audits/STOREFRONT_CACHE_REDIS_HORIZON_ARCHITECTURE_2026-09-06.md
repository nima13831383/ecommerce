# Storefront Cache / Redis / Horizon Architecture — 2026-09-06

## 1. Existing State

Product and Blog storefront reads previously executed directly on every archive and detail request. Cache records/locks already existed through Laravel's database cache infrastructure, but there was no selected-store setting, cache-generation rebuild protocol, rebuild audit trail, or Horizon configuration.

## 2. Central Cache Adapter

`StorefrontQueryCache` is the sole storefront query-cache adapter. It uses `StorefrontCacheStoreResolver`, selected from persisted Site Settings, and has no request-time `cache.default` mutation. It canonicalizes parameters, scopes active generations by domain and backend, stores fresh/stale envelopes, and rebuilds only through atomic locks.

## 3. Core Settings

The additive core settings are `cache.store` (`database` or `redis`), `cache.products.ttl_seconds` (3600), `cache.blog.ttl_seconds` (3600), `cache.lock_seconds` (30), and `cache.stale_seconds` (86400). They are registered in `SettingRegistry`, persisted additively, editable as values through Filament, and read through `SettingsService`. Redis selection fails safely while Redis is unreachable.

## 4. Infrastructure Configuration

`.env.example` documents Redis client/host/port/password/database separation plus cache/queue connections and queue retry values. Infrastructure remains environment configuration; only the selected storefront cache backend is a Site Setting.

## 5. Cache Storage

The existing Laravel `cache` and `cache_locks` database tables remain the local backend. No global cache flush, Redis-only tags, wildcard scans, or destructive cache-table operations were introduced.

## 6. Product Cache Policy

Public Product archives and details are cached through `ProductCatalogQuery`. Price-range, stock-sensitive, and price-sort listings deliberately remain live because their authoritative effective-price/inventory predicates are transactional. Cached Product metadata is overlaid with batched current inventory fields before storefront availability is presented; inventory, reservations, carts, and customer state are never cache authority.

## 7. Blog Cache Policy

Published Blog archives and slug details are cached through `StorefrontBlogQuery`. Existing publication visibility remains authoritative. No new timed publishing policy was added.

## 8. Keys, TTL, and Stale Reads

Keys use a canonical normalized parameter payload and SHA-256 digest, with domain, backend, generation, and scope. Fresh entries use the domain TTL; stale entries remain available for the configured stale window while one lock holder rebuilds. A lock contender serves stale data when possible, preventing stampedes.

## 9. Stampede / Concurrency Evidence

The isolated MySQL concurrency test ran six simultaneous worker processes against `ecommerce_testing`; all completed successfully and the protected rebuild closure executed exactly once: **1 passed, 4 assertions**. Cache tests also prove stale serving under lock contention.

## 10. Invalidation Policy

Product and Post saves do not automatically invalidate or rebuild storefront cache. Stable metadata ages by TTL or explicit rebuild. This is intentional; active inventory and reservation state is live.

## 11. Rebuild Commands

`cache:rebuild-products` and `cache:rebuild-blog` create tracked rebuild runs and enqueue work only. `cache:storefront-status` reports the selected backend, readiness, active generations, queue/Horizon availability, and recent runs without secrets.

## 12. Filament Management

The new **مدیریت کش و صف‌ها** Filament page, protected by `settings.update` (and the existing super-admin bypass), exposes safe asynchronous Product/Blog rebuild actions, current backend/readiness state, and recent runs. It does not synchronously warm a catalog.

## 13. Build-Before-Swap Generations

Each rebuild creates a `cache_rebuild_runs` record with target backend and next generation. The worker warms archive/detail entries under that generation and marks it active only after success. Failures leave the previous successful generation active.

## 14. Queue Design

`RebuildStorefrontCache` runs on `cache-rebuild`, has retries/backoff/timeout, idempotently claims its run, and carries Horizon cache tags. Local validation used the database queue. Production Redis queues are configured separately; `retry_after` is 900 seconds, exceeding the 600-second rebuild timeout.

## 15. Scheduler Recovery

Pending/queued rebuild runs are recovered every minute with `withoutOverlapping()` and `onOneServer()`. Horizon snapshot scheduling is also Redis-only and protected by the same multi-node safeguards.

## 16. Horizon

Laravel Horizon **v5.48.3** is installed and configured with separate `default` and `cache-rebuild` supervisors. Horizon access is authorized by the `viewHorizon` Gate (super-admin or `settings.update`), not by email. Horizon commands and routes register successfully. The local Windows PHP environment has no `pcntl`/`posix` and no Redis; Horizon was deliberately not started locally.

## 17. Operations / Deployment

`DEPLOYMENT.md` documents local database cache/queue operation, production Redis setup, Supervisor/Horizon process management, scheduler requirements, cache rebuild generation behavior, and `storage:link` media deployment setup.

## 18. Performance Evidence

Feature coverage proves a repeated Product and Blog detail read uses fewer database queries after the first cache fill. Local asynchronous warm evidence: Product rebuild completed in 260.67 ms and Blog rebuild in 191.24 ms. These are local observations, not production throughput claims.

## 19. Bugs Found and Fixed

### CACHE-REBUILD-MIGRATION-001 — Medium

The first run of the rebuild-run migration used an auto-generated MariaDB index name longer than the 64-character identifier limit. The migration had created its table before the index failure. It was repaired non-destructively to use a short explicit index name and to complete safely when the table already exists; `php artisan migrate` then completed normally.

### STOREFRONT-CACHE-001 — High

Cached Product model snapshots could present stale inventory attributes after an inventory change. The cache adapter now overlays current Product/active-variation inventory fields in batches before availability is resolved, preserving live stock authority without invalidating metadata cache.

## 20. Focused Runtime Results

| Coverage | Result |
| --- | --- |
| Cache settings and Filament cache management | 10 passed, 44 assertions |
| Storefront Product/Blog/cache + settings/Filament integration | 20 passed, 130 assertions |
| Product API regression | 6 passed, 54 assertions |
| Settings and Filament settings regression | 35 passed, 330 assertions |
| Storefront cache feature file | 7 passed, 26 assertions |
| Filament cache page/Horizon authorization | 1 passed, 11 assertions |
| Isolated MySQL cache concurrency | 1 passed, 4 assertions |

## 21. Complete Isolated Suite

`php artisan test --compact`: **424 passed, 2,794 assertions, 0 failures, 0 skipped** (82.88 s). Pest emitted a sandbox permission warning while updating its vendor-local result cache; test execution and results completed successfully.

## 22. Development Database Validation and Backups

Pre-change backup: `D:\uni-shop-project\db\backups\ecommerce_2026-09-06_102805.sql`.

Post-change backup: `D:\uni-shop-project\db\backups\ecommerce_2026-09-06_162510.sql`.

`php artisan migrate` reported **Nothing to migrate**. `php artisan migrate:status` reports both cache migrations as **Ran** (batches 7 and 8). The non-destructive migrations added core cache setting rows and the rebuild-run table only. No development reset, wipe, truncation, or schema drop was executed.

## 23. Code Quality

`vendor/bin/pint --dirty` completed and applied project formatting. `git diff --check` completed without whitespace errors.

## 24. Files Added / Changed

Key additions include the cache adapter/resolver/rebuild service/job/commands/enums/model/migrations, Horizon provider/configuration, Filament cache management page, cache-focused Feature/MySQL concurrency tests, `DEPLOYMENT.md`, and the documented configuration/settings/query integration changes. The raw template directory `D:\uni-shop-project\front` was not changed.

## 25. Permanent Project Rules

`AGENTS.md` now records the storefront cache backend-selection, generation, lock, stale-state, queue, and Horizon constraints.

## 26. Limitations / Follow-up

* Redis cache/queue connectivity and a live Horizon worker require a Redis-capable deployment plus Unix `pcntl`/`posix`; they were not simulated locally.
* Rebuild warming covers canonical archive pages and details. Arbitrary filter combinations remain lazy-filled under the same lock/stale rules.
* Administrators must request an explicit rebuild after Product/Post metadata changes when immediate publication is required.

## 27. Safety

Development database `ecommerce` was not destructively reset. Tests used isolated databases; the concurrency proof used `ecommerce_testing`. No Redis service was installed, no storefront raw-template file changed, and no Product/Post automatic invalidation was added.

## Final Status

`STOREFRONT CACHE + REDIS/HORIZON ARCHITECTURE: VERIFIED PASS`

`HORIZON REDIS RUNTIME: PENDING REDIS ENVIRONMENT`
