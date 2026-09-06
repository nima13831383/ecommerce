# Storefront Hero, Header, Newsletter, and Mobile Filter Polish — 2026-09-06

## 1. Hero Text Position Before/After

Before, the shared hero copy placement allowed the three desktop slides to use the same flow position. After the focused change, slides 1 and 3 use a visual-right copy anchor and slide 2 uses a visual-left anchor through `hero-slide--1`, `hero-slide--2`, and `hero-slide--3`. Mobile breakpoints reset these absolute placements and retain centered responsive copy.

## 2. Trackpad Root Cause

The slider handled arrows, dots, keyboard, and touch, but had no horizontal `wheel` listener. Trackpad two-finger gestures therefore did not advance the slider.

## 3. Trackpad Implementation

`hero-slider.js` now accumulates horizontal wheel deltas, requires horizontal dominance over vertical movement, advances exactly one slide at an 80px threshold, and applies a 360ms cooldown plus a short accumulation reset. Vertical wheel events are not prevented and reset the horizontal accumulator. The same `goTo` state path is used by arrows, dots, keyboard, touch, and wheel input.

## 4. Touch Regression

Existing touch threshold and vertical-scroll protection were retained. The focused browser suite continues to pass without touch or vertical-scroll regressions.

## 5. Lower Promo Text

Promo copy is now explicitly anchored to the visual right on desktop (`min-width: 901px`). Existing tablet/mobile rules center the copy. Supplied promo artwork and image mapping were not changed.

## 6. Sticky Header Architecture

The shared header remains CSS `position: sticky` and the scroll script now only toggles `header.is-stuck` using a passive listener and `requestAnimationFrame`. Header geometry remains stable while scrolling; no content/nav collapse is driven from scroll.

## 7. Mobile Header Bounce Root Cause

The previous scroll script changed announcement, navigation, and mobile-search visibility while the browser was scrolling. Those height changes could create mobile bounce/jitter.

## 8. Mobile Header Fix

Scroll-time hide/show and collapse mutations were removed. The mobile header keeps stable layout while the lightweight stuck-state class remains available for styling.

## 9. Shared Newsletter

Home and the 404 page now include `storefront.partials.newsletter`, using the shared 404-derived `not-found-newsletter` markup and `newsletter-shared.css`. The newsletter remains presentation-only; no submission backend was invented.

## 10. Header Navigation Cleanup

The shared desktop and mobile navigation no longer render the redundant `دسته‌بندی‌ها` or `برندها` links. Existing category/brand routes and page sections remain available elsewhere.

## 11. Mobile AJAX Filter Footer

The mobile filter drawer footer now contains only `پاک کردن فیلترها` (`data-filter-reset`). The redundant `اعمال فیلترها` action and its close handler were removed. Existing AJAX change, pagination, history, and reset behavior remains intact.

## 12. AJAX Filter Regression

The browser mobile drawer test passed for opening/closing, body locking, accordion state, AJAX availability filtering, URL synchronization, pagination query preservation, backdrop close, and explicit close.

## 13. Clear Filters Regression

The reset control remains present and is covered by the static/feature assertions. Reset continues to submit through the existing progressive filter contract.

## 14. Dynamic Logo Regression

The previously verified registry-backed `branding.logo_path` setting and storefront fallback remain unchanged. The full isolated suite and focused branding regression remain green.

## 15. Homepage Cache Regression

No cache/query/domain behavior was changed. The existing bounded `ProductCatalogQuery` homepage flow and Storefront cache architecture remain unchanged.

## 16. Desktop Browser QA

The Playwright desktop project passed the shared-header/archive/auth/static-page test and the wheel test. A direct viewport audit passed at 1440×900, 1536×1024, and 1920×1080: HTTP 200, RTL document, no horizontal overflow, stable header height, right-anchored promo copy, all hero images loaded, and no console/page/request failures.

## 17. Tablet Browser QA

The tablet project’s desktop-only/mobile-only tests were intentionally skipped by project guard. Direct viewport audit at 768×1024 passed with centered promo copy, RTL, no overflow, stable header, loaded assets, and no browser errors.

## 18. Mobile Browser QA

Mobile 390px and 430px projects passed the mobile drawer and auth/header checks. Direct viewport audits at 390×844 and 430×932 passed with centered promo copy, RTL, no overflow, stable header, loaded assets, and no browser errors. Category/brand links are absent from the opened mobile navigation.

## 19. Trackpad Browser QA

Playwright wheel regression passed: three horizontal deltas advance exactly one slide, a vertical-dominant wheel event does not change the slide, and a reverse gesture returns one slide after cooldown.

## 20. Header Scroll Stability QA

The desktop browser regression and direct viewport audit compared header geometry across scroll positions 0/3/6/12/30/60; the header height remained unchanged and the header stayed visible.

## 21. Console/Asset QA

All six direct viewport checks returned HTTP 200 with no console errors, page errors, failed requests, or missing hero image loads. No horizontal overflow was detected. Both promo images and all responsive hero images reported non-zero natural dimensions.

## 22. Focused Tests

* `HomePageTest.php`, `StaticPagesTest.php`, `ProductListingTest.php`, and `ProductDetailTest.php`: **16 passed, 128 assertions, 0 failures**.
* Extended homepage/static/listing/orders/branding regression: **20 passed, 166 assertions, 0 failures**.
* Browser polish projects: **8 passed, 8 intentional skips, 0 failures** across desktop, tablet, and mobile projects.

## 23. Full Suite

`php artisan test --compact`: **456 passed, 2,925 assertions, 0 failures, 0 skipped** (88.75 seconds). Pest emitted its existing non-fatal result-cache permission warning; the command exited successfully.

## 24. Database Safety

`php artisan migrate --no-interaction`: **Nothing to migrate**. `php artisan migrate:status`: all migrations are `Ran`. No destructive command was used and the development `ecommerce` database was not reset or modified.

## 25. Raw Frontend Preservation

`D:\uni-shop-project\front` was inspected and remains unchanged. Only Laravel views, public migrated assets/scripts/styles, tests, and the focused QA report were changed.

## Files Changed for This Polish Phase

* `resources/views/storefront/home.blade.php`
* `resources/views/storefront/partials/header.blade.php`
* `resources/views/storefront/partials/newsletter.blade.php`
* `resources/views/errors/404.blade.php` (shared newsletter inclusion)
* `resources/views/storefront/layouts/app.blade.php`
* `resources/views/storefront/products/index.blade.php`
* `public/storefront/assets/css/homepage/hero.css`
* `public/storefront/assets/css/homepage/banners.css`
* `public/storefront/assets/css/homepage/responsive.css`
* `public/storefront/assets/css/components/newsletter-shared.css`
* `public/storefront/assets/js/homepage/hero-slider.js`
* `public/storefront/assets/js/homepage/sticky-header.js`
* `public/storefront/assets/js/category/filter-drawer.js`
* `tests/Browser/storefront-polish.spec.js`
* `tests/Feature/Storefront/HomePageTest.php`
* `tests/Feature/Storefront/StaticPagesTest.php`

## Final Status

`HERO + HEADER + NEWSLETTER + MOBILE FILTER POLISH: VERIFIED PASS`
