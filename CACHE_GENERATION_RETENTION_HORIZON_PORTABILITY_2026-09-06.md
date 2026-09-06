# Cache Generation Retention / Horizon Portability — 2026-09-06

## 1. Previous Generation Cleanup Behavior

Before this follow-up, generation swaps selected the latest successful `cache_rebuild_runs` generation, but cache entries had no generation-key manifest. Old entries therefore expired by their normal stale TTL but could not be explicitly enumerated or pruned. Rebuild-run history also had no retention process.

## 2. Risk

**Database:** old generation rows could accumulate in Laravel's `cache` table until expiry and rebuild-run history could grow indefinitely.

**Redis:** the same key-lifetime issue would apply, but there was no safe Redis-independent way to enumerate only storefront generation keys. No Redis runtime was available locally.

## 3. New Retention Policy

`storefront_cache_generation_entries` is a lightweight manifest of only cache keys written by `StorefrontQueryCache`.

* Active: retained indefinitely.
* Previous: the immediately previous successful generation is retained indefinitely as rollback/stale safety.
* Older: pruned asynchronously after a 24-hour grace period.
* Failed target generations: never become active and are eligible for the same grace-period cleanup.

Retention is calculated independently for Products and Blog and separately for each backend.

## 4. Cleanup Job

`PruneStorefrontCacheGenerations` runs on `cache-rebuild`, retries twice with bounded backoff, has a 300-second timeout, carries Horizon tags, and delegates to the existing rebuild service. A database-backed atomic lock makes duplicate execution safe. It uses manifest keys and `forget()` only; it never scans a backend, uses cache tags, or calls `Cache::flush()`.

`storefront_cache_generation_prunes` records started/finished status and deletion counts for safe operational visibility.

## 5. Scheduler

The scheduler dispatches generation cleanup daily at 03:15, rather than every minute. It uses `withoutOverlapping()` and `onOneServer()`. A successful rebuild also asynchronously requests cleanup; grace-period checks mean this cannot remove the just-replaced generation.

## 6. Cache Rebuild Run Retention

Terminal successful/failed runs older than 60 days are pruned. The latest two successful runs for each domain/backend are retained regardless of age. Pending, queued, and running runs are never eligible for deletion.

## 7. Database Cache Growth Test

The retention Feature test creates three Product generations, verifies the active and previous cache rows remain, and verifies the older generation's actual database-cache row and manifest entry are removed after grace. This proves generation growth is bounded rather than relying only on metadata deletion.

## 8. Redis Cleanup Contract

The manifest stores each target backend alongside its exact cache key, so Redis cleanup uses the same `forget()` operations as database cache and never needs key scanning. The architecture is covered by backend-aware key/manifest and service tests.

`REDIS GENERATION CLEANUP RUNTIME: PENDING REDIS ENVIRONMENT`

No local Redis was installed, contacted, or represented as runtime-verified.

## 9. Horizon Composer Audit

### Windows

Normal fresh-clone diagnostic:

```text
composer install --dry-run --no-scripts --no-interaction --no-progress
```

fails because Horizon v5.48.3 requires `ext-pcntl`; `composer check-platform-reqs` also reports `ext-pcntl` and `ext-posix` missing on the current Windows PHP.

The reproducible local installation diagnostic succeeds with:

```text
composer install --dry-run --no-scripts --no-interaction --no-progress --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

Horizon remains installed but is not executable in this Windows environment.

### Linux

Production Linux must install the normal locked dependency set with real `pcntl`, `posix`, and Redis support, then pass:

```text
composer check-platform-reqs
```

Only then may its process supervisor run `php artisan horizon`; deploys use `php artisan horizon:terminate` for graceful restart. Ignored Windows requirements never certify production compatibility.

## 10. Fresh Composer Install Behavior

The normal Windows dry run was executed and failed exactly on Horizon's required extension. The documented ignored-extension dry run completed with **Nothing to install, update or remove**. This is an explicit, reproducible local exception, not a repository-level platform lie and not a production workaround.

## 11. DEPLOYMENT.md

Updated with Local Windows database cache/queue behavior, exact Composer installation command, the prohibition on local Horizon execution, generation and run retention, Linux Redis/extension prerequisites, `composer check-platform-reqs`, and Horizon lifecycle commands.

## 12. AGENTS.md

Updated concise permanent rules: generation manifests must support bounded asynchronous cleanup, obsolete generations may not accumulate, rebuild-run retention preserves non-terminal work, local Horizon package presence does not imply Windows runtime, and production must satisfy real `pcntl`/`posix` requirements.

## 13. Focused Tests

* `StorefrontCacheGenerationRetentionTest`: **5 passed, 21 assertions**.
* Existing cache/Filament/settings regression set: **11 passed, 47 assertions**.
* Isolated six-worker MySQL cache concurrency: **1 passed, 4 assertions**.

Pest printed a sandbox-only warning when attempting to update its vendor-local result cache. It did not affect test execution or results.

## 14. Full Suite

`php artisan test --compact`: **429 passed, 2,815 assertions, 0 failures, 0 skipped** (82.16 s). The same non-fatal sandbox result-cache warning appeared.

## 15. Database Safety

Pre-migration backup: `D:\uni-shop-project\db\backups\ecommerce_2026-09-06_163722.sql`.

Post-validation backup: `D:\uni-shop-project\db\backups\ecommerce_2026-09-06_164224.sql`.

The non-destructive `php artisan migrate` added only the manifest/audit tables and retention index. `migrate:status` reports both migrations as Ran in batch 9. No `migrate:fresh`, wipe, truncate, drop, reset, or destructive development-database operation was used.

## 16. Final Validation

`vendor/bin/pint --dirty`: passed.

`git diff --check`: passed.

`php artisan cache:storefront-status` reported database as the selected/operational backend, Product and Blog active/retained generation `2`, zero old entries pending cleanup, and no completed prune yet. `php artisan schedule:list` reports the daily 03:15 pruner alongside the existing guarded recovery schedule.

## 17. Remaining Limitations

* Real Redis generation cleanup and Horizon worker runtime require a Redis-capable Unix environment with `pcntl` and `posix`.
* The predecessor cache entries created before manifest introduction remain governed by their existing TTL; all newly written storefront entries are manifest-tracked.

## Final Status

`CACHE GENERATION RETENTION + HORIZON PORTABILITY: VERIFIED PASS`
