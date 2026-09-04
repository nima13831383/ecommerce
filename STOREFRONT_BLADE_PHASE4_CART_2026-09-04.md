# Blade Storefront Phase 4 — Cart

## Current Cart Domain Architecture

The existing \`CartService\` remains authoritative for Cart creation, line-key identity, sellability, variation ownership, pricing, tax, availability, recalculation, quantity updates, removal, clearing, and converted-cart state. Controllers do not perform price, tax, stock, or line-key calculations.

Guest Cart strategy: an opaque 64-character server-generated token is stored in the Laravel session under \`storefront_cart_token\`; the token is never placed in URLs or trusted as a visible Cart ID. Missing/inactive token Carts receive a new token Cart.

Authenticated Cart strategy: \`CartService::getOrCreateForUser()\` resolves the active Cart owned by the authenticated user. No request user ID or Cart ID is accepted.

Guest-to-login merge: no existing merge/claim policy was found, so it remains deferred. Guest and authenticated Carts are not silently merged.

Cart does not reserve inventory. Inventory reservations remain an Order/Checkout concern.

## Routes

- \`GET /cart\` — \`storefront.cart.show\`
- \`POST /cart/items\` — \`storefront.cart.items.store\`
- \`PATCH /cart/items/{item}\` — \`storefront.cart.items.update\`
- \`DELETE /cart/items/{item}\` — \`storefront.cart.items.remove\`
- \`DELETE /cart\` — \`storefront.cart.clear\`

All routes use the Laravel \`web\` middleware boundary and Blade forms include CSRF tokens.

## Controller / Service Flow

\`Storefront\\CartController\` resolves the current session/user Cart through \`StorefrontCartContext\`, validates semantic request fields with FormRequests, verifies CartItem ownership, delegates mutations to \`CartService\`, and redirects with safe flash/validation messages. Domain exception text is not exposed.

\`StorefrontCartContext\` prepares a bounded presentation array for the Cart page and shared header composer, eager-loading Product/primary image and variation option labels. Unavailable lines are shown as unavailable and do not present stale authoritative totals.

## Cart Presentation Contract

Cart lines include Product name/link, primary image or placeholder, selected variation options, quantity, unit price, line total, and current availability. Cart totals include subtotal, discount total, tax total, shipping placeholder, and grand total from the recalculated Cart. Shipping is not calculated in this phase.

## Product Detail Add-to-Cart

The existing Product Detail form now posts to \`storefront.cart.items.store\` with only Product ID, optional authoritative variation ID, and quantity. Simple in-stock Products have an active button; simple unavailable Products are disabled; variable Products remain disabled until the existing variation resolver returns an available variation. The server validates all values again.

## Simple Product Behavior

Simple Products resolve current effective pricing and availability through \`CartService\`. Sale prices and integer Rial totals are rendered from the persisted/recalculated Cart state.

## Variable Product Behavior

Variable Products require an active variation belonging to the Product. Cross-Product, missing, inactive, and mismatched variation IDs are rejected through the CartService path. Selected option labels are presentation-only.

## Quantity Validation

Web requests require an integer quantity from 1 through 1000. CartService additionally enforces positive quantities and current available inventory; over-available additions/updates are rejected safely.

## Inventory / No-Reservation Invariant

Adding and updating Cart lines performs availability checks but creates no \`InventoryReservation\` records. This is covered by the focused runtime tests.

## Cart Page

\`resources/views/storefront/cart/index.blade.php\` converts the raw \`cart.html\` structure to SSR. Quantity updates, remove, and clear use normal CSRF-protected PATCH/DELETE forms. Coupon UI and checkout mutation are intentionally not included; the checkout visual remains a disabled/deferred link.

## Empty State

An empty Cart returns HTTP 200 with the raw-template empty state, no demo lines, zero totals, and no active checkout summary.

## Header Cart State

The shared header now receives the current Cart context through a view composer. Count, preview lines, image/placeholder, line quantity/prices, grand total, and Cart links are real session/user Cart data; static demo identities were removed.

## Ownership / IDOR Results

Authenticated users cannot mutate another user’s Cart line. Guest session tokens isolate Cart lines between browser sessions. CartItem ownership is checked against the resolved current Cart before update/removal. Converted Carts are never mutated; an inactive guest token receives a new active session token Cart.

## Client-Side JavaScript Authority Audit

The raw Cart item and Coupon scripts were not loaded because they recalculate demo prices and implement fake Coupon behavior. Cart totals and quantities are server-rendered; standard forms remain the functional path. Existing header dropdown JavaScript is retained only for harmless open/close behavior. Product Detail JavaScript only controls UX and submits the server-validated variation ID.

## Browser Interaction

**Browser Cart interaction NOT VERIFIED.** No browser runner was introduced. PHP feature tests verify SSR/form contracts and server mutations.

## Bugs Found

None.

## Production Fixes

Implemented the focused Blade Cart adapter, shared Cart context/presenter, real header preview, Product Detail form activation, safe ownership checks, and Cart assets. No Cart domain formulas or business policies were changed.

## Focused Cart Test Result

Independent run 1: **9 passed, 81 assertions, 0 failures**.

Independent run 2: **9 passed, 81 assertions, 0 failures**.

File: \`tests/Feature/Storefront/CartTest.php\`.

## Product Detail Regression

\`php artisan test tests/Feature/Storefront/ProductDetailTest.php\`: **5 passed, 34 assertions, 0 failures**.

## Product API Regression

\`php artisan test tests/Feature/Api/V1/Products\`: **6 passed, 54 assertions, 0 failures**.

## Storefront Suite

\`php artisan test tests/Feature/Storefront\`: **20 passed, 166 assertions, 0 failures**.

## Full Isolated Suite

\`php artisan test\`: **284 passed, 1,712 assertions, 0 failures, 0 skipped**.

## Migration

\`php artisan migrate\`: **Nothing to migrate**.

\`php artisan migrate:status\`: all listed migrations **Ran**.

## Pint

\`vendor/bin/pint --dirty\`: **passed**.

## git diff --check

**Passed** with no whitespace errors.

## Production Files Changed

- \`app/Http/Controllers/Storefront/CartController.php\`
- \`app/Http/Requests/Storefront/CartItemStoreRequest.php\`
- \`app/Http/Requests/Storefront/CartItemUpdateRequest.php\`
- \`app/Providers/AppServiceProvider.php\`
- \`app/Services/Storefront/StorefrontCartContext.php\`
- \`resources/views/storefront/cart/index.blade.php\`
- \`resources/views/storefront/partials/header.blade.php\`
- \`resources/views/storefront/products/show.blade.php\`
- \`public/storefront/assets/css/cart/\`
- \`public/storefront/assets/js/product/detail-selection.js\`
- \`tests/Feature/Storefront/CartTest.php\`

## Raw Frontend Source

\`D:\uni-shop-project\front\` remains unchanged.

## Remaining Blade Work

- Auth/account visual integration
- Address/geography
- Coupon/shipping
- Checkout
- Payment
- Orders
- Blog

No frontend source edits, API Cart endpoints, provider integrations, or unrelated domain changes were added.
