# 1. API Foundation

Route registration is now provided by `bootstrap/app.php` through `routes/api.php`. The version prefix is `/api/v1` and the API uses Laravel API Resources with `{ "data": ... }` for resources and `{ "data": [...], "meta": {...} }` for collections. Validation and domain failures use `{ "message": ..., "errors": ..., "code": ... }`; API 404 responses use `not_found`, and unexpected API failures are rendered as the generic `server_error` shape without internal exception text. No authentication is required for this read-only catalog phase.

# 2. Product Listing Endpoint

* Method: `GET`
* URI: `/api/v1/products`
* Controller: `App\Http\Controllers\Api\V1\ProductController@index`
* Request: `ProductIndexRequest`
* Resource: `ProductSummaryResource`
* Query layer: `ProductCatalogQuery`
* Pagination: bounded `per_page` (default 24, maximum 48) and Laravel page metadata.

Only published, non-deleted products are queried. Primary image, taxonomies, and active variations are eager loaded.

# 3. Filters / Sorting

Implemented filters: `search`, `category` slug, `brand` slug, `in_stock`, `type`, `min_price`, and `max_price`. Sorting supports `newest`, `price_asc`, `price_desc`, `name_asc`, and `name_desc`. Effective price filters and price ordering use sale-window-aware SQL expressions matching the current price resolver semantics; availability accounts for active reservations. Popularity sorting is intentionally not exposed because no authoritative storefront metric was required.

# 4. Pagination Contract

The collection response exposes `current_page`, `per_page`, `last_page`, and `total`. Invalid or unbounded page sizes are rejected with a 422 `validation_error` response.

# 5. Product Detail Endpoint

`GET /api/v1/products/{product:slug}` returns the richer `ProductDetailResource`. It includes public product text, gallery URLs, category/brand/tag summaries, authoritative pricing, availability, product attributes/options, and active variable-product variations.

# 6. Product Resource Serialization

* Money: integer Rial values only, with `currency: IRR`.
* Enums/type: stable strings such as `simple`, `variable`, and `published`-derived visibility.
* Images: storage-generated public URLs plus alt/order metadata.
* Sensitive exclusions: no `deleted_at`, inventory transactions/reservations, internal cost/admin fields, or pivot internals.

# 7. Variable Product Contract

Input shape:

```json
{
  "options": [
    {"attribute_id": 1, "value_id": 10},
    {"attribute_id": 2, "value_id": 20}
  ]
}
```

Endpoint: `POST /api/v1/products/{product}/resolve-variation`.

Resolution uses the existing `ProductVariantService::combinationSignature()` implementation. The controller verifies the product axes, option ownership, duplicate axes, exact active variation, and public product visibility. The response is a `ProductVariationResource` with selected options, integer-Rial pricing, availability, and optional image URL. The frontend never needs to recreate canonical signature logic.

# 8. Availability Contract

Public availability is represented as `{ "in_stock": true|false }`. It is derived through `InventoryService`; active reservations reduce availability. Inventory quantities, ledgers, and reservation records are not exposed. Cart reservation semantics are not part of this phase.

# 9. Diagnostic Shipping Route Safety

Previous behavior: `/shipping-calculator-test` was registered publicly without an environment boundary.

New behavior: the existing diagnostic GET/POST routes register only in `local` and `testing` environments. The diagnostic remains available for development/tests but is not a production storefront shipping API and is not wired to the catalog endpoints.

# 10. Bugs Found

None in the catalog contract after the focused runtime tests. The initial implementation issues (resource eager-load callback typing, duplicate test fixture keys, and paginator metadata collision) were corrected before the final runs.

# 11. Production Files Changed

* `bootstrap/app.php`
* `routes/api.php`
* `routes/web.php`
* `app/Http/Controllers/Api/V1/ProductController.php`
* `app/Http/Requests/Api/V1/ApiFormRequest.php`
* `app/Http/Requests/Api/V1/ProductIndexRequest.php`
* `app/Http/Requests/Api/V1/ResolveVariationRequest.php`
* `app/Http/Resources/Api/V1/PublicImageResource.php`
* `app/Http/Resources/Api/V1/ProductSummaryResource.php`
* `app/Http/Resources/Api/V1/ProductDetailResource.php`
* `app/Http/Resources/Api/V1/ProductVariationResource.php`
* `app/Services/Catalog/ProductCatalogQuery.php`
* `app/Services/Catalog/ProductPriceResolver.php` (uses eager-loaded variations when available to avoid catalog pricing N+1 queries)

# 12. HTTP Runtime Tests

`tests/Feature/Api/V1/Products/ProductCatalogApiTest.php`: **6 tests, 54 assertions, 0 failures**. It covers public visibility, pagination, image URLs, integer money, effective/simple and variable pricing, filters/sorting, availability, detail resources, attributes/options, sensitive exclusions, authoritative order-independent variation resolution, invalid selections, inactive variations, and 404 behavior.

All API-related tests (`tests/Feature/Api` plus the existing diagnostic calculator test): **9 tests, 67 assertions, 0 failures**.

# 13. Full Isolated Suite

**252 tests, 1,420 assertions, 0 failures.** Pest emitted its existing non-fatal result-cache permission warning while writing `vendor/pestphp/pest/.temp/test-results`; the process exited successfully and the complete summary was captured.

# 14. Migration

`php artisan migrate`: **Nothing to migrate**.

`php artisan migrate:status`: all listed migrations are **Ran**.

# 15. Pint

`vendor/bin/pint --dirty`: passed. The new API files were also run through scoped Pint formatting.

# 16. git diff --check

Passed with no whitespace errors.

# 17. Remaining Storefront API Gaps

These are intentionally deferred future phases:

* Authentication decision and customer API auth boundary
* Cart
* Coupon integration
* Address/geography
* Shipping quote
* Checkout
* Payment
* Customer Orders/Shipment/Notifications
* Blog

# 18. Safety

* `ecommerce` was not reset or destructively modified.
* Development data was preserved; only non-destructive migration/status commands were run.
* `D:\uni-shop-project\front` was not changed.
* No real payment, SMS, or email provider was added.
* No Returns/Refunds functionality was added.
* No completed backend domain was redesigned; the API is an adapter around existing product, pricing, inventory, image, and variation services.

STOREFRONT API PHASE 1: VERIFIED PASS
