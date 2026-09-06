# Storefront Cache Stampede / Revalidation Strategy Audit — 2026-09-06

## 1. Current Cache Strategy

**Fresh:** `StorefrontQueryCache::remember()` reads the selected database/Redis store. An envelope with `fresh_until >= now` returns its `value` immediately.

**Stale:** once fresh time has passed but `stale_until >= now`, the first request that acquires `lock:<key>` runs the builder synchronously in that HTTP request. Concurrent lock losers return the stale `value` immediately. A successful put resets fresh/stale expiry.

**Hard miss:** with no usable envelope, the lock owner builds synchronously. A lock loser calls Laravel `Lock::block(2)`, retrying every 250 ms. If the owner finishes within that two-second window, the loser rereads and returns the new value. Otherwise Laravel throws `LockTimeoutException`; this path is not caught by the adapter.

**Lock:** the lock TTL is the persisted `cache.lock_seconds` value, currently defaulted to 30 seconds (allowed 5–300). Product and Blog fresh TTL defaults are each 3600 seconds; the stale window defaults to 86400 seconds.

## 2. Current Stampede Protection

**Already protected:** fresh hits; stale lock contention; one normal-duration hard-miss builder; manual full-generation rebuild request deduplication; generation swaps; retained generation cleanup.

**Not fully protected:** hard-miss waiters after two seconds receive an uncaught lock timeout rather than stale or a controlled response. A builder exceeding its 30-second lock lease can allow another worker to acquire the lock and rebuild concurrently. There is no refresh-ahead, TTL jitter, hot-key detection, or cache metrics.

## 3. Current Hard-Miss Risk

On first use of a key, a new arbitrary filter/search combination, backend data loss/restart, or an expired stale window, one request builds. All other same-key requests block for at most two seconds. For a builder slower than two seconds, they fail; they do **not** execute the builder themselves before lock expiry. If a build exceeds the 30-second lease, however, a second owner can start a duplicate build. The current six-worker isolated MySQL regression proves one builder for its controlled fast build, not a guarantee for arbitrary slow builders or 1,000+ waiters.

## 4. Blocking Lock Analysis

**Advantages:** single-flight behavior protects the database when a build completes within lock lease; simple; backend-neutral through Laravel locks; works across application nodes with shared database/Redis stores.

**Disadvantages:** all hard-miss users wait; the current two-second cap becomes an error path; database locks add `cache_locks` contention; a short lease risks duplicate ownership; a long lease amplifies waiting after a crashed owner.

**Fit:** appropriate only as the hard-miss safety net, not as the primary user experience for popular cache expiry.

## 5. Stale-While-Revalidate Analysis

**Advantages:** stale lock losers have near-cache-hit latency, one request refreshes, a failed refresh leaves the existing stale envelope usable until `stale_until`, and the model is shared-store/horizontally scalable.

**Disadvantages:** the lock winner still pays synchronous builder latency; stale Product/Post metadata can remain for the configured window; no visibility currently records stale hits/refresh duration/failures.

**Current implementation overlap:** this is already effectively implemented for stale entries, although it is not deferred/background SWR: the winner refreshes synchronously.

**Fit:** strong baseline and should be preserved.

## 6. Proactive Refresh Analysis

**Advantages:** avoids users encountering the fresh-to-stale transition for known hot keys and reduces the hard-miss probability after planned warming.

**Disadvantages:** broad proactive refresh is wasteful: Product archives vary by page/category/brand/search/sort/filter; every Product detail becomes prohibitive at 10,000–100,000 products; queue work could compete with manual generation rebuilds; current `cache-rebuild` jobs warm whole generations rather than individual active-generation keys.

**Fit:** do not introduce broad scheduler-driven refresh now. Consider only a future, explicitly whitelisted hot-key mechanism after metrics exist.

## 7. Request-Triggered Refresh-Ahead

This is preferable to a global scanner for a future small hot-key set: a still-fresh request near expiry returns immediately, then one lock owner dispatches an idempotent active-generation single-key refresh. It naturally refreshes only keys with real traffic and needs no global hot-key registry. It still needs a queue-health fallback and the same lock; duplicate jobs must reread under the lock.

It is not present today. It must not use `StorefrontCacheRebuildService::request()`, because that creates a competing whole-domain generation swap.

## 8. Scheduled Refresh-Ahead

A periodic scheduler can safely refresh a fixed canonical whitelist (for example `/products` page 1 and `/blog` page 1), guarded by `onOneServer()`/`withoutOverlapping()` and existing locks. It is unsuitable for all filters/searches/details and would otherwise require access metadata plus scan/index work. The current scheduler only recovers rebuilds, prunes generations, and snapshots Horizon; it does not refresh catalog keys.

## 9. Probabilistic Early Refresh

