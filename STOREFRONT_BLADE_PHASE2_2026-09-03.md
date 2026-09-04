# STOREFRONT BLADE PHASE 2 — DYNAMIC HOME + PRODUCT LISTING

## 1. Raw Template Sources Used

- Home: `D:\uni-shop-project\front\index.html`
- Listing/category: `D:\uni-shop-project\front\category.html`
- Product card: repeated `.product-card` markup in `index.html` and `category.html`
- Pagination/filter components: category filter sidebar, toolbar, mobile drawer, and pagination markup in `category.html`; behavior in `assets/js/category/filter-drawer.js` and `sort-dropdown.js`.

## 2. Shared Product Card

Blade file: `resources/views/storefront/components/product-card.blade.php`.

The component receives the public summary array produced by the existing `ProductSummaryResource`. It renders the name, future detail URL, primary image with the existing placeholder fallback, resolver-backed effective/regular/range prices, discount state, and availability. Amounts remain integer Rial; `number_format` is presentation-only.

## 3. Home Dynamic Sections

| Section | Backend Mapping | Query | Limit | Result |
|---|---|---|---:|---|
| پیشنهاد ویژه / special products | Products with the existing `is_featured` flag | `ProductCatalogQuery` (`status=published`, `featured=true`, newest) | 7 | PASS — real featured products only; empty state when none |
| Hero, categories, features, banners, brands, newsletter | Existing static template content | None in this phase | N/A | Preserved; no commerce data connected |

No popularity, best-seller, or recommendation meaning was invented.

## 4. Product Listing Route

`GET /products` → `App\Http\Controllers\Storefront\ProductController@index` → `storefront.products.index`.

The route is separate from `/api/v1/products` and uses a web `FormRequest` with GET query parameters.

## 5. Product Listing Controller / Query

`ProductController` remains thin. `Storefront\ProductIndexRequest` validates the query string, `ProductCatalogQuery` performs public filtering/sorting and pagination, and `ProductSummaryResource` supplies the same public presentation contract used by the API. The controller adds only active category/brand filter options and query-string pagination.

Listing eager loading was narrowed to primary image, taxonomy, brand, tags, and only the active variation pricing/stock columns needed by resolver and availability logic; variation attribute graphs remain detail-only.

## 6. Filters

Implemented with GET parameters:

- `search`
- `category` (public slug)
- `brand` (public slug)
- `in_stock`
- `type` (`simple` or `variable`)
- `min_price`
- `max_price`

Invalid values redirect through normal web validation. Effective-price filtering continues to use the existing authoritative SQL expression in `ProductCatalogQuery`; no raw Blade price predicate was added.

## 7. Sorting

Implemented: `newest`, `price_asc`, `price_desc`, `name_asc`, and `name_desc`. The visible labels are Persian presentation text while query values remain stable machine values. Unsupported raw-template choices such as popularity/best-selling were not exposed.

## 8. Pagination

Laravel `LengthAwarePaginator` is used with the existing bounded `per_page` validation (default 24, maximum 48). A template-matching pagination partial renders previous/next and page links, and `withQueryString()` preserves filters/search/sort across pages.

## 9. Search

Listing search is server-side through `ProductCatalogQuery`. The raw header search remains visually unchanged in this phase; listing search is available at `/products?search=...` without autocomplete or live-search behavior.

## 10. Money Presentation

Backend values remain integer IRR/Rial. Cards render formatted integer values with `ریال`; no float, decimal-string, or Toman conversion is performed. The raw template’s static header cart preview remains untouched until the Cart phase.

## 11. Images

`ProductSummaryResource` now resolves nested `PublicImageResource` data to a plain array for Blade as well as API serialization. Cards use the configured public storage URL for the primary image and the existing `.media-placeholder` when absent. No external image URLs were introduced.

## 12. Empty State

Home shows a styled no-featured-products message when the featured query is empty. Listing returns HTTP 200 with the raw-template-compatible no-results state and retains the submitted query values.

## 13. Product Detail Link Strategy

Cards target the intended named route `/products/{product:slug}`. `ProductDetailPlaceholderController` provides an explicit non-final placeholder only; no Product detail query, pricing, variation, or commerce behavior was implemented in this phase.

## 14. N+1 / Query Loading

The listing query eager-loads only card requirements and active variation pricing/stock columns. Detail-only attribute/value graphs are not loaded for every card. `ProductSummaryResource` reuses `ProductPriceResolver` and `InventoryService`; no parallel pricing or stock arithmetic was introduced in Blade.

## 15. Production Files Changed

- `app/Http/Controllers/Storefront/HomeController.php`
- `app/Http/Controllers/Storefront/ProductController.php`
- `app/Http/Controllers/Storefront/ProductDetailPlaceholderController.php`
- `app/Http/Requests/Storefront/ProductIndexRequest.php`
- `app/Http/Resources/Api/V1/ProductSummaryResource.php`
- `app/Services/Catalog/ProductCatalogQuery.php`
- `routes/web.php`
- `resources/views/storefront/home.blade.php`
- `resources/views/storefront/products/index.blade.php`
- `resources/views/storefront/components/product-card.blade.php`
- `resources/views/storefront/components/pagination.blade.php`
- `resources/views/storefront/placeholder.blade.php`
- `public/storefront/assets/css/components/product-card.css`
- copied category CSS/JS under `public/storefront/assets/css/category` and `public/storefront/assets/js/category`

## 16. Home Runtime Tests

`tests/Feature/Storefront/HomePageTest.php` plus the dynamic Home test in `tests/Feature/Storefront/ProductListingTest.php` passed as part of the Storefront run: 4 Home-related tests, 25 assertions (the existing three tests account for 19 assertions and the dynamic test for six).

## 17. Listing Runtime Tests

`tests/Feature/Storefront/ProductListingTest.php`: 3 tests, 32 assertions, 0 failures, 0 skipped. The file passed independently twice.

Coverage includes public visibility, featured Home data, image URLs, effective sale pricing, variable price ranges, availability, search, category/brand/stock/type/price filters, supported sorting, pagination/query preservation, invalid sort redirect, and empty results.

## 18. Storefront Test Result

`php artisan test tests/Feature/Storefront`: **6 passed, 51 assertions, 0 failures, 0 skipped**.

## 19. Product API Regression

`php artisan test tests/Feature/Api/V1/Products`: **6 passed, 54 assertions, 0 failures, 0 skipped**.

The shared resource/query changes did not regress the Phase 1 Product Catalog API or variation resolver.

## 20. Full Isolated Suite

Captured to a temporary log to completion: **268 passed, 1,532 assertions, 0 failures, 0 skipped**.

## 21. Migration

`php artisan migrate`: **Nothing to migrate**.

`php artisan migrate:status`: all listed migrations **Ran**. No destructive migration command was used.

## 22. Pint

`vendor/bin/pint --dirty`: **passed**.

## 23. git diff --check

`git diff --check`: **passed with no output**.

## 24. Raw Frontend Source

`D:\uni-shop-project\front` was inspected but unchanged.

## 25. Remaining Blade Storefront Work

- Product Detail + variable selection
- Auth/account visual integration
- Cart
- Address/geography
- Coupon/shipping
- Checkout
- Payment
- Orders
- Blog

No frontend source edit, API round-trip, business-policy redesign, or out-of-scope commerce feature was added.

`BLADE STOREFRONT PHASE 2: VERIFIED PASS`
