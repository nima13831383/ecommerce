# Storefront Strict Parity + Jalali QA

## 1. Raw Template Audit

Audited the supplied raw pages (`index.html`, `category.html`, `search.html`, `product.html`, `cart.html`, `checkout.html`, auth pages, account/address/profile/orders/order-detail, `blog.html`, `article.html`, `about.html`, `contact.html`, `faq.html`, `404.html`, `wishlist.html`, and `payment-result.html`) and the associated CSS/JS/assets. `D:\uni-shop-project\front` was not edited.

## 2. Header

Cart is now a button that toggles the preview; the heart is independent; the count is an accent badge rendered only for non-empty carts; preview actions are «مشاهده سبد خرید» and «تسویه حساب» (`/cart`, `/checkout`); account text clutter was removed; both search forms submit on Enter or icon click and preserve `search`; route-aware active navigation covers Home, Products, Blog, About, Contact and FAQ.

## 3. Blog / Article

Blog and article continue to use the existing breadcrumb/card structure. Human dates now use the centralized Jalali formatter. Article headings are no longer affected by the SSR sticky-header rule; related content remains same-category and published.

## 4. Product Archive

Product cards now include the raw heart control and authoritative percentage badges (`٪`, without «تخفیف»). Category and brand controls accept arrays (`categories[]`, `brands[]`), individual selections can be cleared, and «پاک کردن همه» resets the query. Filter groups start collapsed. Filtering and sorting use an AJAX fragment endpoint with History API and stale-request sequencing; normal links remain the fallback. The apply button was removed.

## 5. Product Detail

Add-to-cart uses a CSRF-protected AJAX form and server-returned cart state; no client-side price/stock arithmetic was added. Four trust blocks, deterministic same-category related products, and a truthful empty Reviews section were restored. Variation resolution continues to use the existing API contract and authoritative service.

## 6. Account / Addresses / Auth / FAQ / Home

Account navigation now restores icons and Orders, logout remains POST+CSRF, dashboard presents four truthful tiles and account information, and Addresses initially hides the form until «افزودن آدرس جدید» (or edit/validation) opens it. Auth pages omit the storefront footer, use migrated raw background assets and trust strip, and retain Breeze routes. Home categories are loaded from active public Category records and link to filtered Products; generic trust copy is «ارسال سریع». FAQ/About routes and navigation remain available.

## 7. Jalali Architecture

`App\Support\JalaliDate` is the single presentation formatter. Database timestamps remain canonical Gregorian/DateTime; public/storefront human dates are Tehran-local Jalali. `APP_TIMEZONE` defaults to `Asia/Tehran` and APIs retain machine-oriented timestamps.

## 8. Browser QA

Playwright/Chromium headless smoke QA ran against the local Laravel server at 1440, 1280, 768, 430, 390 and 320px. RTL markup, filter-group collapsed initial state, cart-preview toggle, active Home navigation, search submission, Product Detail trust/review/related sections, and zero console/network errors were verified. The CUA surface itself failed to initialize, so no interactive screenshots were captured.

## 9. PHP / Quality Results

* Storefront tests: **58 passed, 486 assertions, 0 failures, 0 skipped** (including the Jalali test).
* Product API tests: **16 passed, 115 assertions, 0 failures, 0 skipped**.
* Full isolated suite: **341 passed, 2,131 assertions, 0 failures, 0 skipped**.
* `php artisan migrate`: Nothing to migrate; migration status clean.
* `vendor/bin/pint --dirty`: passed (including Jalali formatter).
* `git diff --check`: passed.

## 10. Files / Limitations

Changed Laravel storefront/query/controller/resource/assets plus `app/Support/JalaliDate.php`; copied only required auth background assets. Raw source unchanged. Filament date-picker input conversion and complete real-browser parity remain follow-up work because browser initialization was unavailable; no business-domain redesign was made.

**STOREFRONT STRICT PARITY + JALALI: PASS WITH DOCUMENTED LIMITATIONS**
