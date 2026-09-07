# ZarinPal Checkout Redirect Debug

## 1. Required Env Variables

The current implementation reads:

* `STOREFRONT_PAYMENT_GATEWAY` — selected storefront gateway alias.
* `ZARINPAL_MERCHANT_ID` — required UUID-shaped merchant identifier for SDK construction.
* `ZARINPAL_SANDBOX` — enables sandbox mode outside production; sandbox is rejected in production.
* `APP_URL` — application base URL used to build the signed callback URL.

`ZARINPAL_ACCESS_TOKEN` is not read by the current SDK v2 Request/Verify path and is not required.

## 2. Current Runtime Config

After `php artisan config:clear`, Laravel runtime reported:

* Gateway: `null` (not configured).
* Merchant: missing (not printed).
* Merchant valid: no value to validate.
* Sandbox: `false`.
* `APP_URL`: `http://127.0.0.1:8000`.
* Callback shape: `http://127.0.0.1:8000/payment/callback/PAY-TEST?signature=x`.

The working `.env` currently has blank `STOREFRONT_PAYMENT_GATEWAY` and `ZARINPAL_MERCHANT_ID` values. Merchant secrets were never printed.

## 3. Config Cache State

The cache was cleared and runtime values were rechecked. `config:cache`, `route:cache`, and `view:cache` then completed successfully. The cached runtime reflects the same unset local gateway configuration.

## 4. Payment Button HTML

The Checkout success view renders a CSRF-protected form when `StorefrontPaymentGateway::alias()` resolves an available gateway:

* Route: `POST /orders/{order}/payment` (`storefront.payment.initiate`).
* Order identity: public `order_number`.
* Method: `POST`.
* CSRF: hidden `_token` is present.
* Middleware: `web` and `auth`.
* JavaScript: no handler intercepts the form; it submits normally.

When the runtime gateway is unavailable, the button is intentionally omitted and the page now shows: `درگاه پرداخت در حال حاضر در دسترس نیست. لطفاً بعداً دوباره تلاش کنید.`

## 5. Browser Network Trace

An isolated SQLite browser fixture was used with a valid UUID-shaped sandbox merchant and `STOREFRONT_PAYMENT_GATEWAY=zarinpal`.

* Request sent: yes.
* POST: `/orders/ORD-01M1PTHNM7TMKNHNQFVD3GNYWB/payment`.
* Response: `302`.
* Final redirect: local `/payment/result/1`.
* Visible result: `پرداخت ناموفق بود` with a retry action.
* Browser console/page/request errors: zero.

The real sandbox was not reached because the server-side SDK connection failed before StartPay. The successful redirect URL contract remains covered by the stubbed gateway tests.

## 6. Order Eligibility

The isolated Order was owned by the authenticated customer, unpaid, non-cancelled, and had active reservation coverage. Payment initiation therefore reached the gateway boundary. Existing PaymentService eligibility and idempotency rules remain unchanged.

## 7. Gateway Resolution

With the repository's current local `.env`, the resolver returns `null` because no gateway alias/merchant is configured. With a valid UUID-shaped merchant and `zarinpal` alias, the registry resolves `ZarinPalPaymentGateway`.

## 8. SDK Request

The isolated real-provider attempt reached the ZarinPal adapter boundary. Amount remained the persisted integer Order amount (`10,000`), currency remained `IRR`, and the callback URL was generated from `APP_URL`. No Authority was received because the external connection failed.

## 9. External Connectivity

Sandbox reachable: no.

Exact result with a temporary UUID-shaped sandbox merchant:

`Failed to connect to sandbox.zarinpal.com port 443 after 18 ms: Couldn't connect to server`

The configured blank merchant was separately rejected as unavailable; no production credential was used.

## 10. Root Cause

The observed “no redirect” state is configuration-related, not a malformed form or JavaScript interception: the current runtime has no selected storefront gateway and no merchant ID, so the success page does not expose an initiation button. When correctly configured, the browser submits the POST and provider failures return a visible local payment-result page.

## 11. Production Fix

The smallest focused fix was to replace the ambiguous Checkout success fallback text with an explicit, styled payment-unavailable alert. No PaymentService, gateway registry, ZarinPal adapter, Checkout, Order, Inventory, or storefront architecture was redesigned.

## 12. User-visible Error Handling

* Missing/unavailable gateway: visible Checkout success alert.
* Provider/network initiation failure: safe `پرداخت ناموفق بود` result page with retry.
* SDK/internal exception text, Merchant ID, and host details are not rendered.

## 13. Retry

Failed initiation redirects to the payment result and exposes the existing retry path. Failed initiation clears the per-order initiation key so a subsequent attempt can create a fresh Payment attempt according to existing semantics.

## 14. Playwright Regression

The isolated browser regression proved:

* authenticated customer session;
* payment form POST and CSRF;
* request reaches `/orders/{order_number}/payment`;
* provider failure redirects to `/payment/result/{payment}`;
* safe failure text is visible;
* zero browser errors.

Real hosted StartPay navigation was not possible because sandbox connectivity was unavailable.

## 15. Automated Tests

* `tests/Feature/Storefront/PaymentTest.php`: **7 passed, 48 assertions**.
* ZarinPal, PaymentService, Checkout, and Storefront payment regressions: **31 passed, 178 assertions**.
* New coverage includes the CSRF/action contract and unavailable-gateway visible message.

## 16. Full Suite

* **346 passed, 2,146 assertions, 0 failures, 0 skipped**.

## 17. Migration

`php artisan migrate`: Nothing to migrate.

`php artisan migrate:status`: all migrations reported Ran.

## 18. Cache Commands

`config:clear`, `config:cache`, `route:cache`, and `view:cache` completed successfully.

Before the final PHPUnit run, `config:clear` was run again so PHPUnit could apply its isolated array-session environment; leaving the local database-session cache in place causes expected HTTP-test 419s and is not a production runtime defect.

## 19. Pint

`vendor/bin/pint --dirty`: passed.

## 20. git diff --check

Passed with no whitespace errors.

## 21. Files Changed

* `.env.example` — documented required local sandbox and production configuration; no secrets.
* `resources/views/storefront/checkout/success.blade.php` — explicit unavailable-gateway alert.
* `tests/Feature/Storefront/PaymentTest.php` — payment form and unavailable-gateway regressions.
* `ZARINPAL_CHECKOUT_REDIRECT_DEBUG_2026-09-04.md` — this report.

## 22. Raw Frontend

`D:\uni-shop-project\front` remained unchanged.

## Final Status

**ZARINPAL CHECKOUT REDIRECT: PASS WITH EXTERNAL CONNECTIVITY LIMITATION**
