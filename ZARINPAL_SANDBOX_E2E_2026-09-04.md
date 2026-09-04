# ZarinPal Final Sandbox E2E

## 1. Environment

The prior local probe used `APP_URL=http://127.0.0.1:8000`, `APP_ENV=local`, gateway `zarinpal`, sandbox enabled, and a temporary UUID-shaped sandbox merchant value. No production credential was used.

This closure attempt did not target the persistent `ecommerce` database. The automated suite uses isolated SQLite (`:memory:`) under `APP_ENV=testing`; no isolated browser Order fixture was available for a hosted payment run.

## 2. Merchant / Metadata

The official SDK v2 requires metadata keys in its serialized request and rejects null values in the tested provider path. The current adapter therefore supplies syntactically valid fallback strings when order mobile/email are absent and uses real customer values when present. This is documented as SDK-boundary limitation; no production identity is fabricated from a real customer record.

## 3. Request Probe

`payment:test-zarinpal-sandbox --amount=10000` performed a real sandbox Request: integer amount `10000`, currency `IRR`, no conversion. It returned Authority `S00000000000000000000000000000z6gxno` and redirect host `sandbox.zarinpal.com`. The command creates no local Order, Payment, reservation, or stock mutation.

## 4. Hosted Browser Flow

The required hosted payment → callback → Verify flow was not completed. A disposable isolated browser database was available for Filament QA, but the provider connectivity check with a temporary UUID merchant failed before StartPay: `Failed to connect to sandbox.zarinpal.com port 443 after 18 ms: Couldn't connect to server`. The earlier probe with the configured value was rejected before network use because `ZarinPal merchant_id must be a valid UUID`. Consequently no hosted card interaction, callback, Verify result, provider `ref_id`, paid state, callback replay, or inventory commit is claimed. No attempt used the persistent development `ecommerce` database.

## 5. Existing Security / Idempotency Evidence

Automated provider tests cover persisted Authority and amount, HMAC callback identity, session-independent verification, wrong signature/Authority rejection, 100/101 idempotency, one-time reservation commit, and safe failure behavior. Lifecycle effects remain in `PaymentService`; the adapter does not mutate payment/order/inventory state directly.

## 6. Production Safety Recheck

Production registration remains fail-closed: fake gateway is unavailable, ZarinPal requires a valid merchant UUID, sandbox mode is rejected in production, and callback URLs derive from `APP_URL`. No production StartPay request was made.

## 7. Tests / Quality

* `ZarinPalPaymentGatewayTest`: **12 passed, 48 assertions**.
* Product/Storefront/API regressions remained green.
* Full isolated suite: **346 passed, 2,146 assertions, 0 failures, 0 skipped**.
* `php artisan migrate`: Nothing to migrate; all migrations ran.
* `vendor/bin/pint --dirty`: passed.
* `git diff --check`: passed.

## 8. Safety

Raw `D:\uni-shop-project\front` remained unchanged. No credentials, card data, raw provider payloads, Merchant ID, HMAC, or internal payment data were committed or rendered. No Refund/Reverse/provider redesign was performed.

**ZARINPAL SANDBOX E2E: PASS WITH EXTERNAL LIMITATION**
