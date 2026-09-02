# Backend QA Audit — 2026-09-02

Scope: current worktree and current development schema. This was an audit only. No production code, migration, seed, or development data was changed. A temporary isolated coupon test was created to reproduce BUG-001 and then removed.

## 1. Executive Summary

Overall health: **mixed; not safe to call feature-complete**. The core commerce services have meaningful isolated integration coverage and most passed in this audit. However, the real Filament coupon create path is currently broken for an ordinary browser/Livewire numeric payload, and coupon targeting UI does not prevent invalid include/exclude configurations.

- Complete and runtime-verified domains: 14 (isolated Laravel/Livewire runtime, not browser + MySQL E2E)
- Implemented but not runtime-verified domains: 7
- Partial/broken/scaffolding/legacy domains: 21
- Findings: **0 Critical, 1 High, 3 Medium, 2 Low, 7 Technical Debt**

The full isolated suite passed in its normal aggregate invocation (202 declared tests across 39 `*Test.php` files). That does not prove MySQL production behavior or individual test isolation: `ShipmentResourceTest` fails when run alone.

## 2. Backend Domain Matrix

| Domain | Implementation Status | Runtime Verified | Test Coverage | Main Files | Notes |
|---|---|---:|---|---|---|
| Authentication/users | COMPLETE AND RUNTIME-VERIFIED | Yes | Strong | `User`, auth controllers, `UserResource` | Breeze flows and admin access tested. |
| Roles/policies/admin access | COMPLETE AND RUNTIME-VERIFIED | Yes | Strong | `AppServiceProvider`, policies, seeder | Central `Gate::before` super-admin bypass; protected-user tests pass. |
| Products | COMPLETE AND RUNTIME-VERIFIED | Yes | Strong | `Product`, Product Filament resource | Simple create/edit and inventory integration tested. |
| Variations/attributes | COMPLETE AND RUNTIME-VERIFIED | Yes | Strong | `ProductVariantService`, variations migration | Deterministic signature and duplicate constraints tested. |
| Product pricing/sale prices | IMPLEMENTED BUT NOT RUNTIME-VERIFIED | Service-level | Moderate | `ProductPriceResolver` | Resolver exercised indirectly; no browser price-management audit. |
| Product discounts | PARTIALLY IMPLEMENTED | Service-level | Partial | sale fields/resolver | Sale price is present; no separate promotion domain. |
| Tax | COMPLETE AND RUNTIME-VERIFIED | Yes | Strong | `TaxCalculator`, `TaxClass` | Integer-Rial behavior and default/product precedence covered. |
| Categories/brands/tags | IMPLEMENTED BUT NOT RUNTIME-VERIFIED | No | Weak | respective resources/models | CRUD exists; no focused runtime tests found. |
| Coupons | BROKEN | **No: create fails** | Moderate but misleading | `CouponService`, `CouponResource` | See section 6 / BUG-001. |
| Coupon usage | IMPLEMENTED BUT NOT RUNTIME-VERIFIED | Service-level | Moderate | `CouponUsage`, `CouponService` | Per-order uniqueness/idempotency tested only on SQLite. |
| Settings | COMPLETE AND RUNTIME-VERIFIED | Yes | Moderate | registry/service/resource | Known-key edit path tested; shipping package editing not tested through UI. |
| Inventory ledger/adjustment | COMPLETE AND RUNTIME-VERIFIED | Yes | Strong | `InventoryService`, transactions resource | Atomic adjustments/reservations and audited Filament action tested. |
| Reservations/expiry | COMPLETE AND RUNTIME-VERIFIED | Yes | Strong | `InventoryReservation`, command | Lifecycle and schedule declaration tested/service-verified. |
| Addresses | COMPLETE AND RUNTIME-VERIFIED | Yes | Strong | `AddressService` | Ownership and geography validation tested. |
| Cart | COMPLETE AND RUNTIME-VERIFIED | User flow yes | Moderate | `CartService` | User/token support exists; token flow has no direct test. |
| Checkout | COMPLETE AND RUNTIME-VERIFIED | Isolated service | Strong | `CheckoutService` | Snapshot, reservation, coupon, conversion, replay covered; see section 7 limitations. |
| Orders/history | COMPLETE AND RUNTIME-VERIFIED | Yes | Strong | `OrderService`, order resource | Snapshots, transitions, cancellation/reservations covered. |
| Payments | COMPLETE AND RUNTIME-VERIFIED | Fake gateway only | Strong | `PaymentService`, gateway interface | No real gateway; intentional. |
| Shipping | IMPLEMENTED BUT NOT RUNTIME-VERIFIED | Service-level | Strong calculator/service | resolver/calculator/settings | Global origin flow tested on SQLite; public diagnostic endpoint is separate. |
| Fulfillment/shipments | PARTIALLY IMPLEMENTED | Service yes; resource no | Moderate | `ShipmentService`, shipment resource | Resource test is order-dependent; see BUG-003. |
| Notifications | IMPLEMENTED BUT NOT RUNTIME-VERIFIED | Isolated queue/service | Moderate | events/listener/job/service | Provider-agnostic development channel; no real worker test. |
| Blog/CMS posts | COMPLETE AND RUNTIME-VERIFIED | Yes | Moderate | `PostService`, `PostResource` | Draft/schedule/publish/slug paths tested. |
| Media/uploads | PARTIALLY IMPLEMENTED | No | None | `ProductImage`, relation manager | Product-only local upload manager; no storage/security/runtime audit. |
| Wallet, affiliates, campaigns, flash sales, gift cards, customer groups, wishlists, rewards | SCAFFOLDING ONLY / LEGACY | No | None | models/migrations | Models/tables exist without an evident current service or Filament workflow. |
| Shipping zones/classes/methods/rates | LEGACY / UNUSED | No | None | models/migrations | Checkout uses `ShippingCostResolver`, not these tables. |
| Reviews/questions/SEO/referrals/newsletter/tickets | SCAFFOLDING ONLY / LEGACY | No | None | models/migrations | No complete operational surface found. |
| Queues/scheduler/cache | IMPLEMENTED BUT NOT RUNTIME-VERIFIED | Partial | Moderate | command, jobs, config | Database queue/cache configured; no worker/lock multi-node test. |

