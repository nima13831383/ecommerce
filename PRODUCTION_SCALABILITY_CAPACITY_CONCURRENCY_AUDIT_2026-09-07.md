# Production Scalability / Capacity / Concurrency Audit — 2026-09-07

## 1. Executive Summary

The codebase has a strong commerce correctness foundation: critical inventory, order, payment, coupon, shipment, notification, checkout-idempotency, and storefront-cache races are protected by transactions, row locks, unique constraints, distributed cache locks, or idempotency keys, with real MySQL concurrency evidence for the principal flows.

The repository is not a production-like capacity test environment. Local development uses Windows, PHP 8.2.12, database cache/queue defaults, and a normal PHP application process. Redis, Horizon worker runtime extensions (`pcntl`/`posix`), Linux PHP-FPM/Nginx, production traffic, and a measured production dataset are not available here. Consequently no honest production RPS or concurrent-user number can be certified.

The principal scale risks are known and bounded: effective-price and stock expressions use correlated subqueries for live listing paths, search uses leading-wildcard `LIKE`, cache cardinality can grow with high-cardinality filters, synchronous payment/SMS provider calls depend on provider latency, and production connection/worker budgets are not yet sized. These are roadmap items rather than evidence of a correctness failure.

## 2. Current Architecture

Laravel 12 / PHP 8.2 application with Blade SSR storefront, Filament admin, MySQL relational persistence, Eloquent, Laravel transactions, database-backed local sessions/cache/queues, optional Redis cache/queue/locks, and Horizon production configuration. Domain services include `ProductCatalogQuery`, `ProductPriceResolver`, `CartService`, `CouponService`, `InventoryService`, `CheckoutService`, `OrderService`, `PaymentService`, `ShipmentService`, `CustomerOtpService`, and the centralized `StorefrontQueryCache`.

The normal request path is web/API controller → request validation → domain/query service → Eloquent/database → Blade/API Resource. Storefront cache reads use a backend-neutral adapter with generation-aware keys, stale-while-revalidate, distributed locks, previous-generation fallback, and asynchronous refresh/rebuild jobs.

## 3. What “User Capacity” Means

These are different measures:

| Term | Meaning |
|---|---|
| Stored users | Rows persisted in `users`; not active traffic. |
| Active users | Users making requests during a time window. |
| Concurrent users | Sessions simultaneously using the system; each may generate multiple requests. |
| Concurrent requests | In-flight HTTP requests at one instant. |
| RPS | Completed requests per second at a defined latency/error target. |
| Checkout throughput | Successful order placements per second/minute; constrained by DB locks, inventory, payment and idempotency. |

Example capacity planning (not measured here): 20,000 active users × 3 requests/minute ÷ 60 = 1,000 average RPS; a 2× peak factor would be 2,000 peak RPS. This is a planning formula, not a project result.

## 4. Current Deployment Assumptions

`.env.example` describes local `APP_ENV=local`, `APP_DEBUG=true`, MySQL database `ecommerce`, database sessions, database cache, and database queues. `DEPLOYMENT.md` specifies shared production state, Redis for cache/queues/locks, Linux Horizon requirements, scheduler coordination, public media storage, and supervised workers. No Nginx/PHP-FPM/Octane/container orchestration or production topology is present in the repository.

## 5. Registered User Scale

The users table uses Laravel `id()` (unsigned 64-bit bigint), a unique email, indexed session user references, indexed status, nullable unique mobile, and soft deletion. The schema can represent 100k, 1M, and 10M rows, but storage, secondary-index size, authentication lookup latency, session cleanup, and operational backup/restore times require production measurements. No row-count or capacity claim is inferred from the schema alone.

## 6. Concurrent User Scale

No concurrency-user ceiling is certified. Database sessions are shareable across application nodes when backed by the central database; Redis sessions are supported by configuration. Production must size PHP workers, session storage, database connections, cache/lock capacity, and load-balancer timeouts together. A session count is not equivalent to concurrent request capacity.

## 7. Concurrent Request Scale

No production-like worker pool or load test exists. Local `php artisan serve`/Windows execution is development evidence only and must not be used as a production benchmark. Concurrent-request capacity is pending a Linux/Nginx/PHP-FPM (or explicitly selected equivalent) test with production-like MySQL and Redis.

