# Second Main Header Row Sticky Fix — 2026-09-06

## 1. Scope

Only the shared storefront header behavior changed. The raw design source at `D:\uni-shop-project\front` was not modified. No commerce, API, authentication, Cart, Hero, newsletter, or page-content behavior was changed.

## 2. Header DOM Structure

The shared Blade header now has three independent document-flow regions:

| Region | Desktop | Mobile | Sticky |
| --- | --- | --- | --- |
| Announcement | `[data-storefront-announcement]` | `[data-storefront-announcement]` | No |
| Main/second row | `header[data-storefront-main-header] > .main-header.storefront-main-row` | `header[data-storefront-main-header] > .mobile-header__row.storefront-main-row` | Yes |
| Navigation/search below main row | `[data-storefront-navigation]` | `[data-storefront-mobile-search]` | No |

The main row retains the existing dynamic `branding.logo_path` fallback. Desktop continues to contain logo, search, account/wishlist, and Cart controls. Mobile continues to contain menu, logo, and Cart controls.

## 3. Previous Behavior and Root Cause

The previous outer `<header>` owned the announcement, main row, desktop navigation, and mobile search. Sticky CSS and the loaded `sticky-header.js` targeted that outer wrapper, so the requested one-row sticky boundary did not exist.

Before the correction at 1536 px, live browser boxes were: announcement `0–31`, main row `31–111`, navigation `111–157`; after scroll, the outer header behavior did not retain the requested isolated main row. The structure also made it impossible for announcement/navigation to independently remain in normal document flow.

## 4. Implemented Sticky Architecture

`header.storefront-main-header[data-storefront-main-header]` now contains only the main row and Cart preview. It is the sole sticky element:

```css
.storefront-main-header {
  position: sticky;
  top: 0;
  z-index: 100;
}
```

The announcement, desktop navigation, and mobile search are siblings outside that sticky header. The old scroll-driven `sticky-header.js` is no longer loaded by the storefront layout. No replacement scroll JavaScript was introduced.

## 5. Browser Geometry Evidence

### Desktop (1536 × 1024)

| Scroll Y | Announcement | Main row | Desktop navigation |
| ---: | --- | --- | --- |
| 0 | `0–31` | `31–111` (80 px) | `111–157` |
| 3 | `-3–28` | `28–108` (80 px) | `108–154` |
| 6 | `-6–25` | `25–105` (80 px) | `105–151` |
| 20 | `-20–11` | `11–91` (80 px) | `91–137` |
| 50 | `-50–-19` | `0–80` (80 px) | `61–107` |
| 100 | `-100–-69` | `0–80` (80 px) | `11–57` |
| 220 | `-220–-189` | `0–80` (80 px) | `-109–-63` |

The same physical-box assertions passed at 1440, 1536, and 1920 px.

### Mobile (390 × 844 and 430 × 844)

| Scroll Y | Announcement | Main row | Mobile search |
| ---: | --- | --- | --- |
| 0 | `0–35` | `35–104` (69 px) | `104–158` |
| 3 | `-3–32` | `32–101` (69 px) | `101–155` |
| 6 | `-6–29` | `29–98` (69 px) | `98–152` |
| 10 | `-10–25` | `25–94` (69 px) | `94–148` |
| 20 | `-20–15` | `15–84` (69 px) | `84–138` |
| 50 | `-50–-15` | `0–69` (69 px) | `54–108` |
| 100 | `-100–-65` | `0–69` (69 px) | `4–58` |
| 220 | `-220–-185` | `0–69` (69 px) | `-116–-62` |

The desktop navigation is hidden at mobile widths. The mobile samples at 390 and 430 px show monotonic movement into the sticky position and invariant row height; no jitter or content-height jump was observed.

## 6. Shared-Page Regression

The physical shared header check passed on Home, Product archive, Blog, Account (authenticated-boundary redirect included), and the 404 page. Existing Cart preview placement remains inside the main sticky header and its existing dropdown script remains intact.

## 7. Screenshots

Captured browser screenshots are retained outside the repository:

* `C:\Users\nima\.codex\visualizations\2026\09\03\01a06603-a821-7bb2-8ed0-0680f98bafbe\second-main-header-sticky\desktop-top.png`
* `C:\Users\nima\.codex\visualizations\2026\09\03\01a06603-a821-7bb2-8ed0-0680f98bafbe\second-main-header-sticky\desktop-scrolled.png`
* `C:\Users\nima\.codex\visualizations\2026\09\03\01a06603-a821-7bb2-8ed0-0680f98bafbe\second-main-header-sticky\mobile-top.png`
* `C:\Users\nima\.codex\visualizations\2026\09\03\01a06603-a821-7bb2-8ed0-0680f98bafbe\second-main-header-sticky\mobile-scrolled.png`

## 8. Automated Runtime Coverage

`tests/Browser/storefront-polish.spec.js` now verifies:

* only the desktop main row sticks at 1440, 1536, and 1920 px;
* announcement and navigation scroll out of view normally;
* no desktop main-row height change at the sticky transition;
* mobile 390/430 main-row geometry at Y `0, 3, 6, 10, 20, 50, 100`;
* mobile search scrolls normally and desktop navigation is hidden on mobile;
* the shared main header is present on Home, Product archive, Blog, Account, and 404.

Focused browser result: **20 passed, 28 intentionally project-scoped skips, 0 failures**. The skip count is expected because the file carries desktop/mobile project-specific checks across the six configured browser projects.

The updated storefront auth markup regression passed: **3 tests, 37 assertions, 0 failures**. The full isolated Laravel suite passed: **456 tests, 2,925 assertions, 0 failures**. Pest emitted its existing non-fatal result-cache permission warning after completion.

## 9. Files Changed for This Fix

* `resources/views/storefront/partials/header.blade.php`
* `resources/views/storefront/layouts/app.blade.php`
* `public/storefront/assets/css/homepage/header.css`
* `public/storefront/assets/css/homepage/responsive.css`
* `tests/Browser/storefront-polish.spec.js`
* `tests/Feature/Storefront/AuthPagesTest.php`

## 10. Safety

* Development database was not reset or destructively modified.
* The raw frontend source directory remained unchanged.
* No domain logic, checkout, payment, Cart calculation, or API contract was changed.

SECOND MAIN HEADER ROW STICKY: VERIFIED PASS
