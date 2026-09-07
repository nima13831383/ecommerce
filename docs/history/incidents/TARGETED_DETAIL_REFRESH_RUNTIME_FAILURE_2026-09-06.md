# Targeted Detail Refresh Runtime Failure Audit

## 1. Exact Runtime Exception

The failed database worker reported:

```text
Error: Call to undefined method App\Services\Storefront\StorefrontQueryCache::refreshDetail()
```

Source location:

```text
D:\uni-shop-project\ecommerce-main\app\Services\Storefront\StorefrontDetailCacheRefreshService.php:96
```

The first relevant application frames were `StorefrontDetailCacheRefreshService->refreshProduct(19)` and `RefreshStorefrontDetailAfterWrite->handle(...)`.

## 2. Failed Job IDs

Exactly two failed records were present before remediation:

| UUID | Queue | Connection | Job | Final failed-at |
| --- | --- | --- | --- | --- |
| `a3515b7c-8441-4b2e-9a2a-1de6e86346ee` | `cache-refresh` | `database` | `App\Jobs\Storefront\RefreshStorefrontDetailAfterWrite` | 2026-09-06 18:46:16 |
| `01d60ac9-98c9-4bb5-ade6-693013b23497` | `cache-refresh` | `database` | `App\Jobs\Storefront\RefreshStorefrontDetailAfterWrite` | 2026-09-06 18:50:14 |

Both payloads resolved to Product ID 19 (`demo-saye-volume-mascara`). No Blog detail job was in the failed set.

## 3. Retry / Backoff Explanation

`RefreshStorefrontDetailAfterWrite` is configured with `tries = 2`, `timeout = 120`, and backoff `[5, 30]` seconds. The log showed each UUID failing on its initial attempt and again on its retry (18:46:10/18:46:16 and 18:50:08/18:50:14). The queue retry command was run only for the two UUIDs listed above.

## 4. Root Cause

The queue worker was a long-lived PHP process loaded before the current `StorefrontQueryCache::refreshDetail()` implementation was available. Fresh application/test processes had the current class, while that worker retained the older class definition. Queue dispatch, routing, the database connection, and the Product payload were correct; the failure was stale worker code after deployment/change.

## 5. Why Automated Tests Missed It

The automated tests bootstrap a fresh PHP process and invoke the current class graph. They therefore exercised the method successfully but did not model a long-lived worker retaining an older loaded class. The existing targeted test does execute the same job `handle()` path and is the regression coverage for the missing-method call path.

## 6. Fix

No domain-code workaround or duplicate refresh implementation was added. The two failed UUIDs were retried after broadcasting `php artisan queue:restart`, and the worker was run with:

```text
php artisan queue:work database --queue=cache-refresh --stop-when-empty --tries=2 --timeout=120
```

`DEPLOYMENT.md` now explicitly requires restarting long-lived database workers after deploying cache-refresh code. This is the smallest correct operational fix for stale loaded worker classes.

## 7. Product vs Blog Impact

The observed failure was Product-only: Product ID 19. No failed Blog refresh record was present, and no Blog detail/archive behavior was changed by the remediation.

## 8. Unicode Slug Interaction

Detail keys continue to be produced by the central `StorefrontQueryCache::key()` method using normalized parameters and SHA-256; slugs are not transliterated or rewritten by the cache layer. Existing Product slug-policy coverage verifies stable Persian/Unicode slug behavior. The targeted refresh job reloads the persisted current slug, so Unicode slugs use the same authoritative detail-refresh path.

## 9. Generation / Cache-Key Interaction

The targeted job resolves the active generation at execution time and writes only the active generation's detail key. It does not create or swap a generation. Slug changes invalidate old retained-generation detail keys before warming the new public slug; unpublish/delete invalidate detail keys without preserving a customer-visible fallback.

## 10. Worker Restart Requirement

Database workers and Horizon are long-lived and keep PHP classes in memory. After deploying targeted refresh code, restart the relevant workers (`php artisan queue:restart`, or graceful `php artisan horizon:terminate` under Horizon) before processing `cache-refresh`. Local Windows uses the database worker; Horizon is not run locally.

## 11. Homepage Product Cache

`HomeController` uses the shared `ProductCatalogQuery` with the exact normalized archive parameters `featured=true`, `sort=newest`, `per_page=7`, `page=1`. It therefore uses the same generation-scoped central Product archive cache as the listing. The homepage does not have a second cache implementation. Current active backend is `database`, active Product generation is `4`, and the homepage key is fresh.

## 12. Current Product Archive Key Map

Observed through `StorefrontQueryCache::key()` and the database cache store:

| Surface | Canonical parameters | Active key | State |
| --- | --- | --- | --- |
| Homepage featured | `featured=true, sort=newest, per_page=7, page=1` | `storefront:products:g:4:archive:f7425dc7d3db1389c4c1d1fa774a12394ac8ac23605a1afe4a989fc27ee78955` | fresh |
| Default `/products` listing | `per_page=10, page=1, sort=newest` | `storefront:products:g:4:archive:b2a4647c15d8f7ceed55dc78191862c5a6dc24c8f9f35a46503338e04acb5b1d` | fresh |

Product 19 detail currently maps to generation-4 key `storefront:products:g:4:detail:ff595aaa98e2f87aefabc1341ee6b32e67b7e84da263d18c65f2d70ffd8336d`, state `fresh`.

## 13. Runtime Queue Proof

After retrying only the two failed UUIDs, the real worker output was:

```text
2026-09-06 18:56:44 App\Jobs\Storefront\RefreshStorefrontDetailAfterWrite RUNNING
2026-09-06 18:56:45 App\Jobs\Storefront\RefreshStorefrontDetailAfterWrite DONE (58.04ms)
2026-09-06 18:56:45 App\Jobs\Storefront\RefreshStorefrontDetailAfterWrite RUNNING
2026-09-06 18:56:45 App\Jobs\Storefront\RefreshStorefrontDetailAfterWrite DONE (18.84ms)
```

Immediately afterward, `php artisan queue:failed` returned `INFO No failed jobs found.` and the failed-job table contained no records for either UUID.

## 14. Targeted Detail Result

Product detail refresh completed successfully for both retried Product-19 jobs. The current Product state was reloaded by ID and the active generation detail entry was written by the central cache service.

## 15. Archive Unchanged

The targeted job did not create a Product generation or alter archive-generation identity. The active Product generation remained `4`; the homepage featured and default listing archive keys remained present and fresh. Existing targeted tests also assert that an edit changes only detail freshness while retaining the archive value.

## 16. Failed-Job Retry Safety

Only the two explicitly identified cache-refresh UUIDs were retried. No SMS, notification, payment, provider, or unrelated queue job was retried. No broad retry or queue flush was used.

## 17. Regression Test

`tests/Feature/Storefront/StorefrontDetailCacheRefreshAfterWriteTest.php` directly runs `RefreshStorefrontDetailAfterWrite::handle()` through the current coordinator for Product and Blog and asserts latest-state detail refresh, slug changes, unpublish/delete invalidation, archive preservation, rollback safety, and inventory-only non-refresh behavior. This covers the exact application call path that previously failed on the missing `refreshDetail()` method.

## 18. Focused Tests

```text
php artisan test --compact tests/Feature/Storefront/StorefrontDetailCacheRefreshAfterWriteTest.php tests/Feature/Storefront/StorefrontQueryCacheTest.php
Tests: 18 passed (52 assertions)
Failures: 0
Skipped: 0
```

The broader focused regression group (Storefront, Product/Blog Filament, and Storefront cache settings) completed with `129 passed (1010 assertions)`, zero failures, and zero skips.

## 19. MySQL Concurrency

With `APP_ENV=testing`, `DB_CONNECTION=mysql`, and `DB_DATABASE=ecommerce_testing`:

```text
tests/MySqlCommerce/StorefrontQueryCacheConcurrencyTest.php
Tests: 1 passed (4 assertions)
Failures: 0
Skipped: 0
```

## 20. Full Suite

The post-remediation isolated full suite completed with:

```text
Tests: 450 passed (2871 assertions)
Failures: 0
Skipped: 0
Duration: 84.98s
```

The test runner emitted only the existing non-fatal Pest result-cache warning (`vendor/pestphp/pest/.temp/test-results` permission denied); it did not affect the exit status or test result.

## 21. Database Safety

Development validation was non-destructive:

```text
php artisan migrate
INFO Nothing to migrate.
```

`php artisan migrate:status` reported all migrations as `Ran`, through batch 10. No `migrate:fresh`, wipe, truncate, drop, reset, or destructive seed was run. The `ecommerce` database was not modified by test resets.

## 22. Raw Frontend

`D:\uni-shop-project\front` was not modified.

## Files Changed

- `DEPLOYMENT.md` — documented worker restart after cache-refresh code deployment.
- `TARGETED_DETAIL_REFRESH_RUNTIME_FAILURE_2026-09-06.md` — this runtime failure audit.

The prior `TARGETED_DETAIL_CACHE_REFRESH_AFTER_WRITE_2026-09-06.md` report was preserved and not rewritten.

The existing targeted refresh implementation and tests remain in place; no second cache-refresh implementation was introduced.

`TARGETED DETAIL REFRESH REAL QUEUE RUNTIME: VERIFIED PASS`