## 8. RPS Capacity

| Path | Current implementation | Measured RPS |
|---|---|---:|
| Cached homepage/archive/detail/blog | Blade/query services use `StorefrontQueryCache`; SWR and locks are implemented. | Not measured |
| Uncached product/detail | Eloquent eager loading plus live inventory refresh. | Not measured |
| Authentication/account reads | Web session and ownership-scoped Eloquent queries. | Not measured |
| Cart mutation | Transaction, cart/item row locks, authoritative recalculation. | Not measured |
| Checkout calculation/order create | Transactional pricing, inventory reservation and idempotency. | Not measured |
| Payment initiate/verify | DB locks/idempotency around synchronous gateway calls. | Not measured |

`PRODUCTION RPS CAPACITY: NOT YET CERTIFIABLE`

## 9. Local Benchmark Environment

Observed local evidence: Windows PowerShell environment, PHP 8.2.12, `pdo_mysql` loaded, MySQL reachable on `127.0.0.1:3306`, and no `pcntl`, `posix`, or `redis` PHP extensions reported. Exact CPU/RAM and Windows WMI details were unavailable because CIM access was denied. The local application defaults to database cache/queue; the isolated MySQL test database is `ecommerce_testing`.

No benchmark web server, Redis service, production-sized dataset, or external provider traffic was used.

## 10. Benchmark Results

No HTTP load benchmark was run, so p50/p95/p99, sustainable RPS, saturation point, and error rate are intentionally `N/A`. Sequential concurrency test durations are correctness-test observations, not capacity measurements.

## 11. Production Capacity Confidence

Confidence is architectural rather than quantitative: domain race protection is high, cache/queue deployment design is documented, but production capacity confidence is pending a production-like load test and connection/worker sizing.

## 12. Cache Scalability

`StorefrontQueryCache` canonicalizes parameter payloads into SHA-256 keys scoped by domain/backend/generation. Fresh hits are served directly; stale hits are served while one lock holder rebuilds; hard-miss contenders perform bounded rereads and previous-generation fallback before a controlled 503. Builders verify lock ownership before writing. Generation rebuilds are build-before-swap; manifests and asynchronous pruning retain active/previous generations and avoid global flushes.

Archive/search/filter/page keys are potentially high-cardinality and are TTL/SWR or manual-rebuild driven. Live inventory/price-filter paths bypass cached listing results and refresh current stock state. Transactional/customer state is not treated as stale cache truth. Capacity still depends on cache backend memory/eviction and key volume, which are not measured here.

## 13. Redis Readiness

Redis is configured as an optional Laravel cache/queue/lock backend with separate logical connections (`cache`, `queue`, and databases). The adapter does not use Redis-only tags or scans. Redis runtime was not available locally, so connectivity, latency, failover, eviction, and multi-node lock behavior remain deployment verification items. Production should use unique prefixes, persistence/HA appropriate to its SLA, TLS/authentication as required, and monitoring.

## 14. Queue/Horizon Scalability

Horizon 5.48 is configured with separate `commerce`, `cache-rebuild`, and `cache-refresh` supervisors. Production defaults allow 10 commerce processes, 2 rebuild processes, and 1 refresh process; local defaults are smaller. Jobs define bounded retries/timeouts/backoff and cache jobs carry idempotent rebuild identities. Database queues remain supported locally; Horizon monitors Redis queues only. `retry_after` (900s) exceeds the 600s rebuild timeout.

The configured worker counts are starting settings, not proven capacity. Queue lag, job duration, failed-job rate, retry storms, and DB connection consumption must be measured under production load. Horizon cannot execute on the current Windows PHP because `pcntl`/`posix` are absent.

## 15. DB Schema Scale

Relational tables use bigint foreign keys and meaningful uniqueness/indexes. Products have unique slug/SKU and indexes for status/feature, type/stock, price, and published time. Orders have unique order number/idempotency indexes plus user/status and timestamps. Cart lines have a composite uniqueness constraint. Inventory, payment, shipment, notification, and taxonomy pivots have ownership/lookup indexes and unique invariants.

