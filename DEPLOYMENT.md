# Deployment Notes

## Cache stampede and refresh-ahead operations

Production should use Redis for cache, locks, and queues, with Horizon supervising Redis queue workers. The database cache backend remains supported locally, but Redis is preferred when public cache lock contention is material. Storefront reads use stale-while-revalidate plus atomic locks; narrow request-triggered refresh-ahead applies only to canonical product and blog archive page-one keys. Monitor hard misses, lock contention, builder duration, and refresh-job failures through `cache:storefront-status` and Horizon. Refresh-ahead is non-critical and remains on the low-priority `cache-refresh` queue. Product and Post admin edits also enqueue targeted after-commit detail refresh jobs on this queue; production Horizon must supervise it. For controlled local database-queue validation, use `php artisan queue:work database --queue=cache-refresh`.

## Storefront Query Cache and Queue

Local development uses the database cache store and database queue by default:

```dotenv
CACHE_STORE=database
QUEUE_CONNECTION=database
```

The storefront query-cache backend is selected separately by the persisted Core Site Setting `cache.store`. It selects only `database` or `redis`; Redis host, port, credentials, TLS, and database indexes remain deployment environment configuration (`REDIS_*`) and must never be stored in Site Settings.

Production should use Redis for both the configured storefront cache backend and queue connection:

```dotenv
QUEUE_CONNECTION=redis
REDIS_CACHE_CONNECTION=cache
REDIS_QUEUE_CONNECTION=queue
```

The `cache-rebuild` queue is intentionally isolated from normal commerce work. Cache rebuilds are asynchronous, use build-before-swap generations, and must never be replaced with a global cache flush.

The cache manifest tracks only storefront generation keys, allowing the asynchronous pruner to retain the active and immediately previous successful generation while removing older entries after a 24-hour grace period. Terminal rebuild history is retained for 60 days; pending, queued, and running runs are never pruned.

## Local Windows

Use the database cache and database queue locally. Horizon is installed for production compatibility but cannot run on the Windows PHP build because Horizon requires `pcntl` and `posix`.

On a fresh Windows clone, install the locked dependencies with:

```text
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

Do not run `php artisan horizon` locally. The ignored requirements are a local installation exception only; they do not make Horizon executable and must not be used to validate a production deployment.

## Scheduler and Horizon

Run one Laravel scheduler trigger every minute in production. The scheduled cache-rebuild recovery task is protected with Laravel overlap and single-server safeguards.

Horizon monitors Redis queues only; it does not monitor database queue workers. Production Linux must provide Redis plus PHP `pcntl` and `posix`. Verify real production requirements before starting workers:

```text
composer check-platform-reqs
```

In a Redis-backed production deployment, run Horizon under the host process supervisor:

```text
php artisan horizon
```

During a deploy, terminate Horizon gracefully so the process supervisor can restart it with the new code:

```text
php artisan horizon:terminate
```

For database-backed local or non-Horizon workers, restart long-lived queue processes after deploying cache-refresh code before processing the queue:

```text
php artisan queue:restart
```

Queue workers keep application classes in memory; a restart is required for them to load the deployed targeted-detail refresh implementation.

Local database-queue development does not run Horizon and remains supported without Redis.
