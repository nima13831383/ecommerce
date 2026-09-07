# Blade SSR Storefront Phase 1 — Template Inventory and Foundation

## 1. Frontend Source

**Path:** `D:\uni-shop-project\front`

**Technology:** static HTML, Tailwind CSS output, custom CSS, jQuery, and page-scoped JavaScript. The source is a visual/design template, not a separate application.

**Build setup:** `package.json` provides `build:css`, `watch:css`, and visual QA scripts. `tailwind.config.js` and `scripts/build-css.mjs` produce `assets/css/generated/tailwind.css`. Dependencies include Tailwind 3.4, jQuery 4, and Playwright for visual QA. No Vite or Laravel integration exists in the raw source.

## 2. Page Inventory

| Raw File | Page Type | Shared Layout | JS Dependencies | Conversion Status |
| --- | --- | --- | --- | --- |
| `index.html` | Home | header, footer, icon sprite | homepage scripts, core main | Converted to static Blade SSR proof |
| `category.html` | Product listing/category | header, footer | category scripts | Inventory only; future phase |
| `search.html` | Search results | header, footer | search script | Inventory only; future phase |
| `product.html` | Product detail | header, footer | product scripts | Inventory only; future phase |
| `cart.html` | Cart | header, footer | cart scripts | Inventory only; future phase |
| `checkout.html` | Checkout | header, footer | checkout script | Inventory only; future phase |
| `login.html` | Login | auth layout | auth scripts | Breeze mapping documented; not converted |
| `register.html` | Registration | auth layout | auth scripts | Breeze mapping documented; not converted |
| `forgot-password.html` | Password reset | auth layout | auth scripts | Breeze mapping documented; not converted |
| `account.html` | Account dashboard | account shell | account scripts | Inventory only; future phase |
| `addresses.html` | Address book | account shell | addresses script | Inventory only; future phase |
| `profile.html` | Profile | account shell | profile script | Inventory only; future phase |
| `orders.html` | Orders list | account shell | orders script | Inventory only; future phase |
| `order-detail.html` | Order detail | account shell | account/order scripts | Inventory only; future phase |
| `wishlist.html` | Wishlist | account shell | wishlist script | Inventory only; future phase |
| `payment-result.html` | Payment result | payment-result layout | payment-result script | Inventory only; future phase |
| `blog.html` | Blog listing | blog layout | blog filter script | Inventory only; future phase |
| `article.html` | Blog article | blog layout | article script | Inventory only; future phase |
| `about.html` | About/static content | static page layout | none/page script as applicable | Inventory only; future phase |
| `contact.html` | Contact/static content | static page layout | contact script | Inventory only; future phase |
| `faq.html` | FAQ/static content | static page layout | FAQ script | Inventory only; future phase |
| `404.html` | Not found | not-found layout | not-found script | Inventory only; future phase |

The raw source also contains responsive visual QA screenshots, `FRONTEND-SPEC.md`, `debug.log`, an Iran Yekan archive, and build/QA scripts. These were not copied into the Laravel public asset tree.

## 3. Shared Component Inventory

Repeated structures identified for Blade conversion are: the announcement/header shell, desktop navigation, mobile navigation/drawer, search boxes, cart preview, footer/social links, icon sprite, section headings, product cards, price/discount display, category links, feature strip, banners, brand strip, newsletter form, sliders, breadcrumbs, account sidebar, product gallery, attribute selectors, alerts, and pagination. Phase 1 extracts the header, footer, icon sprite, and base layout; commerce-aware components remain for later phases.

## 4. Asset Inventory

**CSS:** 60 source files under `assets/css`, including generated Tailwind, base reset/fonts/typography/utilities, shared components, homepage, category, product, cart, checkout, auth, account, blog, search, payment-result, and static-page styles.

**JavaScript:** 31 source files under `assets/js`, including core, homepage, product, cart, checkout, auth, account, blog, category, search, payment-result, and static-page modules.

**Fonts:** eight IRANYekan TTF weights from Thin through Extra Black.

**Icons:** inline SVG symbol sprite in the home template plus the `luxira-icon.png` brand image.

**Images:** homepage primarily uses CSS placeholders; the source root also contains login backgrounds, 404 backgrounds, newsletter backgrounds, brand artwork, and visual-QA screenshots. Only the homepage logo and assets required by its CSS/JS were migrated in this phase.

## 5. Third-Party Dependencies

The raw template uses jQuery (`assets/vendor/jquery/jquery.min.js`), Tailwind CSS 3.4 output, and Playwright only for visual QA scripts. No additional browser framework or UI plugin was introduced.

## 6. Tailwind/CSS Strategy