The schema is structurally suitable for growth, but large-table statistics, index selectivity, history retention, archival, and backup/restore duration need production measurements.

## 16. Index/EXPLAIN Findings

Positive: public status, publication, taxonomy, ownership, idempotency, and uniqueness paths are indexed. Risk: `ProductCatalogQuery` search uses `%term%` `LIKE` on `name`/`short_description`, which cannot use a normal B-tree prefix index. Effective-price and live-stock filters/sorts use correlated subqueries over variations/reservations and may become CPU/IO heavy at scale. `whereHas` taxonomy filters and `orderBy` expressions require EXPLAIN on the real MySQL version and data distribution.

Recommended evidence: capture EXPLAIN/EXPLAIN ANALYZE for newest, taxonomy, search, effective-price sort/filter, in-stock, and variable-detail queries at representative row counts before adding indexes or read models.

## 17. Product Catalog at 10k/100k/1M

At 10k products the eager-loaded listing and generation-aware cache should be operationally manageable. At 100k, leading-wildcard search, price/stock subqueries, cache key cardinality, and large rebuild windows become likely bottlenecks. At 1M, the current synchronous Eloquent listing path should not be assumed sufficient; plan denormalized public read models/search infrastructure, bounded rebuild batches, and measured indexes. No 100k/1M benchmark was performed.

## 18. User Table at 100k/1M/10M

Unique email/mobile and indexed status support point lookups. At 1M–10M, session/history retention, admin relationship counts, login-attempt cleanup, backup time, and secondary-index storage need partitioning/archival or operational policies. Authentication lookup itself is indexed, but no throughput claim is made.

## 19. Search Scalability

Current search is database `LIKE` across product names/descriptions and blog title/content. This is correct but not a scalable relevance/search solution for 1M products or multilingual fuzzy search. Keep the current path for modest catalogs; evaluate MySQL full-text or a dedicated search service only after measured need, with cache invalidation/indexing events already available as integration points.

## 20. Session Scalability

Database sessions are the current default and are compatible with multiple nodes when the database is shared. Redis sessions are configurable and preferable when DB session write contention becomes material. File/cookie/local-process assumptions are not required by the application contract. Session garbage collection, idle-session retention, and connection cost must be monitored.

## 21. Media/File Scalability

Product/Blog media use the configured public disk and Laravel `storage:link`. Local disk is not a multi-node shared filesystem; production must use shared/object storage or node-shared mounts plus CDN where appropriate. Media reconciliation is idempotent and source-preserving. Image processing and large uploads should remain off the critical request path.

## 22. Load Balancer Compatibility

The documented architecture supports multiple Laravel nodes with shared DB, Redis, queues, scheduler coordination, and shared/object media. Required deployment checks include HTTPS, trusted proxies, sticky-session avoidance where using shared sessions, health checks, request/body limits, upload timeouts, and consistent `APP_KEY`/config across nodes. No multi-node runtime test was available.

## 23. DB Connection Budget

No pool or hard connection budget is configured in PHP. The deployment must reserve connections for PHP web workers, Horizon/queue workers, scheduler, admin/diagnostics, migrations, and monitoring while remaining below MySQL `max_connections` with headroom. A practical budget is `web workers × per-worker connections + queue workers × connections + maintenance`; measure actual concurrency and wait time before increasing workers.

## 24. Race Condition Matrix

| Area | Classification | Evidence |
|---|---|---|
| Inventory adjustment/reservation/commit | PROTECTED | Transactions, owner/reservation locks, atomic state transitions, unique references; real MySQL test passed. |
| Cart mutation | PARTIALLY PROTECTED | Cart/item locks and composite line uniqueness exist; no dedicated real multi-process Cart suite was found. |
| Checkout/order creation | PROTECTED | Transaction, idempotency key/fingerprint, unique DB key, inventory reservation; real MySQL tests passed. |
| Coupon redemption | PROTECTED | Coupon/usage locks and unique coupon-order usage; real MySQL contention tests passed. |
| Payment initiation/verification | PROTECTED | Payment/order row locks, initiation idempotency, verified callback boundary; real MySQL tests passed. |
| Shipment ensure/transition | PROTECTED | Order lock, shipment lock, one-shipment unique key; real MySQL tests passed. |
| Notification intent/delivery | PROTECTED | Unique intent key and locked retry/status transitions; real MySQL tests passed. |
| OTP challenge/resend | PARTIALLY PROTECTED | Hashed, purpose-bound, expiry/attempt/resend limits and rate limiting; no equivalent real multi-process OTP race suite. |
| Product slug generation | PARTIALLY PROTECTED | Unique DB slug plus service collision suffixes; concurrent slug-generation evidence is absent. |
| Storefront cache stampede | PROTECTED WITH DEPLOYMENT LIMIT | Atomic backend lock, SWR, hard-miss wait, ownership check, generations; real MySQL cache contention test passed. Redis runtime remains unverified. |

