# Blade Storefront Phase 6 — Coupon + Shipping Quote

## 1. Coupon Domain Adapter

The web adapter delegates application, removal, eligibility, targeting, pricing, and recalculation to `CartService` and the existing `CouponService`. No coupon rules or discount arithmetic were duplicated in the controller or Blade.

## 2. Coupon Routes

* `POST /cart/coupon` (`storefront.cart.coupon.apply`)
* `DELETE /cart/coupon` (`storefront.cart.coupon.remove`)

Both use the Laravel web middleware and CSRF-protected forms.

## 3. Coupon UI

The Cart summary renders a server-submitted code form, active code, remove action, discount total, success feedback, and safe validation feedback. Internal coupon IDs and rules are not rendered.

## 4. Coupon Consumption Behavior

Applying/removing a coupon only updates the active Cart coupon and recalculates it. It creates no `CouponUsage`; redemption remains in the existing Order/Checkout path. Recalculation re-evaluates stale eligibility and clears an invalid coupon through the domain service.

## 5. Shipping Domain Adapter

`StorefrontShippingQuoteService` resolves the authenticated customer Address through `AddressService`, validates shipping-capable address type and geography, validates service/payment identifiers through `ShippingOptionCatalog`, and delegates all calculation to `ShippingCostResolver`.

## 6. Shipping Quote Route

* `POST /cart/shipping/quote` (`storefront.cart.shipping.quote`, authenticated web route)

The route recalculates the current Cart, computes a quote, stores only semantic selection in session, and redirects back to Cart. Each Cart render recalculates the quote from current server state.

## 7. Shipping Semantic Input

Accepted fields are `address_id`, `service` (`pishtaz`/`vijeh`), and `payment_type` from the existing catalog. Price, weight, volume, package, origin, parcel nature, and shipping amount are ignored/rejected as authoritative inputs.

## 8. Fixed Mode

Uses configured `shipping.fixed_rate_amount` through `ShippingCostResolver`; no calculator package data is required.

## 9. Free Mode

Uses the resolver's free mode and returns zero Rial. This is distinct from coupons; no free-shipping coupon type was added.

## 10. Calculator Mode

Uses the single configured global origin, customer destination, selected supported service/payment type, central Cart metrics, deterministic package selection, and `PostShippingCalculator`. Both Pishtaz and Vijeh identifiers are exposed from the existing option catalog.

## 11. Weight / Volume / Parcel Nature

The web layer performs no weight or parcel arithmetic. Resolver metrics retain variation weight/volume overrides, fall back to Product values, sum quantities centrally, round once as currently defined, and promote any fragile line to the existing fragile parcel type.

## 12. Package Selection

Package fit and deterministic smallest-fitting selection remain entirely inside `ShippingCostResolver`; unavailable packages become a safe web error rather than an internal exception.

## 13. Address Ownership

The quote service calls `AddressService::getForUser`; another customer's Address is rejected without calculating a quote. The route is authenticated and no guest Address/Checkout behavior was introduced.

## 14. Cart Summary Integration

Cart totals, tax, and coupon discount remain CartService values. A selected quote is recalculated on every Cart render and displayed as a temporary shipping amount plus presentation grand total; the Cart's authoritative persisted `shipping_total` is not trusted or mutated. Checkout remains disabled/deferred.

## 15. Client-Side Authority Audit

No Cart coupon/shipping JavaScript was copied or enabled. All forms are normal Blade submissions. The raw template directory was not changed, and no browser script computes discounts, shipping, totals, package, weight, or parcel data.

## 16. Bugs Found

None. No `FINAL-FILAMENT-BUG`/production defect was observed.

## 17. Production Fixes

Added the smallest scoped web adapter: Coupon and quote requests/routes, `StorefrontShippingQuoteService`, Cart controller integration, Cart summary/forms, and safe presentation data. No domain redesign or schema change was made.

## 18. Coupon Storefront Tests

`tests/Feature/Storefront/CouponShippingTest.php`: **6 passed, 72 assertions, 0 failures, 0 skipped** (run independently twice).

Covered supported percent/fixed-cart/fixed-product application, invalid/expired and sale-targeted rejection, removal, integer values, no usage redemption, no Order/reservation side effects, and safe validation.

## 19. Shipping Quote Tests

The same focused file covers authenticated ownership, fixed/free/calculator modes, Pishtaz/Vijeh, semantic-input validation, global-origin/package/fragile server derivation, integer quote display, and no Order/reservation side effects. Existing `ShippingCostResolverTest` covers variation override/fallback, summed weight, fragile promotion, package selection, and missing-package/configuration errors.

## 20. Cart Regression

`tests/Feature/Storefront/CartTest.php`: **9 passed, 81 assertions**. Existing simple/variable add, update/remove/clear, ownership, availability, and no-reservation behavior remains green.

## 21. Address Regression

`tests/Feature/Storefront/AddressBookTest.php`: **3 passed, 30 assertions**; ownership and Shipping dataset compatibility remain green.

## 22. Coupon Domain Regression

`tests/Feature/Coupons`: **12 passed (26 assertions)** in the targeted run, including targeting precedence, product restrictions, usage/idempotency, limits, and configuration validation.

## 23. Shipping Domain Regression

`tests/Feature/Shipping/ShippingCostResolverTest.php`: **5 passed (12 assertions)**; all existing Unit shipping calculator/reference tests also passed in the full suite.

## 24. Storefront Suite

`tests/Feature/Storefront`: **35 passed, 303 assertions, 0 failures, 0 skipped** in the final focused run (the phase file contributes 6 tests/72 assertions).

## 25. Browser Interaction

**Browser Coupon/Shipping interaction NOT VERIFIED.** No browser runner was added; PHP HTTP tests prove the server-side boundary.

## 26. Full Isolated Suite

**299 passed, 1,849 assertions, 0 failures, 0 skipped.**

## 27. Migration

`php artisan migrate`: **Nothing to migrate.**

`php artisan migrate:status`: all listed migrations **Ran**.

## 28. Pint

`vendor/bin/pint --dirty`: **passed**.

## 29. git diff --check

**Passed** (no output/errors).

## 30. Production Files Changed

* `app/Http/Controllers/Storefront/CartController.php`
* `app/Http/Requests/Storefront/CartCouponRequest.php`
* `app/Http/Requests/Storefront/ShippingQuoteRequest.php`
* `app/Services/Storefront/StorefrontShippingQuoteService.php`
* `app/Services/Storefront/StorefrontCartContext.php`
* `resources/views/storefront/cart/index.blade.php`
* `routes/web.php`
* `tests/Feature/Storefront/CouponShippingTest.php`

## 31. Raw Frontend Source

`D:\uni-shop-project\front` is unchanged.

## 32. Side Effects

Coupon application and shipping quote created zero:

* Orders
* Payments
* Shipments
* InventoryReservations
* premature CouponUsage redemptions

## 33. Remaining Storefront Work

* Checkout
* Payment
* Orders / Shipment tracking
* Blog
* final browser QA
* guest Cart merge only if a product requirement is later defined

`BLADE STOREFRONT PHASE 6 COUPON SHIPPING: VERIFIED PASS`
