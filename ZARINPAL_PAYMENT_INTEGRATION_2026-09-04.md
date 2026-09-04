# ZarinPal Payment Integration

## 1. Official Sources Used

- Docs: <https://www.zarinpal.com/docs/>
- PHP SDK: <https://github.com/ZarinPal/zarinpal-php-sdk>
- Package metadata: <https://packagist.org/packages/zarinpal/zarinpal-php-sdk>
- SDK version installed: `2.0.0` (commit `7025d1562796938f08e95e54d7ac272d1fcec2c1`)

The current SDK exposes `Options`, `ZarinPal::paymentGateway()`, `RequestRequest`, `VerifyRequest`, `getRedirectUrl()`, and verification response codes. The installed source validates UUID-shaped merchant IDs, integer amounts (minimum 1000 IRR), and `IRR`/`IRT` currencies.

## 2. Existing Payment Architecture

`PaymentService`, `Payment`, order payment transitions, inventory commit, reconciliation, and the existing provider registry remain authoritative and unchanged in responsibility. ZarinPal is an adapter behind `PaymentGatewayInterface`; it does not mark payments paid, transition orders, commit inventory, or dispatch success notifications.

## 3. ZarinPal Adapter

- Class: `App\Services\Payments\ZarinPalPaymentGateway`
- Interface: `App\Contracts\Payments\PaymentGatewayInterface`
- SDK boundary: `App\Contracts\Payments\ZarinPalClientInterface` and `App\Services\Payments\ZarinPalSdkClient`
- Registry alias: `zarinpal`

## 4. SDK Installation

Composer package: `zarinpal/zarinpal-php-sdk:^2.0`  
Installed version: `2.0.0`

## 5. Configuration

- Default gateway setting: `STOREFRONT_PAYMENT_GATEWAY`
- Merchant ID: `ZARINPAL_MERCHANT_ID` (environment secret, UUID-shaped)
- Sandbox: `ZARINPAL_SANDBOX` (`true` for local sandbox, `false` for production)
- Currency: `IRR`
- Access token: not required by the official Payment Request/Verify API and is not configured

All environment reads are in `config/payment.php`; runtime code uses `config()` and remains config-cache compatible.

## 6. Credential Terminology

`merchant_id` is the UUID-shaped credential required by Payment Request/Verify. The SDK also has an `access_token` option for other services; it is not required for this normal payment flow and no access token is requested.

## 7. Sandbox Credential Verification

- Expected format: UUID-shaped 36-character merchant ID (the SDK validates lowercase hexadecimal UUID syntax).
- Actual result: a real sandbox Request succeeded with the temporary UUID-shaped value `aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa` and returned an Authority.
- Was arbitrary 36-character UUID accepted? **YES for the tested sandbox Request**. This was not used as a production credential and no real production credential was stored.

The initial sandbox probe exposed an SDK v2/API compatibility issue where null metadata values were rejected. The adapter now supplies syntactically valid fallback metadata only when optional customer values are absent; real order mobile/email values are used whenever available.

## 8. Currency

- Application: integer Rial (`IRR`)
- ZarinPal: explicit `IRR`
- Conversion: **NONE**

No float, Toman conversion, multiplication, or division is performed.

## 9. Payment Request

- Amount: persisted `Payment.amount`, derived from `Order.grand_total`
- Description: deterministic `پرداخت سفارش {ORDER_NUMBER}`
- Callback: generated Laravel route `storefront.payment.callback` with a persistent payment-number identity and HMAC callback signature
- Optional metadata: customer mobile/email where present; no card or sensitive data

## 10. Authority Persistence

The SDK Authority is returned as `PaymentInitiationResult::providerPaymentIdentifier`; existing `PaymentService` persists it in `payments.authority` and the request metadata in the existing gateway response/transaction fields. It is not kept only in session.

## 11. Redirect

- Production: SDK-generated payment URL uses the configured production base URL when `sandbox=false`.
- Sandbox: SDK-generated URL observed as `https://sandbox.zarinpal.com/pg/StartPay/{authority}`.

The controller redirects only to the server-generated URL; browser-submitted URLs are not accepted.

## 12. Callback

The thin unauthenticated callback route accepts the provider's `Authority` and `Status`, validates the HMAC identity and persisted Authority, and redirects to the existing authenticated result page. A `Status=OK` query is only a gate to server verification; it is never treated as proof. Non-OK status is shown safely and does not mutate payment state.

## 13. Verify

- Authoritative amount: persisted `Payment.amount`; callback/query amounts are ignored.
- Code `100`: normalized to verified success.
- Code `101`: normalized to verified idempotent success.

Existing `PaymentService` locking and paid-state checks ensure only one inventory commit/order transition/success event occurs.

## 14. Reference ID

Successful `ref_id` is returned as `PaymentVerificationResult::providerReference` and persisted by `PaymentService` in `payments.reference_id`. It is never fabricated.

## 15. Failure / Cancellation

Provider request/verify failures are normalized to safe application failure reasons. Callback cancellation (`Status` other than `OK`) does not call Verify, does not mark the payment paid, and does not commit inventory. The idempotency key is released for a later customer retry while the provider-side attempt remains auditable.

## 16. Provider Errors

Official SDK exceptions are caught at the adapter boundary, logged with payment/order IDs and exception class only, and converted to customer-safe messages. Merchant IDs, access tokens, raw payloads, and card data are not logged or rendered.

## Proven Issue and Fix

`ZARINPAL-BUG-001` — Severity: Medium. The first real sandbox Request with absent optional metadata failed because SDK v2 serialized `null` mobile/email values and the provider rejected them as non-string metadata. The smallest focused repair was to normalize only absent optional metadata at the official-SDK boundary to valid string values; real order customer values remain preferred. The sandbox Request then returned an Authority successfully. A regression is covered by the SDK/adapter tests and no raw REST implementation was added.

