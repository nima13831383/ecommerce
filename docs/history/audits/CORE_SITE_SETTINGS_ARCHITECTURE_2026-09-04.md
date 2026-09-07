# Core Site Settings Architecture

## 1. Previous Architecture

The application already had a `Setting` model, `SettingsService`, a small `SettingRegistry`, and a value-edit-only Filament resource. Existing installations could nevertheless have no row for a registered key, which made a reconstructed database fall back to implicit defaults (notably shipping configuration).

## 2. New Settings Contract

`SettingRegistry` is the definition authority. Definitions contain key, group, type, default, nullability, validation rules, options, description, and a `core` flag. Persisted rows contain the current value and type. The existing `(group, key)` unique constraint remains the database invariant.

## 3. Registry

The registry exposes six core definitions and `coreDefinitions()` for migrations and operational tooling. No environment secrets are registered for database storage.

## 4. Core Setting Inventory

| Key | Group | Type | Default | Notes |
| --- | --- | --- | --- | --- |
| `default_tax_class_id` | tax | integer | null | Active tax class reference |
| `shipping.mode` | shipping | string | calculator | calculator, fixed, or free |
| `shipping.origin_province_id` | shipping | integer | null | Single global origin; site-specific |
| `shipping.origin_city_id` | shipping | integer | null | Must belong to the origin province |
| `shipping.fixed_rate_amount` | shipping | money | 0 | Integer Rial |
| `shipping.packages` | shipping | json | [] | Valid configured package definitions |

## 5. Database Migration

Migration `2026_09_04_235500_persist_core_site_settings` inserts each missing core row with its declared default using `insertOrIgnore`. Its rollback intentionally does not delete rows, preserving operator values and unknown legacy settings.

## 6. Existing Value Preservation

The development database was backed up before migration at `D:\uni-shop-project\db\backups\ecommerce_2026-09-04_235957.sql`. The migration completed without changing existing shipping values; the previously missing tax row was added. A post-migration backup was created at `D:\uni-shop-project\db\backups\ecommerce_2026-09-05_000029.sql`.

## 7. Fresh Installation

Isolated test migrations complete with all six core rows present, including intentional null rows. The core contract test verifies declared types, defaults, and `(group, key)` uniqueness.

## 8. SettingsService

`SettingsService` is the application read/write authority. It now distinguishes a missing row from a persisted null value, synchronizes missing core rows without overwriting values, reports registered/persisted/missing/unknown/incomplete state, rejects unknown updates, preserves integer Rial money, and keeps JSON round-trips typed. Switching into calculator mode is rejected when the required global origin and packages are incomplete; free/fixed modes retain their existing semantics.

## 9. Filament

The Site Settings resource remains value-edit-only. Key, group, and type are disabled metadata; create and delete are explicitly unavailable. Known values are written through `SettingsService`, and mode options are sourced from the registry. Unknown legacy rows remain inspectable but cannot be updated.

## 10. Shipping Settings

Shipping continues to use one global origin, the existing geography loader, configured packages, and calculator/fixed/free modes. Development currently has its site-specific shipping configuration persisted; this is not a universal default and requires separate production confirmation. No temporary development origin or package was baked into the registry defaults.

## 11. Cache

No settings cache existed before this change, so no new cache layer was introduced. Reads remain database-backed and therefore immediately reflect updates.

## 12. settings:sync

`php artisan settings:sync` adds only missing registered core rows and reports added/existing keys. `php artisan settings:sync --dry-run` reports the same plan without writing. Repeated execution is idempotent and never overwrites an existing value.

## 13. settings:status

`php artisan settings:status` reports registered and persisted counts, missing keys, unknown legacy rows, and shipping keys needing configuration. It does not print setting values or secrets. Development result after migration: 6 registered, 6 persisted, none missing, none unknown, none needing configuration.

## 14. AGENTS.md

The root `Agents.md` now contains a permanent Site Settings Architecture section covering registry definitions, additive migrations, value-only Filament editing, SettingsService authority, safe synchronization/status commands, secret handling, shipping defaults, isolated testing, and development database safety.

## 15. Focused Tests

Independent settings contract run 1: **7 passed, 40 assertions, 0 failures**.

Independent settings contract run 2: **7 passed, 40 assertions, 0 failures**.

Settings plus existing SettingsService tests: **11 passed, 50 assertions, 0 failures**.

Existing Filament Settings/Tax regression: **15 passed, 95 assertions, 0 failures**.

## 16. Full Suite

Isolated full suite: **357 passed, 2,202 assertions, 0 failures**. Pest emitted only its existing result-cache permission warning; no test failed or was skipped.

## 17. Pre-Migration Backup

Completed with `php artisan db:backup-development` before the additive migration.

## 18. Post-Migration Backup

Completed with `php artisan db:backup-development` after migration and status verification.

## 19. Migration Status

`php artisan migrate` applied the new migration once. A subsequent run reported **Nothing to migrate**. `php artisan migrate:status` reports the migration as **Ran** and all six core setting rows are present.

## 20. Database Safety

No destructive database command was run. The `ecommerce` database was not reset, wiped, truncated, or recreated. Automated tests used the isolated test database.

## 21. Files Changed

Production/settings:

- `app/Settings/SettingDefinition.php`
- `app/Settings/SettingRegistry.php`
- `app/Models/Setting.php`
- `app/Services/Settings/SettingsService.php`
- `app/Filament/Resources/Settings/SettingResource.php`
- `app/Filament/Resources/Settings/Schemas/SettingForm.php`
- `app/Console/Commands/SyncSiteSettings.php`
- `app/Console/Commands/SiteSettingsStatus.php`
- `database/migrations/2026_09_04_235500_persist_core_site_settings.php`
- `Agents.md`

Tests:

- `tests/Feature/Settings/SiteSettingsContractTest.php`
- updates to existing settings/tax/Filament fixtures so registered rows are preserved rather than recreated.

## 22. Raw Frontend

`D:\uni-shop-project\front` was not modified.

## 23. Future Developer Workflow

Register the complete definition first, add an additive missing-row migration, expose only value editing in Filament, route all reads/writes through `SettingsService`, and add isolated migration/typing/validation/preservation tests. Use `settings:sync` for safe operational repair and `settings:status` for configuration visibility. Never add secrets or temporary development shipping values to universal defaults, and never reset the development database.

## Runtime Smoke

The authenticated Filament Site Settings route loaded successfully over the local development HTTP server after migration. The settings status command and service readback confirmed all six registered rows and preserved shipping configuration. Existing Filament Livewire edit tests verified value persistence and validation through the normal UI path.

## Final Status

**CORE SITE SETTINGS ARCHITECTURE: VERIFIED PASS**
