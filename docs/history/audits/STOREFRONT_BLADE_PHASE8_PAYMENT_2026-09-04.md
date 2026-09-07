# 1. Existing Payment Architecture

`PaymentService` remains the authoritative application boundary. `initiate(Order, gatewayAlias, idempotencyKey)` derives the Payment amount from `Order::grand_total`, records an audited initiation transaction, and transitions the Order to awaiting payment/processing according to the existing domain rules. `verify(Payment)` is the only success path: it records verification, handles reconciliation outcomes, commits linked inventory reservations exactly once, updates Payment/Order state and history, and emits the existing success lifecycle event. Payment statuses are `pending`, `processing`, `paid`, `failed`, `cancelled`, `expired`, `refunded`, and `partially_refunded`; Order payment/status enums remain unchanged.

# 2. Gateway Resolution

Production: no fake gateway and no unregistered gateway are exposed; the storefront resolver returns unavailable when no configured registry alias exists.

Local: a configured registry gateway is used; the existing `fake` gateway is permitted for local development.

Testing: the existing test fake is bound through `PaymentGatewayRegistry` and is available for runtime feature tests.

# 3. Real Provider Status

`No real payment provider configured.`

No ZarinPal, IDPay, Pay.ir, Stripe, PayPal, or other real provider was added. Production Payment remains unavailable until an explicitly selected provider is configured.

# 4. Payment Routes

* `POST /orders/{order}/payment` (`storefront.payment.initiate`) — authenticated, CSRF-protected initiation.
* `GET /payment/return/{payment}` (`storefront.payment.return`) — owned customer return path that invokes authoritative verification.
* `GET /payment/result/{payment}` (`storefront.payment.result`) — owned read-only result rendering.

# 5. Payment Initiation

The controller resolves the current user's Order by public order number (and retains safe numeric fallback), rejects foreign/missing Orders, paid Orders, and cancelled Orders, then delegates to `PaymentService`. Browser-supplied amount/gateway fields are ignored; the Payment amount equals the Order `grand_total`. A server-generated session idempotency key makes replay safe. The local/testing fake returns to the provider-neutral return route; a future real adapter can hand off its provider redirect without changing the Blade contract.

# 6. Fake Gateway Environment Safety

The existing fake gateway is usable in testing/local only. A production-like environment resolves no fake storefront gateway and the success page shows a safe unavailable state. Runtime coverage verifies this resolver boundary, not merely button visibility.

# 7. Payment Verification

Customer return never trusts query parameters, browser amounts, or initiation success. It loads an owned Payment and delegates verification to `PaymentService`/`PaymentGatewayInterface`; only the gateway verification result can produce a paid state.

# 8. Successful Payment

Payment becomes `paid`; the Order payment status becomes `paid` and its status transitions to the existing processing state. Linked active reservations commit once, reducing physical on-hand stock exactly once. Payment/Order history and the existing notification intent behavior remain owned by the domain service. The storefront controller does not call `InventoryService` directly.

# 9. Failed Payment

Failed verification leaves Payment failed, the Order unpaid/awaiting payment, and the reservation active. Inventory is not committed. The result page provides a safe retry path without exposing gateway internals.

# 10. Duplicate Verification

Repeated return/verification is idempotent: no second verification transaction, reservation commit, stock decrement, Order transition, or success notification is created. The final state remains paid.

# 11. Payment Retry

After a failed attempt, the storefront clears that attempt's session idempotency key. A subsequent explicit initiation creates a fresh Payment attempt; replaying the same live key remains idempotent. No automatic unlimited retry loop was introduced.

# 12. Reconciliation

Amount mismatch, cancelled-Order capture, expired reservation, late capture, and duplicate-success behavior remain the existing `PaymentService` reconciliation contract. Normal success is not produced for mismatched or otherwise unsafe captures, and stock is never silently re-reserved. Existing domain regression tests cover these cases.

# 13. Payment Result Page

`resources/views/storefront/payment/result.blade.php` renders success, failed, pending, and neutral review states with safe Order number, machine status, integer-Rial amount, and retry/continue links. It never presents an Order as paid merely because initiation or landing occurred.

# 14. Ownership / IDOR

Initiation, return, and result queries are constrained to the authenticated customer's Order relationship. Foreign Order/Payment IDs return safe not-found responses; a customer cannot retry or inspect another customer's Payment.

# 15. Sensitive Data Exclusions

The view model excludes `gateway_response`, initiation fingerprints, reconciliation payloads, authorities, secrets, card data, internal transaction payloads, deleted state, and inventory/reservation internals.

# 16. Checkout Integration

The existing Checkout success page now receives gateway availability and shows `ادامه به پرداخت` only for an available, unpaid, non-cancelled Order. It is a CSRF-protected POST and viewing the page creates no Payment. With no configured production gateway it shows the unavailable state instead.

# 17. JavaScript Authority Audit

The payment result page uses no payment-calculation JavaScript. Existing checkout/cart scripts do not calculate or overwrite Payment amounts, status, gateway results, stock, or totals. Payment state is rendered from the server; no fake production success control was added.

# 18. Bugs Found