Probabilistic early recomputation would distribute expiration without a scheduler, but adds difficult-to-explain probability, per-key age metadata, tuning, and test/observability requirements. It is backend-neutral in theory but does not solve cold hard misses. For this repository it is less transparent than request-triggered refresh for a small static hot-key set and is premature.

## 10. TTL Jitter

Current entries receive deterministic domain TTLs. A full manual generation warm writes many Product details/pages close together, so expiry waves can synchronize. Small bounded jitter would reduce a coordinated wave, but is secondary: stale serving already cushions expiry, while jitter complicates exact cache-age expectations and tests. Evaluate it only with hit/miss metrics; do not add it before resolving hard-miss behavior.

## 11. Product Archive Analysis

**Hot/predictable:** canonical newest archive pages, especially page 1; possibly a small set of observed major-category first pages.

**Cold/high-cardinality:** `search` (100-character input), page, category/categories (up to 20 slugs), brand/brands (up to 20), type, name sort, and combinations. `in_stock`, price range, and price sort deliberately bypass cache because they depend on live inventory/effective-price SQL. The public request fixes per-page to the persisted catalog setting, but filters/page still create substantial cardinality. Do not proactively warm arbitrary archives.

## 12. Product Detail Analysis

Each public slug is one detail key. Warming every detail is tolerable for small catalogs during an explicit generation rebuild, but continuous refresh-ahead for 1,000, 10,000, or 100,000 details scales linearly in DB, queue, manifest, and cache work while traffic is usually highly skewed. Use SWR+lock by default; only observed top details could ever enter a future bounded hot-key policy.

## 13. Blog Analysis

Blog archive keys include category, search, page, and per-page; details are one key per slug. `categories()` and `related()` are currently uncached. Blog content usually has lower write/traffic pressure and is more tolerably stale than commerce metadata. Canonical Blog page 1 is the only plausible early-refresh candidate; filtered searches and articles should remain SWR+lock unless metrics prove otherwise. The `published()` scope evaluates publication time while building; cached content remains subject to the intentional TTL/manual-rebuild policy.

## 14. Database Cache Implications

The local database backend performs envelope reads/writes in `cache` and locks in `cache_locks`. At a hard miss, thousands of waiters poll lock acquisition every 250 ms for up to two seconds, creating database lock-table pressure and potential request-worker saturation. Stale hits avoid that pressure after expiry. Database is correct for local/dev and moderate traffic but is not the preferred high-traffic lock backend.

## 15. Redis Implications

Redis reduces shared lock/cache latency and aligns with Horizon production queues. It remains backend-neutral because the adapter uses Laravel repository/lock APIs and manifest-based cleanup rather than scans/tags. Redis restart/eviction creates a hard-miss scenario, so it does not remove the need for safe cold-start behavior. Redis runtime remains unverified locally.

## 16. Manual Generation Rebuild Interaction

Manual admin rebuilds build an entire next generation and swap only on success. Future refresh-ahead must never create another generation or warm the pending manual target. It must refresh only the active generation's individual key under the same key lock. If a manual run is queued/running, the future refresh-ahead policy should yield to it or reread active generation, preventing competing writers and accidental swaps.

## 17. Failure Modes

For a stale entry, a builder exception leaves the stale envelope untouched; subsequent stale requests can retry while the stale window remains. For a hard miss, the owner error propagates and later requests retry. Queue delay/down does not affect current synchronous stale refresh; it would affect any future refresh-ahead, so such a design needs synchronous-on-stale fallback rather than deleting usable data. No strategy should delete a valid old envelope before a replacement is written.

## 18. High-Traffic Scenarios

| Scenario | Current behavior | Recommended future behavior |
| --- | --- | --- |
| 10 archive req/s | Fresh hits; at expiry, one synchronous refresh and stale for contenders | Current SWR baseline is sufficient |
| 1,000 simultaneous at TTL boundary | One stale winner builds; 999 get stale quickly | Same; optional hot-key refresh reduces winner latency |
| 10,000 after cache loss | One owner builds; waiters poll and most time out after 2 s; duplicate build possible after 30 s | Prewarm only canonical keys plus a controlled hard-miss waiter/fallback protocol |
| continuously hot key | Repeated hourly synchronous winner refresh | Later request-triggered refresh-ahead candidate |
| cold search/filter | One builder or two-second waiter failures; no value in prewarming | SWR+lock/hard-miss safety net only |
| builder fails | stale remains if present; hard miss errors | Preserve stale, emit metrics, retry later |
| queue delayed/down | Current stale refresh still works synchronously | Refresh-ahead must fall back to existing synchronous stale behavior |

## 19. Observability Gaps

