# STOREFRONT FINAL BROWSER QA — 2026-09-04

## 1. Browser Runner

- Tool: Playwright (real Chromium, headless)
- Version: 1.62.1 (`D:\uni-shop-project\front\node_modules\playwright`)
- Browser: bundled Chromium executable `chromium-1234`
- App URL: `http://127.0.0.1:8003`
- Environment: local Laravel server, `APP_ENV=local`, QA-only `STOREFRONT_PAYMENT_GATEWAY=fake`, reversible fixed shipping fixture
- The existing `127.0.0.1:8000` server was not used because its `APP_URL` did not match public media URLs.

## 2. Browser Test Files

- `tests/Browser/storefront-final-qa.mjs`
- The same runner was executed independently three times (`run-3.json`, `run-4.json`, and `run-final.json`); the final run includes the Blog search/page-two and FAQ accordion checks.

## 3. Viewports Tested

`1440×900`, `1280×800`, `768×1024`, `430×932`, `390×844`, and `320×700`.

## 4. Global Shell

- Guest and authenticated headers rendered through Blade.
- RTL document, header/footer, typography assets, mobile drawer, cart preview, search, and responsive shell were exercised.
- No horizontal overflow was detected in the exercised pages/viewports.
- Cart preview was verified after fixing its anchor navigation behavior.

## 5. Home

HTTP 200; real catalog cards, pricing, availability, links, hero, banners, brand/newsletter sections, and mobile rendering passed.

## 6. Product Listing

Search, type, stock, min/max price, newest/price sorting, query persistence, pagination rendering, and empty results were exercised. No application request failed.

## 7. Product Detail

Simple and variable detail pages rendered. Gallery thumbnail swapping, quantity controls, image fallback behavior, and Product Detail → Cart form submission were exercised.

## 8. Variable Product

- Aurora Velvet: available `50ml + standard` resolved and enabled Add to Cart; unavailable `50ml + gift` disabled it.
- Variation-resolution requests were observed in the browser Network log.
- Rapid option switching retained the newest response through the existing request-id/abort logic.

## 9. Cart

Guest and authenticated carts were exercised: add, header count/preview, open Cart, quantity update, remove, clear, empty state, coupon state, and shipping selection. Cart totals remained server-rendered; no client-side authoritative arithmetic was observed.

## 10. Authentication

Register, login session establishment, authenticated header, logout, guest header restoration, invalid/forgot-password page presentation, and account access were exercised. A unique `qa.browser.*@example.test` user was used for each run.

## 11. Account / Profile

Dashboard, profile update, account navigation, and responsive account shell passed. No roles/permissions or fake metrics were rendered.

## 12. Address / Geography

Address creation used the real form. Province selection triggered the real cities request, valid cities populated, and the persisted address reloaded.

## 13. Coupon

A temporary QA-only `BQA0904` fixed-cart fixture was used because the pre-existing development coupon was restricted to another user. Applying it and rendering the resulting server state passed; the fixture was soft-deleted after QA and no unrelated coupon was changed.

## 14. Shipping

The browser selected an owned address and shipping service/payment type. The quote response rendered from the server. The temporary fixed-rate setting was removed after QA; no caller-controlled origin, package, weight, volume, parcel nature, or shipping amount was submitted.

## 15. Checkout

Authenticated browser flow completed: Product → Cart → account/address → coupon → shipping quote → Checkout → one Order-created success page. A QA note was persisted. The success page correctly remained unpaid until payment verification.

## 16. Payment

- Local/testing-only fake gateway was enabled on the QA server through `STOREFRONT_PAYMENT_GATEWAY=fake`.
- Order → payment initiation → fake verification → payment result was executed twice.
- Result pages rendered the verified paid state; duplicate return/revisit behavior remained stable.
- No real provider was added; production remains without a gateway.
- The runner does not claim a fake-decline/retry branch because the browser-facing fake gateway has no user-selectable failure control.

