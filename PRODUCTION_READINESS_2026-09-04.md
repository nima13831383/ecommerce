# Production Readiness — 2026-09-04

## 1. Final Readiness Status

The application is production-deployable for the verified storefront and commerce scopes after applying the public-media contract and deployment hardening below. Live paid transactions remain intentionally unavailable until a real payment provider is selected.

`PRODUCTION READINESS: VERIFIED PASS EXCEPT REAL PAYMENT PROVIDER`

## 2. Media Storage Root Cause

`BROWSER-BUG-005` was caused by ProductImage using `filesystems.default`. Development configured that default as the private `local` disk, while storefront URL generation expected the public `/storage` mapping. Blog code independently assumed the `public` disk. Browser QA therefore needed a temporary mirror of existing demo files.

## 3. Final Product Media Contract

- Disk: `config('media.public_disk')`, environment key `STOREFRONT_MEDIA_DISK`, default `public`.
- Physical location for the standard disk: `storage/app/public/...`.
- DB path: relative disk paths only, for example `products/123/image.webp`.
- URL: `Storage::disk(config('media.public_disk'))->url($path)`.
- Public link: `public/storage` → `storage/app/public`, created by `php artisan storage:link`.
- Filament Product uploads, ProductImage cleanup, demo Product media, API resources, and Blade presenters all use the same configured disk.

## 4. Final Blog Media Contract

Post featured images use the same configured public media disk and relative `blog/...` paths. Filament Post uploads, the demo Blog command, and Blog Blade presenters use `config('media.public_disk')`. Blog posts have soft delete only; no force-delete media action exists, so soft deletion preserves the file for restoration.

## 5. Existing Media Compatibility

Existing records may still reference files under the former private local disk. No files were mass-moved or deleted. The reconciliation command copies recognized ProductImage and Post featured-image files to the configured public disk, leaves the source intact, and skips an existing destination rather than overwriting it. Missing and invalid paths are reported and use existing storefront placeholders.

## 6. Media Reconciliation Tool

- Command: `php artisan media:reconcile-public`.
- Dry-run: `php artisan media:reconcile-public --dry-run`.
- Source override: `--source-disk=local` (default `STOREFRONT_LEGACY_MEDIA_DISK=local`).
- Idempotency: destination-existing files are skipped; reruns do not overwrite or duplicate files.
- Safety: only ProductImage paths and Post `featured_image` records are inspected; paths must be relative; streams are copied and destination existence is verified; source files remain intact.

## 7. storage:link

The repository uses Laravel's standard link configuration:

`public/storage` → `storage/app/public`.

`php artisan storage:link` is sufficient for the standard local public disk. The current Windows development link is a junction; production should use the normal Laravel command on the Linux host.

## 8. APP_URL

The public disk URL is based on `APP_URL`. Development currently uses a local URL. Production must set the actual externally reachable value, for example:

`APP_URL=https://actual-domain.example`

No host, port, localhost URL, or Windows path is hardcoded in application code.

## 9. Session / Cookie Production Configuration

The current session driver is database by default and is configurable through `SESSION_DRIVER`. Production should use a shared database or Redis session store for multiple application nodes, set `APP_DEBUG=false`, enforce HTTPS, set `SESSION_SECURE_COOKIE=true`, retain `SESSION_HTTP_ONLY=true`, use an appropriate `SESSION_SAME_SITE` (normally `lax` for same-site Blade SSR), and set `SESSION_DOMAIN` only when the deployment requires a shared parent domain. No production domain is hardcoded.

## 10. Cache

- Local: database cache by default; tests use the array store.
- Production: configure `CACHE_STORE=redis` (or another shared supported store) and the existing `REDIS_*` connection variables when centralized cache/locks are required. Keep `CACHE_PREFIX` unique per application. No cache redesign was introduced.

## 11. Queue

Production requires a persistent worker when `QUEUE_CONNECTION` is not `sync`. The current default is the database queue (`jobs` table); Redis is supported through the existing `REDIS_QUEUE_*` settings. Relevant queued work includes customer lifecycle notification creation/delivery, with three attempts and backoff. Workers must be supervised by the deployment platform and restarted after deploy with the normal Laravel queue restart procedure. `retry_after` must remain longer than any configured worker/job timeout.

## 12. Scheduler

Run `php artisan schedule:run` every minute (or the equivalent long-running scheduler process). The registered task is `inventory:expire-reservations`, scheduled every minute with `withoutOverlapping()` and `onOneServer()`.

## 13. Inventory Reservation Expiry

Reservation expiry remains registered and domain semantics were not changed. This scheduler is required because unpaid Orders can hold active reservations until expiry.