## 3. Codex-Assisted vs Pre-existing Code Matrix

Git records every committed author as `nima1383`; Git cannot establish whether an AI assisted with an individual file. Commit messages are evidence of chronology, not authorship. “Created during Codex-assisted work” is therefore **not provable** unless the commit message itself says so.

| Domain | Classification | Evidence | Confidence |
|---|---|---|---|
| Initial models/migrations/auth/cart/orders | A — clearly pre-existing relative to current uncommitted work | Added in July commits `b68dcb9`/`3737619` | High for chronology; no human/AI attribution |
| Product admin/variations | B — pre-existing, repeatedly modified | July commits plus `b5d9dd8` whose subject mentions Codex | Medium chronology; AI authorship unknown |
| Tax/settings | B — pre-existing, later modified | `bf2db25`, current modifications | High chronology; authorship unknown |
| Coupons | B — pre-existing, later modified | `9cb55b6`, `d9eb6e6`, current modifications/untracked relation managers | High chronology; authorship unknown |
| Inventory/orders/payments/checkout | B — later-added/modified | `b5d9dd8` says “codex checked…” and `d9eb6e6` additions | Medium; commit subject is not authorship proof |
| Shipping calculator | A — committed pre-existing | `33339af` | High chronology; authorship unknown |
| Shipping resolver / shipment / notifications | D — UNKNOWN / NOT PROVABLE FROM REPOSITORY HISTORY | currently untracked files and migrations | High that uncommitted; no attribution proof |
| Tests added after Aug. 28 | D — UNKNOWN / NOT PROVABLE FROM REPOSITORY HISTORY | untracked and modified tests | High chronology; no attribution proof |

## 4. Filament/Admin Feature Matrix

| Resource | List | View | Create | Edit | Delete | Custom Actions | Authorization | Runtime Result |
|---|---|---|---|---|---|---|---|---|
| Products | Yes | No | Yes | Yes | soft | variation/inventory | Policy | PASS (isolated Livewire) |
| Categories/Brands/Tags | Yes | No | Yes | Yes | soft | relationships | Policies | NOT VERIFIED |
| Tax classes | Yes | No | Yes | Yes | delete | product relation | Policy | service/routing PASS; forms not fully tested |
| Coupons | Yes | No | Yes | Yes | soft/restore | products/users/categories/roles/usages | Policy | **FAIL create** |
| Settings | Yes | No | No | known keys only | No | typed save | Policy | PASS |
| Inventory transactions | Yes | Yes | No | No | No | audited adjust | Policy + action check | PASS |
| Reservations | Yes | Yes | No | No | No | none | Policy | PASS |
| Orders | Yes | Yes | No | No | No | transitions/start shipment | Policy | PASS |
| Payments | Yes | Yes | No | No | No | none | Policy | PASS |
| Shipments | Yes | Yes | No | No | No | transition/tracking | Policy | PARTIAL (test isolation failure) |
| Customer notifications | Yes | Yes | No | No | No | retry failed | Policy | PASS (isolated) |
| Users | Yes | Yes | No | Yes | soft/restore | roles | Policy/Gate | PASS |
| Posts | Yes | No | Yes | Yes | soft/restore | publish/schedule | Policy | PASS |
| Post categories/tags | Yes | No | Yes | Yes | delete | none | Policies | NOT VERIFIED |

