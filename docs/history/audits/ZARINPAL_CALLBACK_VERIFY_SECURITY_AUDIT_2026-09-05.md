# ZarinPal Callback / Verify Security Audit — 2026-09-05

## 1. Security Verdict Before Changes

**Could GET manipulation mark a Payment paid? No.** The public provider callback already required a valid application HMAC signature, the stored `Payment` number, an exact stored `authority`, `Status=OK`, and then `PaymentService::verify()`. `PaymentService` obtains the gateway result before its locked transaction; only a verified result can complete inventory and order-payment transitions.

One defense-in-depth gap was found in the authenticated legacy return route: a ZarinPal payment with a missing/empty callback Authority or Status could request Verify. It still could not create financial success without ZarinPal Verify, but it did not meet the required strict callback-input contract. This is fixed and regression-tested.

## 2. Callback Route

- Route: `GET /payment/callback/{payment}` (`storefront.payment.callback`), intentionally session-independent for provider delivery.
- Controller: `Storefront\PaymentController::providerCallback`.
- Identifier: persisted `Payment::payment_number`; it resolves the related Order solely through `Payment::order_id`.
- Application binding: `PaymentCallbackSigner` validates the 64-character HMAC over the payment number before callback input is considered.

## 3. Trust Boundary

Trusted inputs are the persisted Payment attempt, its persisted authority, its persisted integer-Rial amount, the Payment→Order relation, and the server-side ZarinPal Verify response.

Untrusted inputs are every callback query value, including `Status`, `Authority`, amount, order identifiers, ref_id, and any success-looking URL.

## 4. Status Handling

`Status=OK` is only a request to invoke Verify. `NOK`, missing, and unknown status values do not invoke Verify and cannot transition financial state. The authenticated ZarinPal return path now also requires a non-empty matching Authority and exactly `Status=OK`; fake local/testing return behavior remains unchanged.

## 5. Authority Binding

ZarinPal Request persists the returned Authority on the Payment attempt. Both callback paths require a non-empty, constant-time exact match to that Payment's stored Authority. A valid Authority from another Payment, missing Authority, empty Authority, and malformed Authority are rejected before Verify.

## 6. Amount Authority

`ZarinPalPaymentGateway::verify()` passes `(int) $payment->amount` to the SDK. `PaymentService::initiate()` captured that amount from `Order::grand_total`. Callback amount/order/ref query fields are ignored. Values are integer Rial.

## 7. Provider Verify

Verification is mandatory for any transition to `paid`. The provider adapter accepts ZarinPal codes `100` and `101` as successful Verify outcomes only after payment/signature/Authority binding. Network/SDK exceptions are normalized to a failed verification result and log only safe internal identifiers and exception class.

## 8. Payment Transition

`PaymentService::verify()` locks the Payment and Order in a database transaction, persists one verify transaction, and calls `Payment::applyStatus(Paid)` only for a verified provider result. Paid and failed attempts are terminal for replay verification, preventing a late callback from reviving a failed old attempt.

## 9. Order Transition

Only a verified result whose persisted amount/currency/reservation state reconcile with the Order can mark `OrderPaymentStatus::Paid` and transition the Order to processing. Reconciliation-required results do not commit Order financial state.

## 10. Inventory Commit

`OrderService::commitInventoryForOrder()` is called only after a verified, reconcilable result inside the locked Payment/Order transaction. Failed, malformed, status-only, and wrong-authority callbacks leave active reservations and stock untouched.

## 11. Notifications

`PaymentSucceeded` is dispatched only after the successful transaction commits. The existing idempotent customer-notification listener creates one `payment_succeeded` intent; failed/tampered callbacks create none.

## 12. Result Page

`/payment/result/{payment}` is authenticated and ownership-scoped. It renders state from persisted Payment status/reconciliation state only; query input is not read. `/checkout/success/{order}` is an order-created page, displays persisted payment status, and direct navigation does not mutate payment/order/inventory state.

## 13. Tampering Tests

- Status OK with a failed Verify: Payment failed, Order unpaid, no inventory commit or notification.
- Wrong, missing, empty, and malformed Authority: Verify is not called; no financial state changes.
- Invalid HMAC / unknown payment: 404 with no provider Verify.
- `NOK` and unknown status: no Verify and no success transition.
- Callback amount/order/ref tampering: ignored; SDK receives persisted Payment amount.
- Direct success/result navigation: no mutation.