## 25. Real MySQL Concurrency Evidence

The following isolated MySQL files were run sequentially against `ecommerce_testing`; all passed:

| Test file | Tests | Assertions |
|---|---:|---:|
| `ConcurrencyHarnessTest.php` | 1 | 7 |
| `InventoryConcurrencyTest.php` | 1 | 7 |
| `CouponRedemptionConcurrencyTest.php` | 4 | 51 |
| `CheckoutIdempotencyConcurrencyTest.php` | 1 | 24 |
| `CheckoutIdempotencyConflictConcurrencyTest.php` | 5 | 35 |
| `CancellationPaymentConcurrencyTest.php` | 1 | 38 |
| `PaymentVerificationConcurrencyTest.php` | 2 | 43 |
| `ShipmentEnsureConcurrencyTest.php` | 2 | 32 |
| `NotificationIntentConcurrencyTest.php` | 2 | 24 |
| `StorefrontQueryCacheConcurrencyTest.php` | 1 | 4 |
| `MySqlSchemaTest.php` | 1 | 8 |
| **Total** | **21** | **273** |

These are correctness proofs, not a sustained-load benchmark. The Pest result-cache permission warning was non-fatal and did not alter outcomes.

## 26. Missing Concurrency Coverage

Add focused isolated multi-process coverage for Cart line add/update contention, OTP resend/change-mobile races, concurrent Product slug generation/edit, settings synchronization contention, and Redis-backed cache locks. Add deadlock/retry observability tests where deployment behavior requires it.

## 27. Idempotency Matrix

| Operation | Key/constraint | Status |
|---|---|---|
| Checkout/order create | Client idempotency key + request fingerprint + unique DB key | Protected and MySQL-tested |
| Payment initiation | Order + initiation idempotency key | Protected and MySQL-tested |
| Payment verification/callback | Persisted authority/payment state + locked transition | Protected and MySQL-tested |
| Coupon redemption | Coupon/order unique usage + locked counter | Protected and MySQL-tested |
| Shipment ensure | Unique `order_id` shipment | Protected and MySQL-tested |
| Notification intent | Unique idempotency key | Protected and MySQL-tested |
| Cache rebuild | Rebuild run/generation identity and job claiming | Protected by job/service design |
| Cart mutation | Composite cart/product/variation line key | Protected against duplicate lines; race suite gap remains |
| OTP send | Rate-limit/challenge state, no global request idempotency key | Partially protected |

## 28. Transaction/Deadlock Risks

Nested domain workflows lock parent rows and related inventory/order/payment rows. This is correct but imposes lock ordering and wait-time requirements. Payment network calls are outside the state-transition transaction, which avoids holding DB locks across the provider request. Order creation reserves inventory inside the order transaction and can contend on popular stock. Monitor deadlocks/timeouts and keep retry behavior bounded; run production-like contention tests before increasing worker counts.

## 29. External Provider Scalability

ZarinPal and SMS.ir adapters are synchronous provider calls behind project-owned interfaces. Provider quotas, network latency, SDK timeouts, circuit breaking, and retry budgets are environment/provider concerns and were not measured. Never perform live provider traffic for a capacity test. Payment amount and verification remain server-authoritative; SMS OTP is rate-limited and secret-safe.

## 30. Rate Limiting/Abuse

Breeze login retains five-attempt throttling; OTP send uses a hashed mobile/IP/purpose key with bounded attempts and cooldown. API auth reuses the login throttle. Public catalog endpoints have no explicit application throttle in the inspected routes; production should apply edge/WAF or route throttles appropriate to abuse risk without harming cacheable reads.