## 5. Runtime Bugs Found

### BUG-001 — Coupon create rejects valid integral numeric input

- Severity: **HIGH**
- Affected domain: Coupons / Filament
- Reproduction: `CreateCoupon` Livewire flow with `amount => 29.0`; also repeatedly logged for real user id 1 on Aug. 26 and Aug. 29.
- Expected: an integral float emitted by the numeric Filament component is normalized to integer Rial and coupon saves.
- Actual: `CouponConfigurationException: مقدار تخفیف باید یک عدد صحیح ریالی باشد.` at `CouponService.php:399`; no coupon is persisted.
- Root cause: `integerValue()` accepts only PHP `int` or digit-only string, while Livewire numeric state can be an integral `float`.
- Affected files: `app/Services/CouponService.php`, `app/Filament/Resources/Coupons/Pages/CreateCoupon.php`.
- Impact: core coupon admin workflow blocked; previously reported coupon completion was false.
- Recommended fix: accept only finite whole-number floats then cast to int, or normalize Filament numeric state before service validation; preserve rejection of fractional/negative values and add a real create/edit Livewire regression test.

### BUG-002 — Coupon targeting UI persists invalid include/exclude pairs

- Severity: **MEDIUM**
- Reproduction: attach one included and one excluded product/user/role through their relation managers.
- Expected: UI validation prevents each mutually exclusive pair.
- Actual: managers expose independent `is_excluded` toggles; only `CouponService::assertValidConfiguration()` later rejects the resulting persisted configuration. Roles manager also lacks parity edit/detach/bulk management.
- Root cause: validation exists at evaluation/service boundary, not at Filament relationship mutation boundary.
- Affected files: coupon relation managers and `CouponService`.
- Impact: admin can save a coupon that will later fail eligibility/checkout.
- Recommended fix: constrain attach/edit/bulk actions transactionally and test all three pairs.

### BUG-003 — Shipment resource test is order-dependent

- Severity: **MEDIUM**
- Reproduction: `php artisan test tests/Feature/Filament/Shipments/ShipmentResourceTest.php`.
- Expected: the resource test runs independently.
- Actual: `Call to undefined function fulfillmentOrder()` at line 28. Full-suite ordering masks it because the helper is globally declared by `ShipmentServiceTest`.
- Root cause: cross-file global test helper.
- Affected files: `tests/Feature/Filament/Shipments/ShipmentResourceTest.php`, `tests/Feature/Fulfillment/ShipmentServiceTest.php`.
- Impact: claimed shipment-resource coverage is unreliable; CI filtering/parallelization can fail.
- Recommended fix: local fixture or shared test support class/trait.

### BUG-004 — Product image admin actions lack focused authorization/storage QA

- Severity: **MEDIUM**
- Reproduction: structural audit only; relation manager has create/edit/delete/upload actions and no dedicated test.
- Expected: every upload/mutation is explicitly covered for parent policy and storage behavior.
- Actual: NOT VERIFIED.
- Root cause: coverage gap.
- Impact: possible authorization/upload policy gap.
- Recommended fix: add authorized/unauthorized Livewire and storage tests before relying on media operations.

### BUG-005 — Public diagnostic shipping calculator accepts caller-controlled origin/package data

- Severity: **LOW**
- Reproduction: unauthenticated `GET/POST /shipping-calculator-test` uses `CalculatePostalShippingRequest` and `ShippingQuoteInput::fromArray()`.
- Expected: production checkout must not accept these values; it does not.
- Actual: the separate diagnostic endpoint still does. It is not called by `CheckoutService`.
- Impact: no checkout corruption found, but the route is a confusing public legacy surface.
- Recommended fix: remove/restrict/document it as non-production diagnostic tooling.