## 17. Orders

Authenticated Orders list and screenshots were captured after Order creation. Order number, status, payment state, totals, and links rendered.

## 18. Shipment

Customer shipment mutation controls were absent. Existing Order/Shipment presentation remains covered by the PHP Orders tests; this browser pass did not create a new shipment state because no browser-safe fixture action exists.

## 19. Blog

Published Blog listing, images, pagination-capable listing, and Article navigation were loaded. Scheduled/draft records were not requested publicly.

## 20. Article

The published Persian Article layout, breadcrumb, category/tags/body presentation, related section, and responsive rendering were loaded through the real browser.

## 21. Static Pages

About, Contact, and FAQ loaded. FAQ accordion interactions were exercised. Contact remains intentionally non-submitting/deferred.

## 22. 404

Nonexistent web, Product, and Blog paths returned the custom RTL HTML 404 at desktop/mobile widths. `/api/v1/products/not-a-real-product` remained a JSON 404.

## 23. Link Crawl

The exercised header, footer, Product cards, breadcrumbs, account links, Cart, Blog, and static-page links resolved without an unexpected application failure. No raw `.html` or Windows-path links were observed in these flows.

## 24. Console Audit

- Before fixes: asset 403s, missing account stylesheet, and the cart-preview navigation defect were observable.
- After fixes: zero unexplained application console errors. The three remaining generic 404 console notices came only from intentionally requested nonexistent web routes.

## 25. Network Audit

- Before fixes: Product demo media returned 403 under the local/private disk and an account stylesheet returned 404.
- After fixes: 0 failed requests in both browser runs; no 500s, failed AJAX calls, missing CSS/JS, or broken loaded media.

## 26. Responsive QA

All six required viewport sizes passed the no-overflow smoke checks for Home, listing, Product Detail, Blog, and FAQ. Mobile menu and Cart preview were explicitly exercised at 390px. Screenshots include desktop and mobile evidence.

## 27. Visual Parity

- Expected dynamic differences: real demo catalog/blog text, pricing, availability, account and Order state.
- Intentional differences: checkout/payment controls reflect local fake-gateway and server state.
- Objective regressions fixed: malformed Product purchase form, hidden address form, cart-preview anchor navigation, missing account CSS reference, and local QA media exposure setup.

## 28. Screenshots

QA artifacts are outside public assets:

`D:\uni-shop-project\ecommerce-main\storage\app\qa\storefront-final-2026-09-04\`

Captured: `home.png`, `products.png`, `blog.png`, `variable-product.png`, `mobile-home.png`, `variable-product-selected.png`, `mobile-cart-empty.png`, `account-addresses.png`, `authenticated-cart-shipping.png`, `checkout-success.png`, `payment-result.png`, `orders.png`, and `404-mobile.png`.

## 29. Bugs Found

1. `BROWSER-BUG-001` — Medium — Product Detail purchase form had an extra closing div, so hidden semantic inputs were parsed outside the form and a real click could not submit Product/variation/quantity.
2. `BROWSER-BUG-002` — Medium — Address form panel was permanently hidden by CSS/class state, blocking browser address creation.
3. `BROWSER-BUG-003` — Medium — Header Cart preview bound to an anchor without preventing navigation, bypassing the preview.
4. `BROWSER-BUG-004` — Low — Account layout referenced nonexistent `account/addresses.css`, generating a browser 404.
5. `BROWSER-BUG-005` — Environment — Existing demo Product media was on the private local disk while public URLs used `/storage`; QA mirrored only the existing demo SVGs into the public storage link and used an APP_URL-aligned local server. Production must configure the intended public media disk/link.

## 30. Bugs Fixed

- BROWSER-BUG-001: `resources/views/storefront/products/show.blade.php` — corrected form nesting; the browser Add-to-Cart flow now submits and persists a line.
- BROWSER-BUG-002: `resources/views/storefront/account/addresses.blade.php` — address form is open for the SSR page.
- BROWSER-BUG-003: `public/storefront/assets/js/homepage/cart-dropdown.js` — prevent default anchor navigation while opening the preview.
- BROWSER-BUG-004: `resources/views/storefront/layouts/account.blade.php` — removed the nonexistent stylesheet reference.
- BROWSER-BUG-005: QA-only public mirror of existing demo SVGs; no Product domain or storage architecture redesign was made.
- Local/testing fake gateway support: `app/Services/Payments/FakePaymentGateway.php` and local/testing registry binding in `app/Providers/AppServiceProvider.php`, gated away from production.

## 31. Browser Test Result

- Tests/assertions: 213 browser assertions in the final run
- Run 1: 210 passed, 0 failed
- Run 2: 210 passed, 0 failed
- Final run: 213 passed, 0 failed
- Skipped: 0
- Network failures after fixes: 0

## 32. Storefront PHP Suite

`php artisan test tests/Feature/Storefront --compact`: **57 passed (484 assertions)**, 0 failures, 0 skipped.

## 33. Full Isolated Suite

`php artisan test --compact`: **323 passed (2,059 assertions)**, 0 failures, 0 skipped.

The existing Pest result-cache permission warning was emitted but did not affect test results.

## 34. Migration

- `php artisan migrate`: Nothing to migrate.
- `php artisan migrate:status`: all migrations `[1] Ran`.

## 35. Pint

`vendor/bin/pint --dirty`: passed.

## 36. git diff --check

Passed with no whitespace errors.

## 37. Deployment Checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, and the correct public `APP_URL` (including the externally reachable scheme/host/port where applicable).
- Configure the production database and take a backup before migrations.
- Run `php artisan migrate --force` non-destructively during deployment.
- Run `php artisan storage:link`; ensure the configured Product/Blog media disk is publicly served as intended.
- Make `storage` and `bootstrap/cache` writable by the application process.
- Configure queue workers, scheduler coordination, cache driver, queue driver, and Redis only according to the existing deployment architecture.
- Use `php artisan config:cache`, `route:cache` where compatible, and `view:cache`.
- Enforce HTTPS and secure HTTP-only session cookies.
- Keep the fake gateway unavailable in production; a real payment provider is not configured yet.

## 38. Production Files Changed

- `resources/views/storefront/products/show.blade.php`
- `resources/views/storefront/account/addresses.blade.php`
- `resources/views/storefront/layouts/account.blade.php`
- `public/storefront/assets/js/homepage/cart-dropdown.js`
- `app/Services/Payments/FakePaymentGateway.php`
- `app/Providers/AppServiceProvider.php`
- `tests/Browser/storefront-final-qa.mjs`
- this report

Other existing working-tree changes belong to the prior storefront/domain phases and were preserved.

## 39. Raw Frontend Source

`D:\uni-shop-project\front` was not modified.

## 40. Development Data Safety

- No destructive database command was run.
- `ecommerce` was not reset.
- Existing catalog/blog data was preserved.
- Browser QA created only clearly prefixed QA users, carts, addresses, Orders, and Payments; the temporary coupon was soft-deleted and temporary shipping settings were removed.
- QA screenshots/logs are under ignored `storage/app/qa` and outside production public assets.

## 41. Remaining Known Limitations

- No real Payment provider is selected/configured; only the local/testing fake gateway was exercised.
- Guest Cart → authenticated Cart merge remains intentionally deferred.
- Contact submission remains intentionally deferred.
- Returns/Refunds and customer Shipment mutations remain out of scope.
- Browser QA did not fabricate shipment lifecycle fixtures where no browser-safe setup action exists; the customer read-only Shipment contract remains covered by the existing PHP feature/domain tests.

`STOREFRONT FINAL BROWSER QA: PASS WITH DOCUMENTED LIMITATIONS`