## 31. Observability

Structured logging exists for payment/provider failures, SMS outcomes without secrets, notification failures, cache builder/lock events, failed jobs, and settings changes. Storefront cache observability exposes fresh/stale/hard-miss/lock/builder signals and a status command. A full APM/metrics/tracing backend is not configured; production should export request latency, DB time, cache hit ratio, lock contention, queue lag, provider latency, and business-error counters.

## 32. Failure Recovery

Transactions roll back partial commerce writes. Idempotency replays recover existing orders/payments. Cache hard-miss failures return a controlled 503 or previous-generation fallback; failed rebuilds do not activate incomplete generations. Queue jobs have retries/backoff and failed hooks. Deployment documentation covers queue restart, Horizon termination, scheduler overlap protection, media reconciliation, and storage linking.

## 33. Single Points of Failure

Without production HA, the primary MySQL instance, Redis instance (when selected), object/media storage, load balancer, and external payment/SMS providers are potential SPOFs. The application design supports centralized infrastructure but does not provision HA/failover itself. Define RPO/RTO, backups, replicas, Redis topology, provider fallback policy, and restore drills before launch.

## 34. What Project Already Does Well

Relational uniqueness and foreign keys, transactional domain services, row-level locks, checkout/payment idempotency, authoritative pricing/inventory, snapshot-based orders, ownership-scoped storefront queries, cache generation swap/fallback, queue separation, scheduler overlap/single-server safeguards, environment-gated diagnostics, secret-safe provider boundaries, and extensive isolated real-MySQL concurrency tests are all strong foundations.

## 35. Critical Weaknesses

1. No production-like RPS/concurrent-user benchmark or capacity certification.
2. Redis/Horizon/Linux worker runtime is documented but not locally runtime-verified.
3. Leading-wildcard catalog/blog search and correlated live price/stock expressions may not scale to million-row catalogs.
4. Web/queue worker and MySQL connection budgets are not measured.
5. Synchronous external provider latency/timeout behavior is not capacity-tested.
6. Cart, OTP, and slug concurrency suites are less complete than the core order/payment suites.

## 36. High Priority Improvements

Run a production-like load test on Linux/Nginx/PHP-FPM, Redis, and a restored representative MySQL dataset; capture EXPLAIN/slow-query data; set worker/connection budgets; verify Redis locks/Horizon; configure provider timeouts and observability; add Cart/OTP/slug concurrency tests; and define shared media/CDN deployment.

## 37. Medium Priority Improvements

Introduce search indexing/full-text only when measured need appears; add public read-model projections for very large catalogs; tune cache TTL/SWR and generation rebuild batch sizes from metrics; add queue-lag alerts and deadlock dashboards; formalize archival/retention for sessions, logs, and historical operational tables.

## 38. Improvements Not Needed Yet

Do not add Octane, sharding, microservices, a second cache system, a popularity algorithm, Redis-only cache tags, or a search cluster before production-like measurements demonstrate need. Do not redesign completed commerce services merely to pursue theoretical scale.

## 39. Horizontal Scaling Model

Recommended model: stateless Laravel web nodes behind a load balancer; central MySQL primary (with replicas where read routing is proven safe); Redis for shared cache/locks/queues/sessions; Horizon workers for Redis queues; one coordinated scheduler; shared/object media plus CDN; centralized logs/metrics/traces. Database writes and transactional reads remain authoritative on the primary as required by consistency.

## 40. Redis Scaling Impact

Moving cache/queue/session/lock workloads to Redis reduces database contention but introduces memory, eviction, HA, network, and operational requirements. Use separate logical connections/prefixes, monitor memory and blocked clients, size for cache plus queue plus session workloads, and validate lock behavior under failover. No local Redis claim is made.

## 41. CDN/Static Asset Impact

CDN delivery for immutable CSS/JS/images reduces PHP and origin bandwidth and improves global latency. Product/blog media URLs remain Laravel disk-backed and require `storage:link` locally or shared/object storage in production. Cache invalidation/versioned asset names must be coordinated with deploys; CDN must not cache personalized/cart/account responses.

