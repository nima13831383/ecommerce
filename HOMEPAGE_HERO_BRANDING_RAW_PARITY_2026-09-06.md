# Homepage Hero, Branding, and Raw Parity QA — 2026-09-06

## 1. Scope

Focused homepage parity maintenance only. The raw source at `D:\uni-shop-project\front` was inspected and left unchanged. No Cart, Checkout, API, or domain redesign work was added.

## 2. Raw Template Sources

Homepage source: `D:\uni-shop-project\front\index.html`.

Hero styles/scripts: `css/homepage/hero.css`, `css/homepage/responsive.css`, and `js/homepage/hero-slider.js`.

Promo artwork source: `pictures/bootom-of-the-main-page/*.png`.

## 3. Hero Artwork Mapping

The three desktop/mobile pairs are preserved by slide index:

| Slide | Desktop | Mobile |
| --- | --- | --- |
| 1 | `mainpagehero/desktop/1.png` | `mainpagehero/mobile/1.png` |
| 2 | `mainpagehero/desktop/2.png` | `mainpagehero/mobile/2.png` |
| 3 | `mainpagehero/desktop/3.png` | `mainpagehero/mobile/3.png` |

SHA-256 checks confirmed the migrated files match the raw artwork.

## 4. Hero SSR Markup

`resources/views/storefront/home.blade.php` now renders each slide with a responsive `<picture>`, public Laravel asset URLs, meaningful alt text, and the existing Persian copy/controls.

## 5. Hero Interaction

Existing arrows, dots, keyboard direction, active-state ARIA attributes, and reduced-motion CSS remain intact. Touch handling was added with a 48px horizontal threshold and vertical-scroll protection; left swipes advance and right swipes go back in the RTL storefront.

## 6. Touch / Gesture Verification

Real Playwright touch-event verification at 390px advanced the active dot from slide 0 to slide 1. A vertical-dominant gesture did not trigger a slide change. No console/page errors were observed.

## 7. Autoplay / Motion Policy

The inspected current raw slider script did not define autoplay. No autoplay behavior was invented. Existing reduced-motion rules remain effective.

## 8. Section Spacing

Category and `پیشنهاد ویژه` sections retain the raw template section rhythm and responsive spacing. No unrelated spacing refactor was introduced.

## 9. Lower Promo Banners

The two real supplied 1916×821 images are rendered as responsive banner backgrounds with the existing copy and buttons layered above them:

* `assets/homepage/promos/accessories.png` ← supplied lavender bag/accessory artwork.
* `assets/homepage/promos/skincare.png` ← supplied skincare artwork.

## 10. Branding Setting

Added the registry-backed core setting `branding.logo_path` (nullable string, safe fallback, additive migration). Existing values are preserved by `insertOrIgnore` migration semantics.

## 11. Filament Branding UI

The existing Settings edit page exposes an image-only `FileUpload` on the branding key, storing under the public disk `branding/` directory. The real Livewire edit path persisted and reloaded an uploaded logo in the focused regression.

## 12. Storefront Branding Resolution

`StorefrontBranding` reads through `SettingsService`, verifies a relative public-disk path exists, and otherwise returns `asset('storefront/luxira-icon.png')`. Traversal/absolute/missing paths fail closed to the fallback.

## 13. Header Integration

Desktop and mobile shared header logos now use the resolved branding URL. Logo sizing uses `object-fit: contain` to preserve the configured mark without cropping.

## 14. Product Query / Cache Preservation

`HomeController` remains unchanged in its authoritative featured flow: `ProductCatalogQuery` with `featured=true`, `sort=newest`, and bounded `per_page=7`. No cache policy or Product pricing logic was duplicated or changed.

## 15. Sensitive Data / Safety

The branding setting is public presentation data only. No inventory, pricing, customer, or admin data is exposed. The raw source directory was not edited.

## 16. Focused Runtime Tests

* `tests/Feature/Storefront/HomePageTest.php` — 4 passed, 34 assertions (including artwork, RTL, route, and fallback markup checks).
* `tests/Feature/Filament/Settings/BrandingSettingRuntimeTest.php` — 2 passed, 11 assertions.
* Settings/payment and related focused regression set — 49 passed, 452 assertions, 0 failures.

## 17. Full Isolated Suite

`php artisan test --compact`: **454 passed, 2,911 assertions, 0 failures, 0 skipped**.

## 18. Browser QA — Required Viewports

Playwright smoke passed at 1440×900, 1536×1024, 1920×1080, 320×800, 390×844, and 430×932. Each returned HTTP 200, rendered RTL markup, loaded all three responsive Hero images and both promo images, had no horizontal overflow, and produced no console/page errors. The 390px touch swipe also passed.

## 19. Browser Visual Review

Desktop and mobile screenshots were reviewed. The real perfume Hero artwork, responsive mobile crop, real lower promo artwork, RTL text overlay, header logo, category/product rhythm, and footer remain structurally aligned with the raw visual language.

## 20. Migration

`php artisan migrate --no-interaction`: completed successfully; only the additive branding core-setting migration ran.

`php artisan migrate:status`: all migrations, including `2026_09_06_214538_add_branding_logo_core_setting`, are `Ran`.

## 21. Development Database Safety

No destructive command was run. A non-destructive backup was retained at `D:\uni-shop-project\db\backups\ecommerce_2026-09-06_214524.sql` before the additive setting migration. Development Products and other data were not reset or modified.

## 22. Pint

`vendor/bin/pint --dirty`: passed.

## 23. git diff --check

`git diff --check`: passed.

## 24. Files Added/Changed for This Phase

* `app/Services/Storefront/StorefrontBranding.php`
* `app/Settings/SettingRegistry.php`
* `database/migrations/2026_09_06_214538_add_branding_logo_core_setting.php`
* `app/Filament/Resources/Settings/Schemas/SettingForm.php`
* `app/Filament/Resources/Settings/SettingResource.php`
* `app/Providers/AppServiceProvider.php`
* `resources/views/storefront/home.blade.php`
* `resources/views/storefront/partials/header.blade.php`
* `public/storefront/assets/css/homepage/hero.css`
* `public/storefront/assets/css/homepage/banners.css`
* `public/storefront/assets/css/homepage/header.css`
* `public/storefront/assets/css/homepage/responsive.css`
* `public/storefront/assets/js/homepage/hero-slider.js`
* `public/storefront/assets/homepage/hero/**`
* `public/storefront/assets/homepage/promos/**`
* `tests/Feature/Storefront/HomePageTest.php`
* `tests/Feature/Filament/Settings/BrandingSettingRuntimeTest.php`

Pre-existing uncommitted 404/account parity files remain untouched by this phase.

## 25. Raw Frontend Source

`D:\uni-shop-project\front` unchanged.

## 26. Final Status

`HOMEPAGE HERO + BRANDING RAW PARITY: VERIFIED PASS`
