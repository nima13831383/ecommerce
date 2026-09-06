# Storefront Cache Stampede Hardening and Refresh-Ahead

## 1. Previous Hard-Miss Behavior

Hard-miss lock losers used Laravel `Lock::block(2)`, so a normal builder exceeding two seconds could surface a `LockTimeoutException`.

## 2. New Hard-Miss State Machine

The adapter now uses one non-blocking lock attempt, an owner double-check, bounded exponential rereads for losers, same-key previous-generation fallback, then a controlled 503.

## 3. Waiter Behavior

Waiters never invoke the builder. They reread at 50ms–500ms intervals for at most five seconds.

## 4. Previous-Generation Fallback

Only the same domain, backend, normalized scope, and parameter key in a retained previous generation is eligible. It never changes the active generation.

## 5. Lock Lease Policy

`cache.lock_seconds` defaults to 60 seconds, validates from 60–600 seconds, and is enforced at a 60-second minimum for legacy persisted values.

## 6. Lock Ownership / Fencing

The owner verifies `isOwnedByCurrentProcess()` immediately before write. A lost lease cannot overwrite a newer result. Laravel's portable lock API has no lease renewal; the lease margin and fencing check are the deliberate mitigation.

## 7. Builder Duration Evidence

Builder start/success/failure and local duration in milliseconds are recorded as lightweight observations. The isolated hard-miss concurrency fixture deliberately held its builder for 3,000ms; this is local evidence, not a production p99 claim.

## 8. Stale SWR Behavior

Unchanged: a stale lock loser returns stale immediately and never waits or rebuilds.

## 9. Refresh-Ahead Architecture

A fresh, near-expiry eligible key returns immediately and attempts a one-key `RefreshStorefrontCacheKey` job on low-priority `cache-refresh`.

## 10. Eligible Keys

Product: default archive, page 1, `sort=newest`, no filters.

Blog: default archive, page 1, null category/search.

## 11. Refresh Window

The registry-backed `cache.refresh_ahead_percent` setting defaults to 15% of the relevant fresh TTL and is bounded 5–30%.

## 12. Dispatch Deduplication

A short dispatch lock plus a backend-neutral 60-second dispatch marker permits one queued refresh per key/window.

## 13. Refresh Job

The job rechecks active generation, manual rebuild state, key eligibility, envelope freshness/window, and the authoritative rebuild lock before replacing a value.

## 14. Generation Safety

A job queued for an old generation is a no-op after a generation swap. It cannot write to or activate a newer generation.

## 15. Manual Rebuild Interaction

Pending, queued, or running manual rebuilds cause refresh-ahead to yield for that domain.

## 16. Queue Failure Behavior

Dispatch failures are logged and observed but swallowed: the current fresh response remains successful. Existing SWR remains the later fallback.

## 17. Observability

Structured events cover fresh/stale/hard-miss paths, locks, waits, builders, fallback, and refresh-ahead. Only rare transition summaries are stored in the database cache; no per-hit metrics rows are written.

## 18. Site Settings

Added additive core settings `cache.refresh_ahead.enabled=true` and `cache.refresh_ahead_percent=15`; existing setting values are preserved.

## 19. Filament

The cache management page shows refresh-ahead state/window and the latest successful builder duration.

## 20. Status Command

`cache:storefront-status` reports refresh state/window and safe recent observation summaries.

## 21. Hard-Miss Concurrency

Workers: 6 isolated MySQL workers.

Builder count: 1.

Waiter result: all received `rebuilt-once`; no lock timeout or deadlock.

## 22. Stale Concurrency

The existing locked-stale regression confirms stale is returned with zero duplicate builder calls.

## 23. Refresh-Ahead Concurrency

The dispatch marker regression confirms repeated near-expiry canonical reads enqueue one refresh job. Filtered archives and details enqueue none.

## 24. Redis Contract

The implementation uses Laravel cache/lock abstractions and Redis-compatible Horizon queue configuration only.

`REDIS REFRESH-AHEAD RUNTIME: PENDING REDIS ENVIRONMENT`

## 25. Focused Tests

`tests/Feature/Storefront/StorefrontQueryCacheTest.php` and `tests/Feature/Settings/StorefrontCacheSettingsTest.php`: 13 passed, 44 assertions.

Cache/settings/Filament focused run before final formatting: 14 passed, 55 assertions.

## 26. MySQL Concurrency Tests

`tests/MySqlCommerce/StorefrontQueryCacheConcurrencyTest.php`: 1 passed, 4 assertions, against `ecommerce_testing`.

## 27. Full Suite

Initial complete-suite run exposed two stale Core Setting-count assertions, corrected before the final rerun. Final isolated suite: 433 passed, 2,830 assertions, 0 failures, 0 skipped (82.26s).

## 28. Database Safety

Created non-destructive backup `D:\uni-shop-project\db\backups\ecommerce_2026-09-06_171031.sql`, then ran only normal additive migration. Development `ecommerce` was not reset.

## 29. AGENTS.md

Added permanent SWR, hard-miss, fencing, and narrow refresh-ahead rules.

## 30. DEPLOYMENT.md

Documented Redis/Horizon production recommendation and low-priority refresh-ahead operations.

## 31. Raw Frontend

`D:\uni-shop-project\front` was unchanged.

## 32. Remaining Limitations

No portable lock lease renewal is claimed. Redis runtime remains pending a Redis environment. Browser/frontend behavior was not changed.

`CACHE STAMPEDE HARDENING + NARROW REFRESH-AHEAD: VERIFIED PASS`

`REDIS REFRESH-AHEAD RUNTIME: PENDING REDIS ENVIRONMENT`