## 42. PHP-FPM/Octane Assessment

PHP-FPM is the conservative production baseline and is compatible with the documented stateless/session architecture. Octane is not required by current evidence and would introduce long-lived-worker state hygiene requirements; it should not be adopted before profiling. Windows `php artisan serve` is not a capacity proxy.

## 43. Scaling Roadmap P0/P1/P2/P3

| Stage | Work |
|---|---|
| P0 | Production-like benchmark; backups/restore drill; APP_DEBUG off; shared media; Redis/Horizon verification; connection budget; provider timeout/observability review. |
| P1 | EXPLAIN/slow-query tuning; queue/cache metrics and alerts; Cart/OTP/slug concurrency coverage; PHP-FPM worker sizing. |
| P2 | Search/read-model design for measured catalog growth; CDN/object storage; replicas for safe read workloads; archival policies. |
| P3 | Only if evidence demands it: sharding, partitioning, Octane, multi-region, or specialized search/queue infrastructure. |

## 44. Cost/Complexity Matrix

| Improvement | Benefit | Complexity/cost | Timing |
|---|---|---|---|
| Linux PHP-FPM + Redis/Horizon | Shared scalable runtime and queue throughput | Medium | P0 |
| Representative load test | Removes capacity uncertainty | Medium | P0 |
| Query/EXPLAIN tuning | Lower DB latency | Low/medium | P0/P1 |
| CDN/object media | Lower origin bandwidth, multi-node media | Medium | P0/P1 |
| Full-text/search service | Better large-catalog search | Medium/high | P2, evidence-driven |
| Read models/replicas | Higher read scale | High consistency complexity | P2 |
| Sharding/multi-region | Very high scale/availability | Very high | P3 only if required |

## 45. So, How Many Users Can It Handle?

The honest answer is: the schema and concurrency controls are suitable for a staged production rollout, but this repository cannot state a number of concurrent users or RPS. A defensible number requires a production-like benchmark with target latency/error SLOs, representative catalog/users/carts/orders, configured workers, Redis, MySQL limits, and realistic traffic mixes.

## 46. Production Load Test Required

Required before capacity certification: Linux host(s), Nginx or equivalent, PHP-FPM, production PHP extensions, Redis, MySQL with representative indexes/data, shared media, queue workers, scheduler, and safe provider doubles. Test warm cache, cold cache, cache stampede, search/filter, account/cart, checkout/order, payment verification with doubles, queue lag, deadlocks, failover, and recovery. Do not use real ZarinPal/SMS traffic.

## 47. Exact Recommended Production Benchmark

Run stepped 5/10/15/30-minute tests at increasing concurrency with a realistic mix: 45% cached archive/detail/home/blog reads, 20% uncached/search/filter reads, 10% account/order reads, 10% cart mutations, 8% checkout previews, 5% order placement, 2% payment verification. Record p50/p95/p99, throughput, 4xx/5xx, DB CPU/locks/connections, Redis hit/eviction/latency, PHP-FPM saturation, queue lag, provider-double latency, and cache hard-miss/lock metrics. Repeat at 10k/100k/1M catalog scales where applicable. Stop at SLO breach and report sustainable throughput, not peak burst.

## Validation and Safety Evidence

`php artisan migrate` completed with `Nothing to migrate`; `php artisan migrate:status` reported the existing migrations as ran. No destructive database command was executed, the development `ecommerce` data was not reset or modified, no external provider request was made, and `D:\uni-shop-project\front` was not changed. This audit added documentation artifacts only.

## 48. Final Verdict

The project demonstrates strong correctness-oriented architecture and substantial real MySQL concurrency evidence. It is ready for a controlled production-like performance-validation phase, not for an unconditional capacity claim.

`SCALABILITY READINESS: GOOD WITH IDENTIFIED BOTTLENECKS`

`RACE CONDITION SAFETY: STRONG FOR VERIFIED CORE FLOWS; PARTIAL COVERAGE FOR CART, OTP, SLUG, AND REDIS RUNTIME`

`PRODUCTION CAPACITY CERTIFICATION: PENDING PRODUCTION-LIKE LOAD TEST`