## 14. Payment Production Safety

The fake gateway is available only to local/testing application environments through the registry binding. `StorefrontPaymentGateway` also rejects the `fake` alias outside local/testing, even if `STOREFRONT_PAYMENT_GATEWAY=fake` is set. A production-like test confirms the alias resolves to unavailable. No real provider is configured; live paid transactions are an intentional launch blocker.

## 15. Filesystem Permissions

The application process must be able to write `storage` (including framework cache, sessions, logs, and public media) and `bootstrap/cache`. The public storage target must be served through the `storage:link` mapping. Numeric permissions are hosting-specific and are not prescribed here.

## 16. Laravel Optimization Commands

- `config:cache`: passed.
- `route:cache`: passed after replacing the non-cacheable dashboard closure with a serializable redirect route.
- `view:cache`: passed with elevated filesystem access after the sandbox-owned Windows compiled-view file initially returned `Access is denied`; `optimize:clear` restored the normal development state. On production, ensure `storage/framework/views` is writable before running `view:cache`.

## 17. Deployment Command Order

1. Deploy application code and install production dependencies.
2. Inject production environment/secrets (`APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, database, media, session, cache, queue, and mail settings).
3. Back up the database and use the deployment maintenance strategy if required.
4. Run `php artisan migrate --force`.
5. Run `php artisan storage:link`.
6. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` after confirming writable cache paths.
7. Start/restart persistent queue workers.
8. Ensure one-server scheduler coordination and a once-per-minute `schedule:run` trigger.
9. Run the smoke checklist below and verify logs/failed jobs.

Never use `migrate:fresh`, `db:wipe`, truncation, or schema reset for deployment.

## 18. Production Smoke Checklist

- `/`, `/products`, and one public Product Detail return 200.
- A Product image and Blog featured image return 200 through the public storage URL.
- `/blog` returns published content only.
- Login page and authenticated session behavior work with HTTPS cookies.
- `/admin` responds according to existing Filament authorization.
- `/api/v1/products` returns its existing JSON contract.
- Queue worker is running and failed-job handling is observable.
- `php artisan schedule:list` shows reservation expiry.
- With production-like environment, fake payment is unavailable until a real provider is configured.

## 19. Tests

Added `tests/Feature/Production/ProductionReadinessTest.php`:

- shared public Product/Blog disk and URL contract;
- configured-disk ProductImage deletion;
- dry-run, copy, idempotency, and source-preserving media reconciliation;
- fake gateway exclusion in production.

Focused result: **5 passed (22 assertions)**.

## 20. Storefront Regression

Product media, Product listing/detail, API catalog, demo Product/Blog commands, and relevant Filament media/delete tests: **27 passed (315 assertions)**.

## 21. Full Isolated Suite

`php artisan test --compact`: **328 passed (2,081 assertions)**, 0 failures, 0 skipped.

The existing Pest result-cache permission warning is non-fatal and does not affect test results.

## 22. Migration

- `php artisan migrate`: Nothing to migrate.
- `php artisan migrate:status`: all migrations `[1] Ran`.

## 23. Pint

`vendor/bin/pint --dirty`: passed.

## 24. git diff --check

Passed with no whitespace errors.

## 25. Production Files Changed

- `config/media.php`
- `.env.example`
- `app/Models/ProductImage.php`
- `app/Filament/Resources/Products/Schemas/ProductForm.php`
- `app/Filament/Resources/Posts/Schemas/PostForm.php`
- `app/Http/Resources/Api/V1/ProductVariationResource.php`
- `app/Console/Commands/CreateStorefrontDemoBlog.php`
- `app/Console/Commands/ReconcilePublicMedia.php`
- `resources/views/storefront/components/blog-card.blade.php`
- `resources/views/storefront/blog/index.blade.php`
- `resources/views/storefront/blog/show.blade.php`
- route-cache-safe dashboard redirect in `routes/web.php`
- media test updates and `tests/Feature/Production/ProductionReadinessTest.php`
- this report

Existing unrelated working-tree changes from prior phases were preserved.

## 26. Raw Frontend Source

`D:\uni-shop-project\front` was not modified.

## 27. Remaining Launch Blockers

- A real Payment provider is not configured; live paid transactions cannot launch yet.
- Deployment must set the real `APP_URL`, configure shared production state where needed, run `storage:link`, and provide writable `storage`/`bootstrap/cache` paths.
- Existing legacy media should be reconciled before launch using the dry-run then normal command; missing files require content-owner review.

`PRODUCTION READINESS: VERIFIED PASS EXCEPT REAL PAYMENT PROVIDER`