* `PAYMENT-UI-BUG-001` — Severity: MEDIUM. Scenario: owned initiation lookup. Input: valid owned Order number. Expected: provider-neutral handoff. Actual: the first adapter implementation called unsupported `orWhereKey` on a closure builder and returned HTTP 500. Exception: `BadMethodCallException`. Root cause: incorrect query-builder method in the new adapter. Affected file: `app/Http/Controllers/Storefront/PaymentController.php`.
* `PAYMENT-UI-BUG-002` — Severity: HIGH. Scenario: ineligible paid Order. Input: replay initiation with the prior idempotency key. Expected: rejection. Actual: PaymentService's idempotent early return could be reached before eligibility validation. Root cause: adapter did not guard paid/cancelled enum states before delegation. Affected files: `app/Http/Controllers/Storefront/PaymentController.php`, `tests/Feature/Storefront/PaymentTest.php`.
* `PAYMENT-UI-BUG-003` — Severity: MEDIUM. Scenario: failed-payment retry. Input: retry after failed verification. Expected: fresh attempt. Actual: the failed session key would otherwise replay the failed Payment. Root cause: stale session idempotency key. Affected files: `app/Http/Controllers/Storefront/PaymentController.php`, `tests/Feature/Storefront/PaymentTest.php`.

# 19. Production Fixes

The smallest focused fixes replaced the unsupported ownership query call, added explicit paid/cancelled eligibility guards before idempotent delegation, and cleared the session initiation key after failed verification. No PaymentService/domain redesign was made.

# 20. Storefront Payment Tests

`tests/Feature/Storefront/PaymentTest.php`: **5 passed, 40 assertions, 0 failures, 0 skipped**. Independent run 1 and independent run 2 produced the same result. Coverage includes guest/owner boundary, amount authority, checkout-success handoff, verified success, inventory commit-once, failure/retry, duplicate return, paid-order rejection, Payment result ownership, and production fake-gateway unavailability.

# 21. Payment Domain Regression

`tests/Feature/Payments/PaymentServiceTest.php`: all 5 passed. The combined domain regression run passed **24 tests, 103 assertions** with zero failures/skips, including PaymentService reconciliation/idempotency behavior.

# 22. Inventory Regression

`tests/Feature/Inventory/InventoryServiceTest.php`: all 7 passed within the combined **24 tests, 103 assertions** regression run. No reservation or stock behavior regressed.

# 23. Checkout Regression

`tests/Feature/Checkout/CheckoutServiceTest.php`: all 6 passed within the combined **24 tests, 103 assertions** regression run. Checkout Order creation, reservation, Coupon redemption, conversion, and idempotency remain unchanged.

# 24. Notification Regression

`tests/Feature/Notifications/CustomerNotificationTest.php`: all 6 passed within the combined **24 tests, 103 assertions** regression run. Duplicate Payment verification remains exactly-once for notification intent behavior.

# 25. Storefront Suite

`php artisan test tests/Feature/Storefront`: **47 passed, 400 assertions, 0 failures, 0 skipped**.

# 26. Browser Interaction

`Browser Payment interaction NOT VERIFIED` — no browser runner was used in this phase. PHP HTTP/runtime tests and static JavaScript inspection were used instead.

# 27. Full Isolated Suite

`php artisan test`: **311 passed, 1,946 assertions, 0 failures, 0 skipped**. The run emitted only the existing Pest result-cache permission warning for `vendor/pestphp/pest/.temp/test-results`; it did not affect the exit status or test results.

# 28. Migration

`php artisan migrate`: **Nothing to migrate.** `php artisan migrate:status`: all listed migrations are `Ran`. No migration was added.

# 29. Pint

`vendor/bin/pint --dirty`: **passed**.

# 30. git diff --check

**passed** (no whitespace errors).

# 31. Production Files Changed

Phase 8 files:

* `config/payment.php`
* `app/Http/Requests/Storefront/PaymentInitiationRequest.php`
* `app/Services/Storefront/StorefrontPaymentGateway.php`
* `app/Http/Controllers/Storefront/PaymentController.php`
* `app/Http/Controllers/Storefront/CheckoutController.php`
* `routes/web.php`
* `resources/views/storefront/checkout/success.blade.php`
* `resources/views/storefront/payment/result.blade.php`
* `public/storefront/assets/css/payment-result/layout.css`
* `public/storefront/assets/css/payment-result/status.css`
* `public/storefront/assets/css/payment-result/order-summary.css`
* `public/storefront/assets/css/payment-result/responsive.css`
* `tests/Feature/Storefront/PaymentTest.php`

# 32. Raw Frontend Source

`D:\uni-shop-project\front` remains unchanged.

# 33. Safety

* ecommerce untouched; no destructive development database command was run.
* No real payment provider was added.
* The fake gateway is unavailable in production.
* The browser cannot choose the Payment amount.
* Verification is mandatory.
* Inventory is committed only by `PaymentService`.
* No Shipment, Returns, or Refunds implementation was added.

# 34. Remaining Storefront Work

* real Payment provider integration — only after provider selection
* customer Orders
* Shipment tracking
* Blog
* final browser/visual QA
* guest Cart merge only if later required

`BLADE STOREFRONT PHASE 8 PAYMENT: VERIFIED PASS`