Current operational status exposes backend readiness, generations, rebuild runs, and prune status. It does not record cache hits, fresh hits, stale hits, hard misses, lock acquired/contended/timeout, builder duration, builder failure, revalidation dispatch, or per-key access volume. These are prerequisite signals for a dynamic hot-key policy.

## 20. Complexity / Operational Cost

Keeping current SWR+lock has low incremental cost. A static canonical-key whitelist has modest queue/scheduler cost. Dynamic hot-key tracking, probabilistic refresh, or scanning every manifest entry would add state, cleanup, queue volume, failure modes, and production tuning without evidence that this catalog needs it. Database local mode makes broad background refresh especially unattractive.

## 21. Recommended Strategy

**D — narrowly scoped SWR + lock + proactive hot-key refresh**, delivered in stages:

1. Preserve current fresh/stale envelopes, generation swaps, manifest pruning, and atomic locks.
2. First repair and test the hard-miss/lock-lease behavior; this is a correctness and availability prerequisite.
3. Add request-triggered (not broad scheduled) refresh-ahead only for a small explicit canonical archive whitelist after metrics confirm traffic. Do not refresh all details, searches, or filter combinations.
4. Keep manual full-generation rebuilds separate from per-key active-generation refresh.

Until stage 2/metrics exist, the operational recommendation is effectively **B — current SWR + lock only**, not a broad refresh system.

## 22. Exact Recommended State Machine

```text
read active-generation envelope
if fresh:
    return fresh
    if key is whitelisted-hot and near expiry:
        atomically request an active-generation single-key refresh (future)

if stale:
    return stale immediately to lock losers
    one lock owner refreshes same active-generation key
    if background queue is healthy, it may refresh asynchronously (future)
    otherwise retain synchronous stale-owner fallback

if hard miss:
    one lock owner builds synchronously with a lease exceeding measured p99 build time
    waiters perform bounded reread/polling for the result
    on bounded-wait expiry, return a controlled transient/fallback response rather than an uncaught lock exception
    never let waiters independently build until the owner lease has safely ended

manual rebuild:
    build next generation completely -> mark succeeded -> switch active generation
    per-key refresh operates only on the active generation and never creates/switches generations
```

## 23. Which Cache Families Should Use Which Strategy

| Cache family | Strategy | Reason |
| --- | --- | --- |
| Product canonical archive | SWR+lock; future request-triggered refresh-ahead only for observed page 1/hot pages | Predictable but not all pages are hot |
| Product filtered archive | SWR+lock/hard-miss safety net | Search/filter cardinality is high; live stock/price variants bypass cache |
| Product detail | SWR+lock | One key per product; proactively refreshing every detail does not scale |
| Blog canonical archive | SWR+lock; possible future request-triggered page-1 refresh | Low-cardinality canonical key and content tolerates stale data |
| Blog filtered archive | SWR+lock/hard-miss safety net | Category/search/page combinations are not predictable enough |
| Article detail | SWR+lock | Detail cardinality grows with posts; stale content is generally acceptable |

## 24. Suggested Future Settings

Do not add settings now. If the staged strategy is approved after metrics, consider only: a hard-miss wait policy, a lock lease derived from measured builder duration, an optional refresh-ahead threshold/fraction, and a bounded canonical-key whitelist. Preserve existing TTL/stale settings and avoid per-key operator configuration.

## 25. Required Future Tests

* Hard miss with build longer than two seconds: controlled outcome, no uncaught lock timeout.
* Build longer than current lock TTL: no overlapping builder after lease policy repair.
* Stale winner failure: stale remains and later retry succeeds.
* Thousands-of-contenders simulation in isolated MySQL/Redis-capable CI where practical, including loser latency/result class.
* Refresh-ahead single dispatch, duplicate suppression, manual-generation interaction, queue-down fallback, and active-generation-only write tests.
* TTL jitter boundaries, if later approved.

## 26. Required Future Metrics

Fresh/stale/hard-miss counts; lock acquired/contended/timed-out; builder duration/success/failure; stale age served; refresh-ahead enqueued/completed/failed; per-key access frequency; queue latency; Redis/database lock latency; generation-rebuild duration and swap frequency.

## 27. Risks

The immediate risk is high-concurrency hard-miss availability, not stale entry stampede. Overly aggressive refresh-ahead risks database/queue load and can conflict with manual generations. Price changes are intentionally stale until TTL/manual rebuild, while sale-window evaluation uses current time against cached sale fields; inventory is overlaid live and price/stock-sensitive listing predicates bypass cache. Preserve these authority boundaries.

## 28. Final Verdict

`CACHE STAMPEDE STRATEGY AUDIT: COMPLETE`

`RECOMMENDED STRATEGY: D — narrow SWR + atomic lock + request-triggered refresh-ahead for measured canonical hot keys, after hard-miss/lock-lease repair; no broad proactive refresh or probabilistic expiration now.`