The source is mixed: compiled Tailwind plus ordered custom CSS layers. The working generated stylesheet and the homepage/base/component CSS are reused without a visual rewrite. They are served as static Laravel `public/storefront` assets; the Laravel Vite pipeline was not forced onto the existing template in this foundation phase.

## 7. JavaScript/jQuery Strategy

The existing deferred jQuery and homepage scripts are preserved in their source order: jQuery, mobile menu, cart dropdown, sticky header, hero slider, product slider, newsletter, then core initialization. No jQuery-to-Alpine/Vue/React rewrite was made. Dynamic business behavior is intentionally not connected yet.

## 8. Blade Structure Created

- `resources/views/storefront/layouts/app.blade.php`
- `resources/views/storefront/partials/header.blade.php`
- `resources/views/storefront/partials/footer.blade.php`
- `resources/views/storefront/partials/icon-sprite.blade.php`
- `resources/views/storefront/home.blade.php`

## 9. Home Page Conversion

**Raw source:** `D:\uni-shop-project\front\index.html`

**Blade destination:** `resources/views/storefront/home.blade.php`, extending `storefront.layouts.app`.

**Route:** `GET /`, named `storefront.home`.

**Controller:** `App\Http\Controllers\Storefront\HomeController` (invokable, static view data only).

The converted page preserves the raw home section order: hero, categories, special products, feature strip, banners, brands, newsletter, footer, and SVG icons. Product cards and cart preview remain explicitly static template content; no database or commerce service is queried.

## 10. Asset Migration

Copied to `public/storefront`: the generated Tailwind CSS, base fonts/reset/typography/utilities CSS, shared component CSS, all homepage CSS, homepage/core JavaScript, jQuery, eight IRANYekan fonts, and `luxira-icon.png`. Relative font URLs continue to resolve within the copied public asset tree. No `node_modules`, caches, QA screenshots, archive, or temporary files were copied.

## 11. Visual/Structural Notes

The Blade page uses Laravel `asset()`/`url()` helpers for migrated paths and navigation while retaining the raw classes, RTL structure, section order, responsive CSS, and deferred script order. The SVG sprite is a shared partial. The two raw banner inline styles were expressed as the equivalent `promo-banner__media` CSS class. No claim of pixel-perfect browser parity is made; this phase used structural HTTP/Blade tests, not browser screenshot comparison.

## 12. Dynamic Integration Points Identified

Future integration points include ProductCatalogQuery-backed product sections/cards, catalog/search links, category and brand navigation, cart badge/preview, auth/account menu, wishlist state, newsletter submission, shipping/promotions messaging, and blog links. None are connected in Phase 1.

## 13. Existing Backend Boundaries Preserved

`/api/v1` routes, Breeze authentication routes, Filament/admin routes, diagnostic route environment restrictions, and all commerce services remain intact. The home route now uses a dedicated web controller and does not repurpose API controllers.

## 14. Tests

`tests/Feature/Storefront/HomePageTest.php` — **3 tests, 19 assertions, 0 failures, 0 skipped**.

Coverage verifies guest `GET /`, Blade layout markers, RTL attributes, shared header/footer, critical asset references, non-JSON rendering, migrated local asset existence, and API/Breeze/Filament route registration.

## 15. Full Isolated Suite

**265 tests, 1,500 assertions, 0 failures, 0 skipped.** Pest emitted only its known non-fatal result-cache warning (`vendor/pestphp/pest/.temp/test-results` permission denied).

## 16. Migration

`php artisan migrate` reported **Nothing to migrate**. `php artisan migrate:status` reported the latest migrations **Ran**. No schema changes were added.

## 17. Pint

`vendor/bin/pint --dirty` completed successfully.

## 18. git diff --check

`git diff --check` completed cleanly.

## 19. Files Changed

Production/runtime:

- `app/Http/Controllers/Storefront/HomeController.php`
- `routes/web.php`
- `resources/views/storefront/layouts/app.blade.php`
- `resources/views/storefront/partials/header.blade.php`
- `resources/views/storefront/partials/footer.blade.php`
- `resources/views/storefront/partials/icon-sprite.blade.php`
- `resources/views/storefront/home.blade.php`
- `public/storefront/assets/...` (migrated static template assets)

Tests/report:

- `tests/Feature/Storefront/HomePageTest.php`
- `STOREFRONT_BLADE_PHASE1_2026-09-03.md`

## 20. Frontend Source Changes

`None`. The raw source at `D:\uni-shop-project\front` remains unchanged.

## 21. Next Blade Integration Phase

Next, connect the existing Product listing/category template (`category.html`) through a thin Blade controller and shared `ProductCatalogQuery`/pricing services, then convert `product.html` for detail and backend-authoritative Variable Product selection. Keep the extracted layout/components as the shared shell and add Feature coverage before proceeding to Cart.
