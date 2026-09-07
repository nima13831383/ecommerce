# 404 + ACCOUNT RAW TEMPLATE PARITY FIX

## 1. 404 Root Cause

The Laravel error view was a minimal placeholder (`not-found-card`) and did not represent the raw `front/404.html` composition. The raw page’s two supporting stylesheets were also absent from the migrated public asset tree.

## 2. 404 Raw vs Blade Differences

The raw page contains a hero, recovery search, suggested-product rail, trust features, newsletter, and shared site chrome. The prior Blade view rendered only a compact error card. The Blade conversion now preserves those sections and the raw RTL class structure; shared header/footer continue to come from the Laravel storefront layout.

## 3. 404 Fix

`resources/views/errors/404.blade.php` now renders the raw-template hero, recovery search, suggestions, trust row, newsletter, and Laravel home/product links. The recovery script submits to the Laravel `/products?search=...` route instead of a static `search.html` path. The missing `components/public-page.css` and `product/reviews.css` assets were copied byte-for-byte from the raw source.

## 4. Account Root Cause

The account dashboard did not match `front/account.html`: it used generic cards, placed account information in the top row, omitted the raw breadcrumb/mobile navigation structure, and did not distinguish pending from completed orders.

## 5. Top Dashboard Cards Before

The previous top row showed generic Orders, Addresses, Account Information, and Favorites cards. It did not expose the raw template’s four semantic summary sections.

## 6. Top Dashboard Cards After

The top row now contains, in raw-template order:

1. `سفارش‌های در انتظار` — live count of pending/active order states.
2. `سفارش‌های تکمیل‌شده` — live count of completed orders.
3. `آدرس‌های ثبت‌شده` — live owned-address count.
4. `علاقه‌مندی‌ها` — truthful `—` placeholder with `این بخش به‌زودی فعال می‌شود`; no unsupported wishlist metric is fabricated.

Counts are prepared by `StorefrontOrderQuery::dashboardCounts()` and the existing account controller data flow.

## 7. Account Information Placement

Account Information now appears in the lower `.account-columns` row beside Latest Orders, matching the raw composition. It displays only the current customer’s name, mobile fallback, and email.

## 8. Latest Orders

The lower Latest Orders card renders real recent order snapshots with order number, Jalali date, item count, integer-Rial amount, status label, and Order Detail links. Empty accounts retain the real empty state.

## 9. Logout Button

Logout remains a CSRF-protected POST form and is wrapped in the raw `.account-nav__logout` structure. No GET logout behavior was introduced.

## 10. Sidebar

The account sidebar now retains the raw profile/navigation styling and active states. A wishlist navigation item was not invented because no supported storefront wishlist route was required for this fix; the dashboard card explicitly remains deferred.

## 11. Raw CSS / Asset Reuse

Existing migrated account CSS and JavaScript were reused without visual rewrites. The following raw 404 assets are now present under `public/storefront` and matched by SHA-256:

- `assets/css/components/public-page.css`
- `assets/css/product/reviews.css`
- `back-404.png`
- `back-404-mobile.png`
- `news-back.png`
- `news-back-mobile.png`

## 12. Backend/View Data Changes

- Added `StorefrontOrderQuery::dashboardCounts()` for pending/completed/total owned-order counts.
- Updated `AccountController` to pass those counts to the dashboard.
- Added raw breadcrumb/mobile navigation markup to the account layout.
- Updated dashboard Blade composition and presentation-only 404 markup.

No new domain model, pricing, inventory, order, or authorization rule was introduced.

## 13. Desktop Visual QA

Dedicated Playwright smoke at `1536×1024` passed for both `/does-not-exist` and an isolated authenticated `/account` session. Checks passed for RTL document attributes, shared header/footer, four account summary cards, lower account-columns row, and no horizontal overflow. The expected 404 document response was the only 404 network status.

## 14. Mobile Visual QA

Dedicated Playwright smoke at `390×844` passed for both pages. RTL, header/footer, four summary cards, lower account-columns row, and no horizontal overflow were verified. The account mobile layout shows the responsive mobile account navigation and stacked summary/lower cards.

## 15. 404 Browser QA

VERIFIED. Desktop and mobile screenshots were captured from the isolated browser run. The raw hero background, recovery card, suggestions, trust row, newsletter, responsive stacking, shared chrome, and Laravel search action rendered without missing assets or JavaScript errors.

## 16. Account Browser QA

VERIFIED. Desktop and mobile screenshots were captured from isolated registered sessions. The dashboard rendered the four semantic summary cards and lower two-column content with no console, page, request, or asset failures.

The separate pre-existing `storefront-polish.spec.js` run was not used as this parity gate: two assertions failed against the intentionally empty isolated catalog (archive pagination link absent and scroll-hide behavior not triggered), while its auth-page checks passed. No parity implementation failure was indicated by those fixture-dependent assertions.

## 17. Focused Tests

Command:

`php artisan test --compact tests/Feature/Storefront/AccountTest.php tests/Feature/Storefront/OrdersTest.php tests/Feature/Storefront/StaticPagesTest.php`

Result: **12 passed, 91 assertions, 0 failures, 0 skipped**.

The Orders test now includes a regression for real pending/completed dashboard counts and the Static Pages test asserts the raw heading `صفحه مورد نظر پیدا نشد`.

## 18. Full Suite

Command: `php artisan test --compact`

Result: **451 passed, 2,882 assertions, 0 failures, 0 skipped** (89.13 seconds).

## 19. Database Safety

`php artisan migrate --no-interaction`: **Nothing to migrate.**

`php artisan migrate:status`: all migrations reported **Ran**. No destructive command was used. Browser smoke data used a temporary isolated SQLite database and was removed afterward; the development `ecommerce` database was not reset or modified by test setup.

## 20. Raw Frontend Preservation

`D:\uni-shop-project\front` remained unchanged. Only copied public assets and Laravel application/views/tests were changed.

## Quality Checks

- `vendor/bin/pint --dirty`: **passed**
- `git diff --check`: **passed**

## Bugs Found / Fixed

**PARITY-BUG-001 — Medium**

- Scenario: 404 asset loading.
- Input/state: Rendered Laravel 404 page.
- Expected: All raw 404 stylesheets load successfully.
- Actual: `components/public-page.css` and `product/reviews.css` returned 404.
- Exception: Browser asset 404 responses; no application exception.
- Root cause: Raw files had not been migrated into `public/storefront/assets/css`.
- Fix: Copied the exact raw files; subsequent browser smoke had no asset failures.

## Final Status

`404 + ACCOUNT RAW TEMPLATE PARITY: VERIFIED PASS`
