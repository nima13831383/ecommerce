# Checkout Missing Shipping/Cart Debug — 2026-09-04

## 1. Error Source

- File: `app/Http/Controllers/Storefront/CheckoutController.php`
- Method: `show()`
- Previous branch: the preview call caught every `CheckoutValidationException|DomainException` and set `previewError` to `اطلاعات ارسال یا سبد خرید برای ثبت سفارش کامل نیست.`
- Participating inputs: the current authenticated Cart ID/items, the authenticated user's selected/default Address ID, the Address province/city, `shipping_service`, `shipping_payment_type`, Checkout idempotency key, Cart sellability/stock, and all Shipping settings consumed by `CheckoutService::preview()`.

The same broad catch in `store()` also obscured shipping configuration and address failures during submission.

## 2. Root Cause

The reconstructed development database had no persisted Shipping settings. Runtime defaults therefore selected calculator mode with no valid origin province/city and no package definitions. The admin's Cart and Address were valid, but `ShippingCostResolver::quote()` rejected the quote at origin validation with `ShippingConfigurationException: استان و شهر مبدأ ارسال معتبر نیستند.` This was then collapsed into the generic checkout message before an Order could be placed.

## 3. Admin User

- Authenticated: yes, existing primary super-admin `nsarreshtehdari87@gmail.com` (not recreated and role unchanged)
- Cart: authenticated Cart ID `1`, owned by user ID `7`
- Address: authenticated Address ID `2`, owned by user ID `7`, default and `both` type

## 4. Cart

- Owner: User ID `7` through the authenticated `StorefrontCartContext`/`CartService` path
- Items: one simple Product line, Product ID `11`, quantity `1` during the successful post-fix browser run
- Subtotal before shipping: `4,900,000` IRR
- Mismatch: none; header/cart and Checkout resolved the same authenticated Cart ID

## 5. Address

- Ownership: Address ID `2` belongs to the authenticated admin
- Province: ID `17`
- City: ID `65931`
- Shipping-compatible: yes; validated through `AddressService` and the authoritative Shipping geography loader

## 6. Shipping Runtime Settings

Before the fix, the Setting service returned the repository defaults: calculator mode, null origin province/city, fixed rate `0`, and an empty package list.

The focused development repair configured:

- Mode: `calculator`
- Origin province: ID `2` (`گیلان`)
- Origin city: ID `4391` (`آستارا`)
- Fixed rate: `0` (unused in calculator mode)
- Packages: one active development-default package, code `1`, capacity volume `1000`, maximum weight `30000`

These are explicitly development defaults requiring production confirmation; no shipping algorithm or origin architecture was changed.

## 7. Shipping Quote Before Fix

- Generated: no
- Failure: `ShippingConfigurationException` with `استان و شهر مبدأ ارسال معتبر نیستند.`
- Browser evidence: Playwright login as the existing super-admin, then `/checkout`, returned HTTP 200 with the generic alert and a disabled submit button.

## 8. Product Shipping Data

The admin Cart Product (ID `11`) has positive base weight `0.08` kg, volume `70` cm³, and normal parcel type. With the development package, the authoritative calculator selected package code `1` and normalized weight to `80` grams. No Product shipping fields were invented or changed.

## 9. Fix

- Settings: persisted valid development calculator origin/package configuration through `SettingsService`; no free-shipping bypass was used.
- Code: `CheckoutController` now maps `ShippingConfigurationException` to `محاسبه هزینه ارسال در حال حاضر امکان‌پذیر نیست.`, `AddressValidationException` to `لطفاً آدرس ارسال را انتخاب کنید.`, and other preview validation to `اطلاعات ارسال کامل نیست. لطفاً روش ارسال را دوباره انتخاب کنید.` The submission path uses the same safe distinctions.
- UI: the existing Checkout Blade form now displays the specific safe message and enables submission when the server preview is valid.

## 10. Shipping Quote After Fix

