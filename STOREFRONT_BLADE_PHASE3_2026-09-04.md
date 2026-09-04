# Blade Storefront Phase 3 — Product Detail

## 1. Raw Product Template

Source: \`D:\uni-shop-project\front\product.html\` (unchanged).

CSS: the raw product layout, gallery, product-info, details, and responsive styles were copied to \`public/storefront/assets/css/product/\`.

JS: the raw gallery/details scripts were copied to \`public/storefront/assets/js/product/\`; a small page-specific \`detail-selection.js\` was added for authoritative variation lookup and quantity UX. The raw purchase script was not loaded because Cart is out of scope.

## 2. Product Detail Route

\`GET /products/{product:slug}\` now resolves through \`Storefront\ProductController@show\`. The old placeholder controller was removed. Only published, non-deleted Products are rendered; inactive and soft-deleted records return 404.

## 3. Controller / Query / Presenter

\`ProductCatalogQuery::findPublicBySlug()\` supplies the public detail graph with taxonomy, ordered images, attributes/options, and active variations. \`ProductDetailResource\` remains the shared public mapping for API and Blade presentation; nested gallery and variation resources are resolved to plain arrays before being passed to the view.

## 4. Simple Product Rendering

Pricing uses \`ProductPriceResolver\` through \`ProductDetailResource\`; effective, regular, sale, and discount state are rendered as integer Rial values. Availability uses the existing \`InventoryService\` semantics. Ordered primary/gallery images use generated storage URLs, with the existing placeholder when no image exists.

## 5. Gallery

The first ordered image is the main image and all ordered images render as accessible thumbnails with alt text. The copied gallery behavior plus the page script swap the main image without a server call. No-image Products render the existing media placeholder.

## 6. Variable Product Axes

Actual Product attributes/options are rendered with stable \`attribute_id\` and \`value_id\` data attributes. Options from unrelated attributes are not rendered. The initial state displays the resolver-backed price range and a neutral selection prompt.

## 7. Variation Resolution

Endpoint: \`POST /api/v1/products/{product}/resolve-variation\`.

Input: \`{ "options": [{ "attribute_id": <int>, "value_id": <int> }, ...] }\`.

Response fields used: authoritative variation ID, effective/regular pricing and discount state, availability, and optional variation image URL. The browser does not recreate canonical signatures or calculate prices.

## 8. Client-Side Selection Behavior

Incomplete: clears selected variation and shows a neutral prompt/range.

Resolved: stores the authoritative variation ID, updates price/discount/stock, and applies an optional variation image.

Unavailable: clears the selected ID and presents a safe unavailable state.

Failure: presents a retryable Persian message without exposing raw server errors.

Stale-request handling: prior jQuery requests are aborted and a monotonically increasing request ID prevents stale responses from overwriting current selection state.

## 9. Selected Variation State

The page includes \`data-selected-variation\` and a hidden \`variation_id\` input for the future Cart phase. No Cart mutation is performed.

## 10. Quantity UI

The raw quantity control appearance is preserved. The page script maintains a minimum UX quantity of 1 and a hidden quantity value; server-side quantity validation remains deferred to Cart.

## 11. Description / Tabs

The real Product description (falling back to short description) is rendered in the existing details accordion shell. Review/specification/usage content that has no authoritative backend data was not fabricated and remains deferred.

## 12. Breadcrumb

The page renders Home, the first actual Product category when present, and the Product name using existing routes.

## 13. Related Products

Deferred. No recommendation/related-product domain was invented, and no fake static Product identities are rendered.

## 14. Sensitive Data Exclusions

The Blade page receives the public detail resource only. Inventory transactions, reservations, internal costs, deleted state, and admin metadata are not rendered.

## 15. Product Detail Tests

\`tests/Feature/Storefront/ProductDetailTest.php\`: **5 passed, 34 assertions, 0 failures** (independent run 1).

Independent run 2: **5 passed, 34 assertions, 0 failures**.

Coverage includes public simple/variable rendering, sale price, availability, ordered gallery, placeholder, taxonomy, visibility 404s, option ownership, selection hooks, and Cart non-integration.

## 16. Storefront Test Result

\`php artisan test tests/Feature/Storefront\`: **11 passed, 85 assertions, 0 failures**.

## 17. Product API Regression

\`php artisan test tests/Feature/Api/V1/Products\`: **6 passed, 54 assertions, 0 failures**. Existing detail and variation-resolution contracts remain green.

## 18. Browser Interaction

**NOT VERIFIED** in this phase. PHP tests verify SSR markup/data hooks; the existing API tests verify authoritative resolution. No browser runner was introduced.

## 19. Full Isolated Suite

\`php artisan test\`: **273 passed, 1,566 assertions, 0 failures, 0 skipped**.

## 20. Migration

\`php artisan migrate\`: **Nothing to migrate**.

\`php artisan migrate:status\`: all listed migrations **Ran**.

## 21. Pint

\`vendor/bin/pint --dirty\`: **passed**.

## 22. git diff --check

**Passed** with no whitespace errors.

## 23. Production Files Changed

- \`app/Http/Controllers/Storefront/ProductController.php\`
- \`app/Http/Resources/Api/V1/ProductDetailResource.php\`
- \`app/Services/Catalog/ProductCatalogQuery.php\`
- \`routes/web.php\`
- \`resources/views/storefront/layouts/app.blade.php\`
- \`resources/views/storefront/products/show.blade.php\`
- \`public/storefront/assets/js/product/detail-selection.js\`
- Product detail CSS and gallery/details JS assets under \`public/storefront/assets/css/product/\` and \`public/storefront/assets/js/product/\`
- \`tests/Feature/Storefront/ProductDetailTest.php\`

Removed: \`app/Http/Controllers/Storefront/ProductDetailPlaceholderController.php\`.

## 24. Raw Frontend Source

\`D:\uni-shop-project\front\` is unchanged.

## 25. Remaining Blade Work

- Auth/account visual integration
- Cart
- Address/geography
- Coupon/shipping
- Checkout
- Payment
- Orders
- Blog

Cart, checkout, and all other out-of-scope commerce mutations were not implemented.
