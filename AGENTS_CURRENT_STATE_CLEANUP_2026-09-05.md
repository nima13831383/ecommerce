# AGENTS.md Current-State Cleanup — 2026-09-05

## 1. Stale Statements Found

- The opening described the storefront as future work and deferred until a template was provided.
- The storefront implementation sequence was written as pending work.
- The Payment section stated that no real provider had been selected.
- The API paragraph could imply that a missing customer HTTP endpoint meant the Blade workflow was unavailable.
- The tracked filename was `Agents.md`, not the required canonical `AGENTS.md`.

## 2. Storefront Corrections

The document now states that the customer storefront is implemented as Laravel Blade SSR in the same Laravel application. It identifies `D:\uni-shop-project\front` as the unchanged raw visual/design source and instructs future work to preserve the Blade architecture, RTL design, server authority, and progressive-enhancement JavaScript approach.

The pending implementation list was replaced with a concise current status covering Home, catalog, Product Detail/variation selection, account/authentication, Cart, Address/geography, Coupon/Shipping, Checkout/Orders, Payment, Blog/static pages, and recorded browser/visual QA.

## 3. Payment Corrections

The Payment section now records ZarinPal as the implemented real provider using the official PHP SDK and the provider-neutral gateway contract, including Request, redirect, callback, persisted Authority/reference, server-side Verify, and idempotent callbacks. Future providers must use the same boundary.

## 4. Payment Settings Corrections

The existing Site Settings section remains authoritative: gateway selection, enabled state, sandbox mode, and encrypted Merchant ID are registry-backed persisted settings read through `SettingsService`. No legacy environment value was promoted to runtime authority; `APP_URL` remains infrastructure configuration.

## 5. Historical Context Preserved

The `/api/v1` contracts and historical readiness reports remain documented. The distinction that Blade SSR should call shared Laravel services directly, rather than its own API, remains intact. No report files were removed.

## 6. Permanent Rules Preserved

Database safety, architecture/SOLID, event-driven and event-sourcing guidance, transactions, queues, scheduler, scaling, Redis/cache, concurrency, database/Eloquent, Filament, authorization, validation, money/tax/inventory, testing, refactoring, dependency, security, and the callback-verification invariant were preserved.

## 7. Filename Casing

Git now records one case-normalized rename: `Agents.md` → `AGENTS.md`. A filesystem scan confirms only `AGENTS.md` exists.

## 8. Files Changed

- `AGENTS.md` (case-normalized rename plus current-state corrections)
- `AGENTS_CURRENT_STATE_CLEANUP_2026-09-05.md`

## 9. Database

Untouched. No migrations, tests, resets, or database commands were run.

## 10. Raw Frontend

`D:\uni-shop-project\front` was not modified.

## Validation

`git diff --check`: passed with no whitespace errors.

## Final Status

AGENTS CURRENT-STATE CLEANUP: VERIFIED PASS