## 6. Coupon Deep Audit

- Creation currently works? **FAIL** for real integral float payloads; reproduced exactly.
- Current/past exception: `CouponConfigurationException` at `CouponService.php:399`, message above. Logs prove real occurrences; isolated Livewire reproduction proves current behavior.
- Product include/exclude: persisted by `coupon_product`; evaluated by `eligibleItems()` against the cart item parent product. **Variable parent targeting: PASS.**
- User include/exclude: persisted by `coupon_user`; evaluated. **PASS at service level.**
- Role include/exclude: table/model/service added in current uncommitted work; service precedence tests pass. **Persistence through Filament relation manager: NOT VERIFIED.**
- User/role precedence: **PASS at service level**: excluded user rejects; included user bypasses roles; otherwise role checks apply.
- Product + user/role independence: **PASS at service level**.
- `exclude_discounted_products`: resolver-based check exists. `exclude_sale_items` currently duplicates the same condition, so semantics are redundant. Direct regression coverage for the new flag is missing.
- `percent`, `fixed_cart`, `fixed_product`: enum/options/service branches exist; percent and fixed-cart are tested. **Fixed-product end-to-end behavior: NOT VERIFIED.**
- Dates/usage limits/redemption idempotency: service tests pass on SQLite.
- Mutually exclusive pairs: service rejects invalid persisted records; **Filament does not prevent them (FAIL).**
- Categories are an extra legacy targeting dimension; not part of requested six dimensions and not covered by the requested precedence rules.

## 7. Checkout Deep Audit

| Step | Result | Evidence |
|---|---|---|
| Cart ownership/active state | PASS | `CartService` + checkout tests |
| Central pricing | PASS | stale-preview recalculation test |
| Coupon evaluation | PASS | checkout coupon/redeem test; blocked by BUG-001 only at admin creation |
| Tax | PASS | cart/order tax tests |
| Address ownership/geography | PASS | address + checkout tests |
| Server-side shipping/global origin | PASS | `CheckoutInput` has no origin/package fields; resolver settings-only |
| Inventory reservation | PASS | order/checkout tests |
| Order snapshots | PASS | order service tests |
| Coupon redemption | PASS | transaction/idempotency test |
| Cart conversion | PASS | checkout test |
| Idempotency | PASS with limitation | same-key and conflicting-input test; fingerprint does not include coupon id/note/user-agent, so canonical completeness is **PARTIAL** |

No real provider is initiated by checkout. Failure rollback for unavailable stock and wrong address is tested. All proof is SQLite in-memory, not MySQL/worker/browser E2E.

## 8. Order / Payment / Inventory Consistency

Verified invariants: integer snapshots, variation inventory ownership, active reservation availability reduction, immutable ledger writes through `InventoryService`, cancellation releases active reservations, committed stock is not restored by cancellation, payment verification commits once, and shipment transition does not directly mutate inventory.

Payment foundation is provider-agnostic with fake-gateway tests. No real gateway exists, as required. Payment and inventory admin resources are read-only, with no mark-paid/verify shortcut found.

## 9. Shipping Audit

`ShippingCostResolver` reads one global origin from registered settings, validates destination IDs against the shared geographic loader, and uses configured packages for both volume and weight. `CheckoutInput` no longer contains origin/city/package/parcel fields. Fixed/free return without running the calculator. Missing origin/packages returns a domain error.

Pass with caveat: legacy public `/shipping-calculator-test` remains caller-controlled but is separate from checkout (BUG-005). Calculator formulas were not changed or independently re-derived.

## 10. Authorization Audit

Passes: guest/non-admin denial, centralized super-admin role gate, soft-deleted admin denial, product policy distinctions, settings restriction, user self-delete/last-super-admin protections, and read-only payment/inventory resources.

Weaknesses: product image relation-manager mutation/upload paths lack dedicated policy tests; coupon relation-manager mutations lack configuration-invariant enforcement. No direct browser authorization sweep was possible; results are route/Livewire/service runtime only.

## 11. Notification Audit

After-commit event classes, queued listener/job, durable idempotency key, recipient snapshots, retry of failed intent, and no fake “mark sent” action all exist and tests pass. Core transaction rollback does not create intent in tests. Delivery failure is isolated.

Not verified: a real database queue worker, retry timing under worker crashes, and external channel delivery (intentionally no provider selected). Development channel must not be presented as external delivery.