## 14. Idempotency

Repeated successful callbacks create one verify transaction, one reservation commit, one paid Order transition, and one payment-success notification. Code `101` is covered as a verified, payment-bound replay success. Existing isolated real-MySQL two-worker coverage passed: 2 tests, 43 assertions.

## 15. Multiple Attempts

An old failed attempt remains failed. A late callback for it does not invoke Verify or alter a newer processing attempt, its Order payment state, or inventory.

## 16. Positive Control

A signed callback with correct stored Authority and a successful mocked ZarinPal Verify marks the Payment paid, marks the Order paid, commits one inventory reservation, persists the provider reference, and creates one success notification.

## 17. Transaction / Locking Audit

The verified Payment transition, Order lock/transition, reservation commit, and payment-status history are all inside `PaymentService`'s database transaction. The success event dispatches after commit.

## 18. Bug Record

### ZARINPAL-CALLBACK-VERIFY-001

- Severity: Medium (defense-in-depth callback validation gap; no direct paid-state bypass).
- Scenario: authenticated ZarinPal return route.
- Input: missing/empty/malformed Authority or missing/non-OK Status.
- Expected: reject before Verify.
- Actual: missing Authority/Status could reach Verify.
- Root cause: the generic fake-return compatibility condition was applied to ZarinPal.
- Fix: strict ZarinPal Authority/Status checks before Verify; failed Payment attempts are terminal for replay verification.
- Affected files: `PaymentController`, `PaymentService`, ZarinPal feature tests.

## 19. Runtime HTTP Tampering QA

Verified through isolated Laravel HTTP Feature requests using the current ZarinPal SDK client test double, with no real provider calls. Wrong Authority, failed Verify, status-only callbacks, query amount tampering, and direct success-page navigation were each inspected through persisted Payment, Order, inventory, and notification assertions. Browser automation was not needed for this server-side trust boundary.

## 20. Security Invariant

`AGENTS.md` now records the permanent rule: callback input may request verification, but only persisted attempt data plus successful authoritative provider Verify may produce financial success; callbacks are idempotent and inventory/notifications are downstream of that transition.

## 21. Tests

- `tests/Feature/Payments/ZarinPalPaymentGatewayTest.php`: 33 passed, 182 assertions.
- Focused callback/service/storefront-payment regression after the final locked-terminal-state hardening: 45 passed, 255 assertions.
- Payment/order/inventory/checkout/notification focused regression: 87 passed, 509 assertions.
- `tests/MySqlCommerce/PaymentVerificationConcurrencyTest.php` with `DB_CONNECTION=mysql`, `DB_DATABASE=ecommerce_testing`: 2 passed, 43 assertions.
- Full isolated suite: 394 passed, 2,555 assertions, 0 failures, 0 skipped; 72.33s.

Pest emitted its pre-existing inability to write `vendor/pestphp/pest/.temp/test-results`; all test commands exited successfully.

## 22. Development Validation

- `php artisan migrate`: `Nothing to migrate.`
- `php artisan migrate:status`: every migration, including the current payment-settings migration, is `Ran`.
- `vendor/bin/pint --dirty`: passed after the standard workspace formatter permission was granted; ordered imports were fixed in the ZarinPal feature test.
- `git diff --check`: passed.

## 23. Files Changed

- `AGENTS.md`
- `app/Http/Controllers/Storefront/PaymentController.php`
- `app/Services/Payments/PaymentService.php`
- `tests/Feature/Payments/ZarinPalPaymentGatewayTest.php`
- `ZARINPAL_CALLBACK_VERIFY_SECURITY_AUDIT_2026-09-05.md`

## 24. Safety

- No real ZarinPal request was made.
- No raw frontend file under `D:\uni-shop-project\front` was modified.
- No destructive command ran against `ecommerce`.
- Automated security tests used isolated SQLite or explicitly configured `ecommerce_testing` MySQL only.
- No refund, reverse, provider redesign, checkout redesign, pricing, shipping, or storefront visual work was added.
