# Controlled Development Database Reconstruction — 2026-09-04

## 1. Incident Baseline

Recovery status: `DEVELOPMENT DATABASE RECOVERY: FAILED — NO RECOVERABLE SOURCE FOUND`

The original development rows could not be recovered from available backups or binlogs. This task reconstructed only deterministic repository-backed demo/bootstrap data; it did not represent that data as historical recovery.

Damaged snapshot preserved at:

`D:\uni-shop-project\db\ecommerce_damaged_after_accidental_reset_2026-09-04.sql`

Pre-write snapshot created at:

`D:\uni-shop-project\db\backups\ecommerce_pre_reconstruction_2026-09-04_223817.sql`

## 2. Safety Guard

* Testing uses PHPUnit's isolated SQLite/in-memory configuration and now fails immediately if a testing application resolves to `ecommerce` or `production`.
* Development-only demo commands refuse a testing process configured for the development database.
* A runtime Artisan command-start guard refuses `migrate:fresh`, `migrate:refresh`, `migrate:reset`, and `db:wipe` against local/development MySQL database `ecommerce`; normal `migrate` remains allowed.
* Browser smoke used the local development server only; no browser test was configured for the testing database.
* No destructive command was executed during reconstruction.

## 3. Backup Strategy

Current pre-write backup: `D:\uni-shop-project\db\backups\ecommerce_pre_reconstruction_2026-09-04_223817.sql` (130,614 bytes).

Backup command: `php artisan db:backup-development`