## 12. Database Integrity Findings

- Development MySQL schema read-only check confirmed `cache`, `jobs`, `sessions`, coupon pivots, and `coupon_role` exist; all migrations show ran.
- Coupon code and variation signatures have unique constraints; coupon usage has `(coupon_id, order_id)` uniqueness.
- Money columns are `decimal(...,0)` while models/services generally cast to integers. This is compatible with integer Rial but should be made consistently explicit in all models.
- Geometry/weight/volume correctly use decimal/float-like values; they are not money.
- Historical logs contain old migration/schema failures (`category_coupon`, duplicate tax columns/indexes) and historical tax-class/Filament errors. Current migration status/schema does not reproduce those exact states.
- No destructive schema command was run.

## 13. Existing Test Coverage Assessment

The suite proves substantial service behavior, particularly inventory/order/payment/checkouts, but it does not prove the admin panel is generally usable. Coupon tests created models directly and only checked coupon routes/permissions; they never submitted the coupon creation form. That is exactly how a “complete” report could coexist with BUG-001.

The full suite passing is weakened by test ordering: the standalone shipment resource test fails. SQLite `:memory:` also cannot establish MySQL migration/index/locking equivalence.

## 14. Missing Tests

1. Coupon create/edit Livewire submissions with `1.0`, `29.0`, decimal rejection, all types, dates, limits, and reload/persistence.
2. Coupon product/user/role relation-manager attach/edit/bulk validation for all include/exclude pairs.
3. Fixed-product coupon, `exclude_discounted_products`, and variation-parent coupon target tests.
4. MySQL integration tests for checkout/idempotency/reservation uniqueness/locking.
5. Independent shipment resource fixture test.
6. Shipping Settings Filament save/reload for origin, mode, rate, packages and invalid combinations.
7. Product media upload authorization/storage/primary-image invariants.
8. Token-cart ownership and checkout transition tests.
9. Worker-backed notification retry/after-commit test.

## 15. Technical Debt

- Large set of domain models/tables has no current application surface.
- Duplicate coupon `exclude_sale_items` and `exclude_discounted_products` logic.
- Public shipping calculator diagnostic route should be isolated from production routes.
- Global test helper leakage and Pest result-cache permission warnings.
- No factories beyond `UserFactory`; commerce tests manually build data.
- No demonstrated cache invalidation strategy; cache database is configured but domain cache use is minimal.
- No current proof of N+1 safety for all Filament relations/large selects.

## 16. Recommended Repair Plan

### P0 — must fix immediately

- BUG-001: normalize integral numeric form values at coupon boundary; affected coupon service/create/edit tests and pages.
- BUG-002: enforce include/exclude invariants at each coupon relation mutation; affected coupon relation managers/service/tests.

### P1 — before frontend integration

- Make shipment resource test independent; affected two test files/support.
- Add MySQL-backed transactional checkout/payment/inventory tests; affected test environment/config only.
- Complete coupon fixed-product/discounted-product/relationship UI regressions.
- Add shipping-setting Filament flow tests and restrict/document diagnostic route.

### P2 — hardening

- Test/secure product image storage actions.
- Expand resource action/policy matrix to categories, brands, tags, post categories/tags, tax forms.
- Make checkout fingerprint include all order-shaping canonical inputs, including coupon state.

### P3 — future improvement

- Classify and either build or retire legacy/scaffolding domains.
- Add model factories, query-count checks, and production queue/worker observability tests.

## 17. Commands Executed

Key commands: `Get-Content -Raw AGENTS.md`; repository file/history/status inspection with `rg`, `git log`, `git diff`; `php artisan about`; `php artisan migrate:status`; `php artisan route:list --except-vendor`; full and focused `php artisan test` runs; read-only MySQL `SHOW CREATE TABLE`/`SHOW TABLES`; log inspection.

Focused test commands included coupon, product, setting, checkout, order, payment, inventory, fulfillment, cart, catalog, tax, shipping, notification, and Filament resource test files. The full isolated suite passed in normal aggregate execution; standalone shipment resource execution failed as BUG-003.

One unsupported command, `php artisan test --no-interaction`, was attempted and did not execute tests. Tinker was blocked before querying because PsySH history was not writable. Neither affected database data.

**Confirmed: no `migrate:fresh`, `db:wipe`, schema drop, truncation, destructive seed/reset, database recreation, or destructive development-database command was executed.**