## 17. PaymentService Integration

The adapter does **not** directly:

- mark a Payment paid
- transition an Order
- commit inventory
- create notification intents

All of those remain in `PaymentService` and existing order/inventory/event flows.

## 18. Inventory

- Before verify: active reservation remains; no stock deduction.
- After verify: existing `PaymentService` commits the reservation exactly once.
- Duplicate callback/verify: paid-state/idempotency guards prevent a second commit or success history.

## 19. Reconciliation

Amount mismatch, cancelled orders, expired reservations, late captures, and duplicate verification continue through the existing `PaymentService` reconciliation behavior. No ZarinPal-specific reconciliation path was added.

## 20. Security

- Secrets: environment configuration only; no credentials committed.
- Logs: payment/order IDs and exception class only.
- Sensitive data: no PAN/CVV, provider payload, merchant ID, or access token exposed to customers.
- Client amount: ignored; Payment/Order state is authoritative.
- Callback: HMAC-signed payment-number route plus persisted Authority comparison; callback status cannot authorize payment by itself.

## 21. Production Safety

- Fake: retained for local/testing and unavailable in production.
- ZarinPal: registered only with a valid merchant ID; production also refuses sandbox mode.
- Sandbox in production: fail closed.
- Missing/malformed credentials: no active storefront gateway and safe unavailable UX.

## 22. Sandbox Runtime Test

The local-only command `php artisan payment:test-zarinpal-sandbox` was run with a temporary UUID-shaped merchant ID:

- Request: **succeeded**
- Authority: `S00000000000000000000000000000z6gxno`
- Redirect: `https://sandbox.zarinpal.com/pg/StartPay/S00000000000000000000000000000z6gxno`
- Callback: not completed against a human/browser sandbox payment
- Verify: not attempted without a completed sandbox transaction

The command is local/development/testing-only and does not create a local Order, Payment, reservation, or database record.

## 23. Browser Sandbox QA

**PARTIAL** — real sandbox Request and handoff URL were runtime verified. Completing the hosted sandbox payment and provider callback/Verify requires a human-compatible sandbox interaction and was not claimed as automated browser coverage.

## 24. Automated ZarinPal Tests

`tests/Feature/Payments/ZarinPalPaymentGatewayTest.php`: **12 tests, 48 assertions, 0 failures**.

Coverage includes registry/config fail-closed behavior, production fake/sandbox blocking, integer IRR amount, callback URL/description, Authority/redirect persistence path, 100/101 verification, failure normalization, callback tamper safety, one-time inventory commit, and the installed official SDK Request/Verify/redirect contract.

## 25. Payment Regression

Payment/checkout/inventory/notification regression command: **39 tests, 210 assertions, 0 failures**.

## 26. Checkout Regression

Included in the 39-test regression run: **7 Checkout tests passed**.

## 27. Inventory Regression

Included in the regression run: **7 Inventory tests passed**; no reservation is created by provider Request and no stock is changed before Verify.

## 28. Notification Regression

Included in the regression run: **6 Notification tests passed**; success notification remains emitted only by the existing PaymentService success path.

## 29. Storefront Regression

Existing Storefront Payment tests: **5 passed, 40 assertions**. Existing checkout/order storefront flows remain green in the full suite.

## 30. Full Isolated Suite

`php artisan test --compact`: **340 tests passed, 2,129 assertions, 0 failures, 0 skipped**.

The Pest result-cache permission warning is non-fatal and does not affect test execution.

## 31. config:cache

Passed: `Configuration cached successfully.`

## 32. route:cache

Passed: `Routes cached successfully.`

## 33. view:cache

Passed: `Blade templates cached successfully.`

## 34. Migration

`php artisan migrate`: **Nothing to migrate.**  
`php artisan migrate:status`: all listed migrations **[1] Ran**.

## 35. Pint

`vendor/bin/pint --dirty`: passed.

## 36. git diff --check

Passed with no output.

## 37. Files Changed

- `composer.json`
- `composer.lock`
- `.env.example`
- `config/payment.php`
- `app/Contracts/Payments/ZarinPalClientInterface.php`
- `app/Services/Payments/ZarinPalSdkClient.php`
- `app/Services/Payments/ZarinPalPaymentGateway.php`
- `app/Services/Payments/PaymentCallbackSigner.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/Storefront/PaymentController.php`
- `routes/web.php`
- `app/Console/Commands/TestZarinPalSandbox.php`
- `tests/Feature/Payments/ZarinPalPaymentGatewayTest.php`

## 38. Raw Frontend

`D:\uni-shop-project\front` unchanged. No frontend code was added or modified.

## 39. Production Environment Variables Required

- `STOREFRONT_PAYMENT_GATEWAY=zarinpal`
- `ZARINPAL_MERCHANT_ID=<real production UUID-shaped merchant ID>`
- `ZARINPAL_SANDBOX=false`
- `APP_URL=https://<production-host>` with HTTPS callback reachability

No access token is required for the implemented Payment Request/Verify flow.

## 40. Remaining Launch Blockers

- Obtain and inject the real production ZarinPal Merchant ID through the deployment secret store.
- Configure a publicly reachable HTTPS `APP_URL`/callback in production.
- Complete a human/browser sandbox payment and Verify if the deployment team requires that external end-to-end evidence before launch.
- Refund/Reverse remain intentionally out of scope.

No real production payment was made.

## Status

`ZARINPAL PAYMENT INTEGRATION: PASS WITH SANDBOX LIMITATION`
