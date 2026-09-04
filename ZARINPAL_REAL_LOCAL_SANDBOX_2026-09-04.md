# ZarinPal Real Local Sandbox Validation — 2026-09-04

## 1. Pre-Test Backup

Created before the QA customer/cart/order flow with `php artisan db:backup-development`:

`D:\\uni-shop-project\\db\\backups\\ecommerce_2026-09-04_230347.sql` (178,211 bytes)

## 2. Runtime Env

- Gateway: `zarinpal`
- Merchant configured: yes
- Merchant format: valid UUID-shaped value (not recorded)
- Sandbox: `true`
- APP_URL: `http://127.0.0.1:8000`
- Callback: generated from the current `storefront.payment.callback` route using the local APP_URL; signature redacted
- `php artisan optimize:clear`: completed successfully
- `payment:test-zarinpal-sandbox --amount=10000`: reached the real SDK path and reported provider connection failure; no fake gateway was selected

No merchant credential, password, raw provider payload, or secret was recorded.

## 3. Connectivity

`Test-NetConnection sandbox.zarinpal.com -Port 443` resolved the host and reported `TcpTestSucceeded: False` (ping succeeded). A direct hosted-page request failed with:

`An attempt was made to access a socket in a way forbidden by its access permissions. (sandbox.zarinpal.com:443)`

The payment architecture was not changed to work around this external restriction.

## 4. QA Customer

One idempotent development-only customer was created/reused:

`qa-zarinpal-sandbox@example.test`

The account is active, not soft-deleted, and was used through the normal Breeze login/session flow. No additional QA customer was created.

## 5. QA Address

One address was created/reused through `AddressService`, with Shipping Service-compatible geography (province `1`, city `39761`), valid mobile and postal-code data, and `both` address type.

## 6. Product / Cart

The normal storefront flow used the reconstructed published, in-stock simple demo Product `demo-hydra-glow-serum` (Product ID `9`). Product Detail/cart forms were used; no Product stock was manually changed. The QA cart contained one line with quantity `2` after the idempotent add flow.

## 7. Checkout

The normal authenticated sequence was exercised:

`Login → Product → Add to Cart → Cart → Checkout → Address → Pishtaz/online semantic choices → Order`

Checkout used `CheckoutService::placeOrder` through the web controller and a generated idempotency key. Shipping mode was temporarily set to `free` solely to make the reconstructed local checkout deterministic, then the previously absent `shipping.mode` row was removed to restore the pre-test default behavior. No direct Order insert was used.

## 8. Order

- Number: `ORD-01M1PYSCMXGBGNG4WTSP84CDTR`
- Grand total: `19,800,000` IRR
- Items subtotal: `19,800,000` IRR
- Discount: `0` IRR
- Tax: `0` IRR
- Shipping: `0` IRR
- Order status before/after failed provider attempt: `awaiting_payment`
- Order payment status: `unpaid`

## 9. Inventory Before Payment

The Product had `15` on-hand and `13` currently available because Checkout created one active reservation for quantity `2`. No inventory commit had occurred before payment.

## 10. Payment Initiation

- POST: `POST /orders/ORD-01M1PYSCMXGBGNG4WTSP84CDTR/payment`
- CSRF: accepted by the web middleware
- Ownership: authenticated QA customer ownership check passed
- Gateway: `zarinpal`
- First Payment ID: `1` (`PAY-01M1PYTTZ4MMM9K9R16WY3E6X2`)
- Retry Payment ID: `2` (`PAY-01M1PYVTKB1VMHJRT8MWFVQ0EA`)

The payment action was not a no-op: it created the Payment records and invoked the configured gateway adapter.

## 11. ZarinPal Request

- Reached: yes; the first request returned a provider response with code `100`
- Amount: `19,800,000` (exactly the persisted Order grand total)
- Currency: `IRR`
- Conversion: none
- Sandbox: true

The retry request reached the SDK adapter but failed safely when the provider connection was unavailable.

## 12. Authority

The first provider Request returned an authority and it was persisted on Payment ID `1` (recorded only as redacted evidence in this report). The retry failed before an authority was returned.

## 13. Redirect

The first initiation persisted a redirect URL on the Payment pointing to `https://sandbox.zarinpal.com/pg/StartPay/<redacted-authority>`. The local application returned the provider redirect as designed; the hosted navigation could not complete because outbound TCP/443 access was blocked.

## 14. Hosted Sandbox

- Loaded: no — external sandbox connection blocked
- Payment completed: not possible under the observed network restriction
- No real card or provider-security bypass was attempted

## 15. Callback

- Received: no, because the hosted page could not be loaded
- Signature: not exercised
- Authority match: not exercised

## 16. Verify

- Code: not available; no provider callback/authority completion was possible
- `ref_id`: not available
- Official Verify was not called without a legitimate hosted completion/callback

## 17. Final Payment State

- Payment ID `1`: `processing`, provider authority persisted, provider Request accepted (`code=100`)
- Payment ID `2`: `failed`, safe failure reason `ZarinPal payment is temporarily unavailable.`
- No raw SDK exception was shown to the customer

## 18. Final Order State

The Order remains `awaiting_payment` / `unpaid`, which is correct while no server-side provider verification has succeeded.

## 19. Inventory After Payment

On-hand remains `15`; available remains `13`; the reservation remains active for quantity `2`. No decrement or reservation commit occurred because Verify was never successful.

## 20. Notification

No payment-success notification was emitted (`0` customer notifications for the QA customer), consistent with the absence of a successful Verify transition.

## 21. Duplicate Callback

Not exercised because no legitimate provider completion/callback was available. The already-created Payment remains ownership-scoped and no success transition was fabricated.

## 22. Failure / Retry UX

The failed retry rendered the normal payment result page with the safe Persian message:

`پرداخت تکمیل نشد؛ می‌توانید دوباره تلاش کنید.`

The result page returned HTTP 200, exposed no merchant ID/provider payload/signature, and left the retry action available. A second initiation created a new failed attempt rather than being blocked by stale idempotency state.

## 23. Provider Errors

The adapter maps the network failure to the safe customer-facing reason `ZarinPal payment is temporarily unavailable.` and logs only exception class context. No internal exception text or credential was returned.

## 24. Full Test Suite

Focused payment/ZarinPal/checkout/inventory/notification run:

- Tests: **50 passed**
- Assertions: **256**
- Failures: **0**
- Skipped: **0**

Full isolated `php artisan test --compact`:

- Tests: **348 passed**
- Assertions: **2,149**
- Failures: **0**
- Skipped: **0**
- Duration: **114.46s**

Both runs emitted only the pre-existing non-failing Pest result-cache warning (`vendor/pestphp/pest/.temp/test-results` permission denied).

## 25. Post-Test Backup

Created after the QA flow with `php artisan db:backup-development`:

`D:\\uni-shop-project\\db\\backups\\ecommerce_2026-09-04_231119.sql` (233,029 bytes)

## 26. Database Safety

- No reset, fresh, refresh, wipe, truncate, drop, or destructive seed was run.
- Development data, including the QA Order/payment/customer and inventory history, was preserved.
- `php artisan migrate`: **Nothing to migrate.**
- `php artisan migrate:status`: all migrations reported **Ran**.

## 27. Files Changed

This validation added only this evidence report. Existing uncommitted reconstruction/storefront changes were not rewritten. No payment architecture or domain service was changed.

## 28. Raw Frontend

`D:\\uni-shop-project\\front` was not modified.

## Final Status

`ZARINPAL REAL LOCAL SANDBOX: PASS WITH EXTERNAL CONNECTIVITY LIMITATION`

