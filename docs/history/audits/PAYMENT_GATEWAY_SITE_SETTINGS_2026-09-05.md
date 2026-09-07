# Payment Gateway Site Settings — 2026-09-05

## 1. Previous Payment Config

Normal ZarinPal runtime selection, merchant ID, and sandbox mode previously read legacy values from `config/payment.php`, populated by `STOREFRONT_PAYMENT_GATEWAY`, `ZARINPAL_MERCHANT_ID`, and `ZARINPAL_SANDBOX`.

## 2. New Payment Settings

| Key | Type | Default | Encrypted | Purpose |
| --- | --- | --- | --- | --- |
| `payment.default_gateway` | string enum | `null` | No | Selected storefront gateway; currently only `zarinpal` is selectable. |
| `payment.zarinpal.enabled` | boolean | `false` | No | Explicit operational enablement. |
| `payment.zarinpal.sandbox` | boolean | `false` | No | Local/testing sandbox mode. |
| `payment.zarinpal.merchant_id` | secret string | `null` | Yes | ZarinPal merchant credential. |

## 3. Settings Registry

`SettingDefinition` now carries explicit `secret` metadata. The registry has exactly 12 core settings: the prior eight plus the four `payment` settings above. Fake is intentionally absent from the admin-selectable gateway options.

## 4. Additive Migration

`2026_09_05_130642_add_payment_gateway_core_settings.php` inserts only missing payment rows with safe defaults. It neither reads environment values nor writes credentials, and its rollback deliberately preserves operator data.

## 5. Fresh Installation

The isolated fresh-migration Feature tests confirm all 12 core settings are persisted. Fresh payment state is disabled, sandbox-off, no default gateway, and no merchant credential; the runtime resolver is unavailable.

## 6. Merchant Encryption

DB plaintext: **NO**

Runtime decrypt: **YES**

`SettingsService` centrally encrypts secret values with Laravel `Crypt` before persistence and decrypts them only for authorized runtime use. A focused runtime check against development printed only `merchant_db_encryption=verified`; no credential value or ciphertext was recorded.

## 7. Legacy Env Import

`payment:import-zarinpal-env` is an explicit operator command with `--dry-run` and `--force`.

- Dry run: reports only gateway/merchant presence/sandbox state and writes nothing.
- Import: writes through `SettingsService`, encrypts the merchant credential, and enables ZarinPal only when the legacy configuration is intentional and valid.
- Overwrite behavior: an existing DB merchant credential is retained unless `--force` is supplied.

The current valid local legacy configuration was inspected with `--dry-run` and then explicitly imported. No secret was displayed.

## 8. Runtime Authority

`PaymentGatewayConfiguration` is the sole runtime adapter for these operational settings. It reads `SettingsService`; `PaymentGatewayRegistry` constructs ZarinPal only from a valid active DB-backed configuration. The SDK receives a small resolved configuration DTO. Static `config/payment.php` retains only IRR currency and legacy import/diagnostic input.

- Default gateway: database setting.
- Enabled: database setting.
- Sandbox: database setting.
- Merchant: encrypted database setting.

## 9. Filament

The existing value-only Site Settings resource has a Persian `پرداخت` group, gateway select, enabled/sandbox toggles, and a masked merchant input. The input hydrates blank, blank saves preserve the existing credential, and a new UUID replaces it encrypted. Domain validation maps back to the visible Filament value field. The table reports a secret as `پیکربندی شده` or `نیاز به تنظیم`, never its value.

## 10. Fail-Closed Behavior

ZarinPal is unavailable unless the default gateway is `zarinpal`, it is enabled, the merchant is valid, and sandbox is allowed for the current environment. There is no normal-runtime fallback to Fake. Production blocks sandbox configuration and a production-like sandbox resolver fails closed.

## 11. Sandbox / Production

An isolated enabled sandbox configuration resolves in testing/local context. A production-like valid non-sandbox configuration resolves; production-like sandbox is rejected/blocked.

## 12. APP_URL

`APP_URL` remains environment/infrastructure configuration. Callback URLs remain Laravel route-generated from it and are not stored as Site Settings.

## 13. `settings:sync`

The existing idempotent sync uses registry metadata and now adds any missing payment rows without changing stored values.

## 14. `settings:status`

Development result after import:

- Registered: 12
- Persisted: 12
- Missing: none
- Unknown: none
- Needs configuration: none

The command also reports default gateway, enabled/sandbox flags, configured state, and operational state. It never prints the merchant credential.

## 15. `AGENTS.md`

The permanent Site Settings rules now cover payment operational settings, encrypted provider credentials, no credential rendering/logging, route-derived callbacks, additive structural migrations, fail-closed production behavior, and the future-provider workflow.

## 16. Development Runtime QA

The current development configuration was imported and diagnostics report ZarinPal operational in local sandbox mode. Actual Filament/Livewire runtime tests verified the payment controls, masked blank-preserve/rotation behavior, clean incomplete-enable rejection, and disable/re-enable availability transition without invoking a hosted payment.

## 17. Focused Tests

`tests/Feature/Settings`, `tests/Feature/Filament/Settings`, `tests/Feature/Payments`, `tests/Feature/Checkout`, and `tests/Feature/Storefront/PaymentTest.php`:

- Tests: 57
- Assertions: 374
- Failures: 0
- Skipped: 0

## 18. Full Suite

- Tests: 373
- Assertions: 2,421
- Failures: 0
- Skipped: 0

The test runner emitted its pre-existing non-fatal inability to write Pest's vendor result-cache file; the suite exit code was zero.

## 19. Pre-Migration Backup

`D:\uni-shop-project\db\backups\ecommerce_2026-09-05_131630.sql`

## 20. Post-Migration Backup

`D:\uni-shop-project\db\backups\ecommerce_2026-09-05_132210.sql`

## 21. Migration Status

The additive migration ran successfully. `php artisan migrate:status` records it as run, and the subsequent `php artisan migrate` reported `Nothing to migrate.`

## 22. Database Safety

Only the required pre/post backups, normal additive migration, explicit import, and non-destructive verification ran against `ecommerce`. No reset, wipe, truncate, fresh migration, or destructive command was used.

## 23. Files Changed

- Settings registry/service and the additive migration.
- Payment runtime resolver, SDK construction, provider binding, import and diagnostics commands.
- Value-only Filament payment controls and settings status output.
- Legacy environment documentation, tests, and permanent project guidance.

## 24. Raw Frontend

`D:\uni-shop-project\front` was unchanged.

## 25. Future Provider Workflow

Register each future provider's explicit operational settings in `SettingRegistry`, mark every persisted credential as `secret`, add an additive structural migration and value-only Filament fields, consume it through `SettingsService` plus a runtime resolver, and add isolated encryption/fail-closed tests. Do not create rows for unimplemented providers.

`PAYMENT GATEWAY SITE SETTINGS: VERIFIED PASS`