Location: `D:\uni-shop-project\db\backups\`

The command is local/development-only, requires MySQL database `ecommerce`, uses `mysqldump` with a timestamped non-overwriting filename, and reads any configured password through the process environment rather than committed source.

Restore guidance: stop application writes, validate the target database identity, and import a selected timestamped dump only after explicit operator approval. Never use a dump as a reason to run a destructive reset against the working database.

## 4. Binary Logging

Current: `log_bin=OFF`; MariaDB reports no binary logs. Point-in-time recovery is unavailable.

Recommended manual configuration in `C:\xampp\mysql\bin\my.ini` under `[mysqld]` (review with the operator first):

```ini
server-id=1
log_bin=C:/xampp/mysql/data/mysql-bin
binlog_format=ROW
```

Restarting MariaDB is required for this change. It was not applied automatically during this task.

## 5. Schema

`php artisan migrate:status` reports all 98 migrations as `Ran`. No migration was needed or run.

## 6. Authorization

Roles reconstructed through `RolesAndPermissionsSeeder`:

* `super-admin`
* `admin`

Permissions: 74 deterministic application permissions; each role has the seeded permission set (148 role-permission assignments).

### Primary Super Admin

Email: `nsarreshtehdari87@gmail.com`

Role: `super-admin`

Created/Reused: **YES**

Filament access: **VERIFIED**

Password: `Configured from user-supplied bootstrap credential; not recorded in report.`

Focused verification confirmed exactly one matching non-deleted user, active status, role assignment, Filament panel authorization, and a successful Laravel `Hash::check()` without printing or storing the plaintext credential.

## 7. Settings

No settings rows were invented. The registered `SettingRegistry` defaults remain authoritative (`shipping.mode=calculator`, fixed rate `0`, nullable origin, empty package list, and nullable default tax class). Origin/package/tax values with no recoverable repository-defined persisted value remain unset and must be configured deliberately before shipping/tax operations.

## 8. Products

Reconstructed with `php artisan demo:storefront-products`:

* Products: 6 (5 published demo products and 1 draft visibility control)
* Variations: 15 (6 Aurora variable + 9 Luna variable)
* Product images: 8
* Categories: 4
* Brands: 3
* Tags: 3
* Attributes: 4; attribute values: 11
* Inventory transactions: 15; no inventory reservations were created

Second run: idempotent; 0 created and all demo records reused (4 categories, 3 brands, 3 tags, 6 products, 4 attributes, 11 values, 15 variations, 8 images); no duplicate records were introduced.

## 9. Blog

Reconstructed with `php artisan demo:storefront-blog`:

* Posts: 21 total — 18 published, 2 scheduled controls, 1 draft control
* Post categories: 5
* Post tags: 10
* Published-post/category links: 21; post/tag links: 48
* Local deterministic images reused/created by the command as applicable

Second run: idempotent; 0 created and all 5 categories, 10 tags, 18 published posts, 2 scheduled controls, 1 draft control, and images were reused.

## 10. Coupons

No Coupon dataset was fabricated. No deterministic Coupon fixture/command was present that was required for reconstruction, so the coupons table remains empty.

## 11. Historical Data

Not recovered: original customers/users beyond the newly bootstrapped development admin, Addresses, Carts, Orders/order items/history, Payments/transactions, Shipments/history, Coupon usages, inventory reservations/ledger history, settings values, and any other pre-incident business records. The demo catalog/blog and bootstrap user are newly reconstructed development data, not historical records.

## 12. Filament Smoke

The primary development super-admin passed direct `canAccessPanel()` authorization for the `admin` panel. Admin login route and panel routes were present; the local HTTP smoke returned `200` for `/admin/login`. Product, Blog, Settings, and Coupons routes were confirmed present via route inventory. No broad mutating Filament QA was performed.

## 13. Storefront Smoke

With the local development server, read-only HTTP smoke returned `200` for:

* `/`
* `/products`
* `/products/demo-aurora-velvet-perfume`
* `/blog`
* `/login`
* `/admin/login`

No Order or Payment was created.

## 14. Development DB Counts

Non-zero table counts after reconstruction:

| Table | Count |
|---|---:|
| users | 1 |
| roles | 2 |
| permissions | 74 |
| model_has_roles | 1 |
| role_has_permissions | 148 |
| products | 6 |
| product_variations | 15 |
| product_images | 8 |
| categories | 4 |
| brands | 3 |
| tags | 3 |
| attributes | 4 |
| attribute_values | 11 |
| inventory_transactions | 15 |
| posts | 21 |
| post_categories | 5 |
| post_tags | 10 |
| post_post_category | 21 |
| post_tag | 48 |

Bookkeeping also contains `migrations=98`, `cache=2`, and `sessions=7`. Other application tables remain at zero.

## 15. Automated Test DB Protection

Implemented `App\Support\DatabaseSafetyGuard` and an application bootstrap check in `Tests\TestCase`. Focused test result: **1 passed, 2 assertions**. The full suite passed with the guard active, demonstrating that the isolated test configuration is not `ecommerce`.

## 16. Full Test Suite

`php artisan test --compact`: **348 passed (2,149 assertions), 0 failures, 0 skipped**, duration 74.09 seconds.

The runner emitted a non-failing Pest result-cache permission warning under `vendor/pestphp/pest/.temp`; it did not affect test execution.

## 17. Final Development Backup

`D:\uni-shop-project\db\backups\ecommerce_2026-09-04_224446.sql` (166,613 bytes) was created with `php artisan db:backup-development` after validation. It does not overwrite the incident snapshot or pre-write backup.

## 18. Files Changed

* `app/Support/DatabaseSafetyGuard.php`
* `app/Console/Commands/BackupDevelopmentDatabase.php`
* `app/Console/Commands/CreateStorefrontDemoBlog.php`
* `app/Console/Commands/CreateStorefrontDemoProducts.php`
* `app/Providers/AppServiceProvider.php`
* `tests/TestCase.php`
* `tests/Feature/Safety/TestDatabaseSafetyGuardTest.php`
* `DEVELOPMENT_DATABASE_RECONSTRUCTION_2026-09-04.md`

Existing cumulative working-tree changes from earlier tasks were preserved and not reverted.

## 19. Database Commands Executed

* `php artisan migrate:status` (before and after reconstruction; read-only, all migrations already applied)
* `php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder --no-interaction`
* `php artisan demo:storefront-products` (twice)
* `php artisan demo:storefront-blog` (twice)
* `php artisan test --compact` (isolated test DB)
* `php artisan db:backup-development` (final dump)

No `migrate:fresh`, `db:wipe`, `migrate:reset`, `migrate:refresh`, schema drop, truncate, destructive reset, or development Order/Payment operation was performed.

## 20. Raw Frontend

`D:\uni-shop-project\front` was not modified.

## Final status

`DEVELOPMENT DATABASE RECONSTRUCTION: PASS WITH MISSING HISTORICAL DATA`