- Services: `pishtaz` and `vijeh` remained available from `ShippingOptionCatalog`.
- Selected: `pishtaz` with `online` payment type.
- Result: authoritative calculator quote available, `1,204,500` IRR, currency `IRR`, package code `1`, weight `80` grams, parcel type `normal`.

## 11. Checkout Request

Before: the browser sent no placement request because the preview catch set `previewError` and disabled the submit button. The server-side preview failure was caused by missing origin/package settings, not missing Cart or Address data.

After: the real Blade form submitted the semantic fields `shipping_address_id`, `shipping_service`, `shipping_payment_type`, `idempotency_key`, and optional `customer_note` (plus CSRF, not recorded). No price, shipping amount, weight, package, origin, or inventory fields were trusted.

## 12. Order Creation

- Succeeded: yes, through the existing Checkout web controller and `CheckoutService::placeOrder()`.
- Order number: `ORD-01M1Q01Q579CCAE3P4GPCEPAQM`
- Status: `pending`
- Payment status: `unpaid`
- Grand total: `6,594,500` IRR
- Shipping snapshot: calculator/Pishtaz, `1,204,500` IRR, origin IDs `2/4391`, destination IDs `17/65931`

## 13. Inventory

- On-hand after successful Checkout: `2` for Product ID `11`
- Available: `1`
- Reservation: one active reservation for quantity `1`
- Physical commit: none; payment remained unpaid and no inventory decrement occurred

## 14. Error UX

- Empty Cart: existing `CheckoutController` redirects safely with `برای ادامه، ابتدا محصولی به سبد خرید اضافه کنید.`
- Missing Address: existing form state shows `برای ثبت سفارش ابتدا یک آدرس در حساب کاربری خود ثبت کنید.`; the regression also proves the generic preview message is not used.
- Missing Shipping/configuration: now `محاسبه هزینه ارسال در حال حاضر امکان‌پذیر نیست.`
- Other invalid shipping/address selection: safe non-internal messages are used; raw exceptions are not rendered.

## 15. Playwright

Checks:

1. Existing super-admin login → `/checkout` with reconstructed defaults reproduced the exact generic error and disabled submit.
2. After SettingsService configuration, the same Playwright flow rendered `/checkout` without an alert, enabled the submit button, selected the owned Address/Pishtaz/online values, and reached `/checkout/success/8`.

Passed: both before-fix reproduction and after-fix success checks.

Failed: none.

## 16. Focused Tests

- New regression, first independent run: **2 passed, 13 assertions, 0 failures, 0 skipped**.
- New regression, second independent run: **2 passed, 13 assertions, 0 failures, 0 skipped**.
- Checkout/shipping/cart/address/order/inventory focused run: **57 passed, 346 assertions, 0 failures, 0 skipped**.

All focused runs emitted only the existing non-failing Pest result-cache permission warning.

## 17. Full Suite

- Tests: **350 passed**
- Assertions: **2,162**
- Failures: **0**
- Skipped: **0**
- Duration: **73.83s**

## 18. Pre-Test Backup

`D:\\uni-shop-project\\db\\backups\\ecommerce_2026-09-04_230347.sql` (178,211 bytes)

## 19. Post-Test Backup

`D:\\uni-shop-project\\db\\backups\\ecommerce_2026-09-04_233105.sql` (313,195 bytes)

## 20. Development DB Safety

No destructive operation was run. No `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, truncate, drop, destructive seed, or reset was used. The QA Order and reservation were retained; no direct SQL cleanup was performed. `php artisan migrate` reported `Nothing to migrate`, and all migration statuses were `Ran`.

## 21. Files Changed

- `app/Http/Controllers/Storefront/CheckoutController.php`
- `tests/Feature/Storefront/CheckoutMissingShippingRegressionTest.php`
- `CHECKOUT_MISSING_SHIPPING_CART_DEBUG_2026-09-04.md`

## 22. Raw Frontend

`D:\\uni-shop-project\\front` was not modified.

## Final Status

`CHECKOUT SHIPPING/CART BUG: VERIFIED PASS`

