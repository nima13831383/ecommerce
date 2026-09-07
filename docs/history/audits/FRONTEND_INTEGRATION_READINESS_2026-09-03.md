# Backend → Frontend Integration Readiness Audit — 2026-09-03

## 1. Executive Summary

**Status: NOT READY.**

The commerce domain services are substantial and retain server authority for pricing, tax, inventory, coupons, shipping, checkout, payments, fulfillment, and notification intent creation. However, the customer-facing HTTP contract is not implemented. There is no `routes/api.php`, no customer API controller layer, no API Resources, and no public Product, Cart, Coupon, Address, Shipping Quote, Checkout, Order, Payment, Shipment, Notification, or Blog endpoints.

The existing web routes are Laravel Breeze session/profile/authentication pages and an intentionally diagnostic shipping calculator. They are not a stable JSON storefront contract. A separate frontend cannot safely begin commerce implementation until the missing read/write endpoints, response schemas, authentication boundary, and runtime HTTP contract tests are added.

No frontend code or backend endpoint was implemented in this audit.

## 2. Current Public API Architecture

- `routes/api.php` does not exist and `bootstrap/app.php` does not register an API route file.
- `app/Http/Resources` does not exist.
- `app/Http/Controllers` contains only Breeze authentication, profile, and the diagnostic shipping calculator controllers.
- Domain services are callable in-process but are not HTTP contracts: `ProductPriceResolver`, `CartService`, `CouponService`, `AddressService`, `ShippingCostResolver`, `CheckoutService`, `PaymentService`, `OrderService`, `ShipmentService`, `CustomerNotificationService`, and `PostService`.
- Public responses are currently HTML views or redirects. There is no standard JSON success/error envelope, pagination contract, or API exception renderer.
- Admin/Filament routes are intentionally excluded from storefront scope.

## 3. Route Inventory

### Storefront-relevant current routes

| Method | URI | Auth | Controller/action | Validator | Response | Domain service | Pagination | Status / gap |
|---|---|---|---|---|---|---|---|---|
| GET | `/` | No | `routes/web.php` closure | None | `welcome` HTML view | None | No | 200 HTML only; no product sections |
| GET | `/shipping-calculator-test` | No | `TapinCalculatorController@show` | None | `shipping-calculator-test` HTML view | `WordpressShippingDataLoader`, `ShippingOptionCatalog` | No | Diagnostic only; not a storefront quote API |
| POST | `/shipping-calculator-test` | No | `TapinCalculatorController@calculate` | `CalculatePostalShippingRequest` | HTML view/session validation | `PostShippingCalculator` | No | Accepts caller origin, package, weight, declared value, and service; must not become production checkout API |
| GET | `/profile` | `auth` session | `ProfileController@edit` | None | HTML view | None | No | Own profile page only; no JSON customer contract |
| PATCH | `/profile` | `auth` session | `ProfileController@update` | `ProfileUpdateRequest` | Redirect/session flash | None | No | Own profile mutation; same-origin web form |
| DELETE | `/profile` | `auth` session | `ProfileController@destroy` | Current-password validation | Redirect | User model delete | No | Account deletion is available as a web form; no frontend JSON contract |
| GET/POST | `/register` | Guest session | Breeze controllers | Breeze validation | HTML/redirect | User creation | No | Session auth only |
| GET/POST | `/login` | Guest session | Breeze controllers | `LoginRequest` | HTML/redirect | Session guard | No | Session auth only |
| POST | `/logout` | `auth` session | Breeze controller | None | Redirect | Session guard | No | Same-origin session operation |
| GET/POST | `/forgot-password` | Guest session | Breeze controllers | Breeze validation | HTML/redirect | Password broker | No | Session/web only |
| GET/POST | `/reset-password/{token}` / `/reset-password` | Guest session | Breeze controllers | Breeze validation | HTML/redirect | Password broker | No | Session/web only |
| GET/POST | `/verify-email...` | `auth` session | Breeze controllers | Signed/throttled route | HTML/redirect | MustVerifyEmail | No | Session/web only |

The route list contains 66 routes including Filament/admin routes; only the routes above are public/customer-facing. There are zero JSON commerce endpoints.

## 4. Product Listing

**Contract: missing — BLOCKER.**

There is no public listing controller, route, request filter, API Resource, or pagination shape. `ProductPriceResolver` can calculate simple effective prices and variable-product minimum/maximum prices, but no HTTP layer invokes it. A storefront currently cannot obtain stable product ID, name, slug, primary image, current/effective price, sale state, product type, stock/availability, category, brand, badges, or pagination.

Required next contract: a server-side listing query/resource that eager-loads the intended public relations and calls the authoritative price resolver. It must not serialize raw price columns as the sole price authority.

## 5. Product Detail

**Contract: missing — BLOCKER.**

No public detail-by-slug/ID route exists. Product, image, taxonomy, SEO, attribute, variation, and availability data are available in models/services only. Internal inventory ledger/reservation data must remain private when the endpoint is added.

## 6. Variable Product Selection

**Contract: missing — BLOCKER.**

`ProductVariantService` and canonical variation signatures are authoritative internally, but no public endpoint accepts selected attribute-value IDs and resolves the matching active variation. A frontend would otherwise have to duplicate domain canonicalization and pricing/availability logic, which is explicitly unsafe. The future contract should accept option/value IDs and return the authoritative variation, or expose an explicit safe map generated by the backend.

## 7. Cart

**Contract: service-only — BLOCKER for storefront.**

`CartService` supports authenticated carts (`getOrCreateForUser`) and token carts (`getOrCreateForToken`), add/update/remove/clear/recalculate/convert, and derives unit prices through `ProductPriceResolver`. It validates product/variation ownership, stock availability, taxes, and coupons. Cart does not reserve inventory; reservations are created by order creation.

No HTTP adapter exists for obtaining a cart/token, adding simple or variable lines, changing quantity, removing/clearing lines, or returning recalculated line/totals data. No JSON contract exposes line identity, display data, unit/effective price, tax, discount, availability, and integer Rial totals.

## 8. Coupons

**Contract: service-only — BLOCKER for storefront.**

`CouponService` remains the authority for normalization, targeting, user/role eligibility, discounted-product exclusion, usage limits, discount calculation, apply, and reverse. `CartService` calls it during apply/recalculation. No public apply/remove/validate endpoint exists, so there is no stable error/status contract for invalid, expired, exhausted, ineligible, or cart-ineligible codes. The frontend must not implement any coupon policy.

## 9. Addresses / Geography

**Contract: service-only — BLOCKER for checkout.**

`AddressService` scopes reads and mutations to the authenticated user, validates mobile/postal data, and resolves province/city through `WordpressShippingDataLoader`. It supports create/update/delete/get/snapshot, but no customer HTTP endpoints exist.

There are no public province or city-by-province endpoints. The authoritative geography loader exists, but a frontend has no supported way to populate location choices or validate IDs without duplicating the dataset. This is a **HIGH** integration gap in addition to the checkout blocker.

## 10. Shipping Quote

**Contract: missing — BLOCKER.**

`ShippingCostResolver::quote()` derives origin from settings, package/weight/parcel metrics from the cart, and supports calculator, fixed, and free modes. It returns a `ShippingQuoteResult` with service, availability, integer total, currency, breakdown, and metadata. No production customer endpoint accepts cart + selected address + shipping service/payment type and returns quote options.

The public `/shipping-calculator-test` route is not an acceptable replacement: its request intentionally accepts caller-controlled origin province/city, destination, weight, declared value, parcel type, package size, service, and payment type. It is a diagnostic HTML form, unauthenticated, and must remain outside the production storefront contract. It should be isolated or access-controlled in production deployment rather than used by checkout.

## 11. Checkout

**Contract: service-only — BLOCKER.**

`CheckoutService::preview()` and `placeOrder()` accept a typed `CheckoutInput` containing cart ID, shipping/billing address IDs, shipping service/payment type, idempotency key, and optional customer note/request metadata. The service recalculates the cart, validates address ownership/type/geography, obtains server shipping quote, evaluates coupons, snapshots addresses/shipping/tax, derives all totals, reserves inventory, and creates an Order. It does not trust caller prices, tax, discount, weight, origin, package, or inventory state.

No request/controller/JSON response exists. The future response should expose Order ID/number, order/payment status, final integer Rial totals, shipping snapshot, and the next payment step without exposing internal reservation or pricing implementation details.

## 12. Payment

**Contract: provider-agnostic service-only — BLOCKER for payment UX.**

`PaymentService::initiate()` derives amount from the locked Order, stores initiation idempotency/fingerprint, and delegates to `PaymentGatewayRegistry`; `verify()` performs server-side gateway verification and updates Payment/Order atomically. `PaymentGatewayInterface` and initiation/verification DTOs provide a future provider boundary. No customer HTTP initiate/callback/status endpoint exists, and no concrete gateway is registered in the current application configuration.

What can be stabilized now: a provider-neutral initiate response shape (`payment ID/status`, redirect/client instruction, and provider-independent error) and an authenticated order-ownership check. Provider-specific redirect/callback behavior must wait for provider selection. The frontend must never submit or choose the authoritative amount.

## 13. Customer Account / Orders

**Contract: missing — BLOCKER.**

There are no customer endpoints for profile JSON, addresses, own cart, Orders list/detail, payment state, shipment/tracking, or customer notifications. Models and services contain ownership relationships, but no HTTP policy/scope boundary is exercised for these flows. Admin/Filament resources are not customer APIs.

`OrderService` preserves historical item/address/tax/shipping snapshots and `ShipmentService` owns fulfillment transitions, but a storefront cannot read those snapshots or status history. A future Order detail resource should serialize snapshots rather than current mutable Product values and should exclude admin notes, inventory audit, and raw payment/provider payloads.

## 14. Authentication

**Contract: web session only — HIGH / effectively a blocker for separate-origin frontend.**

Authentication is Laravel Breeze using the `web` session guard, database sessions, HTML forms, CSRF, email verification, and password reset routes. There is no Sanctum/token guard, `routes/api.php`, CORS config, or stateful-domain configuration. The deployment origin for a separate frontend is unknown, so cookie/SameSite/CSRF/CORS requirements cannot currently be asserted.

Do not introduce a second auth strategy in this audit. Before a separate-origin frontend starts, choose and document one supported session/token boundary and add runtime contract tests.

## 15. Blog/CMS

**Contract: service/model-only — BLOCKER.**

`PostService` supports create/update/publish/schedule/unpublish and `Post::published()` excludes drafts and future scheduled posts when used. No public Post listing, slug detail, category, tag, or pagination route exists. No public controller/resource currently proves that drafts or scheduled content are excluded.

## 16. Response Contract Consistency

There is no customer JSON response contract to compare. Existing responses are mixed HTML views, redirects, session validation errors, service DTOs, Eloquent models, and Filament component state. There is no standard pagination, error envelope, status-code mapping, or serialization policy.

Recommendation: introduce one API convention when endpoints are implemented (versioned URI, JSON Resource classes, consistent `data/meta/errors` shape, machine-readable enum values, and domain exceptions mapped centrally). Do not serialize models directly or expose service DTO internals by accident.

## 17. Money / Date / Enum Serialization

- Internal money handling is generally integer Rial in Cart/Order/Payment and resolver results; Product/ProductVariation database casts use `decimal:0`, so a public Resource must explicitly cast/serialize monetary fields as JSON integers and never leak decimal strings/floats.
- Dates are Carbon/datetime casts internally. No public serialization format exists; use stable ISO-8601 machine timestamps in the future contract, with localization only in presentation.
- Stable string-backed enums exist for Product type (string fields), Order/Payment/Shipment/Notification statuses, address type, shipping mode, and Post status. No public endpoint currently exposes them; future clients must consume enum values, not Persian admin labels.

## 18. Authorization / IDOR Audit

The service layer contains useful ownership checks: `CartService::getForUser`, `AddressService::getForUser/update/delete`, and `CheckoutService` address/cart ownership paths. Payment/Order/Shipment ownership is not exposed through a customer controller, so HTTP IDOR behavior is **not runtime-tested**. There is no public endpoint against which another user's Cart, Address, Order, Shipment, Payment, or Notification can currently be queried.

This is a missing-contract/high-risk area, not evidence that future endpoints are automatically safe. Every new route needs explicit authenticated ownership scopes and HTTP tests for cross-user IDs.

## 19. Sensitive Field Exposure

No public JSON endpoint currently exposes fields, so there is no observed accidental customer JSON leakage. Internal models contain fields that must remain excluded from future Resources, including inventory reservations/transactions, payment `gateway_response`, card-related fields, initiation fingerprints, internal notification errors/payloads, admin notes, and user roles/permissions. The diagnostic shipping route exposes caller-provided calculation inputs and breakdown in HTML; it does not represent a customer account contract.

## 20. Frontend Readiness Matrix

| Frontend area | Backend contract | Exists | Runtime tested | Missing | Severity | Ready? |
|---|---|---:|---:|---|---|---:|
| Home product sections | None; `/` is welcome HTML | No | No | Product query/resource | BLOCKER | No |
| Product listing | None | No | No | Listing, pagination, price/availability Resource | BLOCKER | No |
| Product filters/search | None | No | No | Supported filter/sort query contract | BLOCKER | No |
| Product detail | None | No | No | Detail-by-slug/ID Resource | BLOCKER | No |
| Variable selection | Internal `ProductVariantService` only | Partial | Service tests only | Option-ID → authoritative variation endpoint | BLOCKER | No |
| Cart | `CartService` only | Partial | Service tests only | Customer cart HTTP contract/token bootstrap | BLOCKER | No |
| Coupon | `CouponService`/`CartService` only | Partial | Service tests only | Apply/remove/error JSON contract | BLOCKER | No |
| Address book | `AddressService` only | Partial | Service tests only | Own-address CRUD endpoints | BLOCKER | No |
| Province/city | Loader only | Partial | Admin/diagnostic validation only | Public geography endpoints | HIGH | No |
| Shipping quote | Resolver only; diagnostic route is unsuitable | No | Diagnostic HTML only | Cart+address quote endpoint | BLOCKER | No |
| Checkout | `CheckoutService` only | Partial | Service/concurrency tests only | Preview/place HTTP contract/idempotency response | BLOCKER | No |
| Payment initiation | `PaymentService` + interface | Partial | Service tests only | Customer initiate/callback/status contract; provider registration | BLOCKER | No |
| Payment result/state | Payment model/service only | No | No customer HTTP test | Own-order payment status endpoint | BLOCKER | No |
| Orders list | `Order` model/service only | No | No | Own-order summary Resource/pagination | BLOCKER | No |
| Order detail | Snapshot-capable `Order` model | No | No | Snapshot/tax/shipping/status Resource | BLOCKER | No |
| Shipment tracking | `ShipmentService`/model only | No | Admin tests only | Own-order shipment read endpoint | HIGH | No |
| Profile | Web HTML profile routes | Partial | Profile HTTP tests | JSON contract or same-origin decision | HIGH | No |
| Authentication | Breeze web session | Partial | Auth HTTP tests | Separate-origin auth/CORS/CSRF/token decision | HIGH | No |
| Blog listing | `Post::published()`/`PostService` only | No | Service/admin tests only | Public listing/category pagination | BLOCKER | No |
| Blog detail | Post model only | No | No | Published slug endpoint | BLOCKER | No |

## 21. BLOCKERS

1. No public Product listing/detail/variation-selection contract.
2. No Cart, Coupon, Address, Shipping Quote, Checkout, Order, Payment, Shipment, Notification, or Blog customer endpoints.
3. No server-defined JSON response, pagination, error, enum, date, or monetary serialization contract.
4. Checkout/payment cannot be safely wired from a separate frontend without HTTP ownership, idempotency, and server-authoritative response boundaries.

## 22. HIGH Gaps

- Public geography endpoints are absent; the frontend would otherwise duplicate authoritative province/city data.
- `/shipping-calculator-test` is unauthenticated and accepts caller-controlled origin/package/weight/value inputs. It is a diagnostic route, not a production quote API, and should be isolated from production use.
- Authentication is web-session-only; separate-origin frontend requirements for Sanctum/token or correctly configured stateful cookies, CORS, CSRF, and SameSite are undocumented/unconfigured.
- Customer HTTP authorization/IDOR tests do not exist because customer routes do not exist.
- Profile and shipment tracking have internal/web/admin capability but no customer JSON contract.

## 23. MEDIUM / LOW Gaps

- No public SEO/blog response shape, image URL policy, or cache invalidation contract exists yet.
- No documented API versioning or deprecation policy.
- No explicit public availability/badge semantics for products.
- No browser E2E was performed; this audit is code/route inspection plus existing HTTP feature tests.

## 24. Existing HTTP Runtime Tests

The focused existing HTTP smoke run completed successfully: 26 tests, 74 assertions, 0 failures, 0 skipped, covering Breeze authentication/profile/password flows and the diagnostic postal-shipping calculator. `PostalShippingCalculatorTest` verifies geography validation and a reference quote. These tests do not constitute storefront API coverage.

## 25. Missing Runtime Contract Tests

Before storefront implementation, add HTTP feature tests for each new contract: product listing/detail/filter/pagination, variation resolution, cart ownership and recalculation, coupon error mapping, address/geography ownership, shipping quote authority, checkout preview/place/idempotency, payment initiate/status/callback boundary, own-order/shipment/notification reads, auth origin strategy, and published-only blog listing/detail. Include cross-user IDOR cases and assert integer Rial/ISO date/string enum serialization.

## 26. Recommended Backend Implementation Order

1. Decide/version the customer API boundary and authentication strategy (same-origin session or explicitly configured token/Sanctum approach).
2. Add shared JSON error/validation and pagination conventions plus API Resources.
3. Add public catalog listing/detail and authoritative variation-resolution reads.
4. Add cart/address/geography resources and shipping quote endpoint, keeping all calculations server-side.
5. Add checkout preview/place with idempotency and ownership tests.
6. Add provider-neutral payment initiation/status/callback boundary after a provider is selected.
7. Add customer Orders/Shipment/Notification reads and published-only Blog endpoints.

This order reuses the completed domain services; it does not call for rebuilding them.

## 27. What Frontend Can Start Today

Only non-commerce integration planning and contract/design work can start safely today. The frontend can consume no stable customer commerce JSON endpoint. It may target the existing same-origin Breeze profile/auth pages only if the deployment chooses server-rendered web forms, but that is not a separate customer API contract.

## 28. What Frontend Must NOT Start Yet

Do not implement product catalog, variable selection, cart, coupon UI, address book, shipping selection, checkout totals, payment flow, order history, shipment tracking, or blog pages against model/service internals or the diagnostic shipping form. Those flows require the server-authoritative HTTP contracts listed above.

## 29. Files Created / Changed

Created:

- `FRONTEND_INTEGRATION_READINESS_2026-09-03.md`

No application production files were changed in this audit.

## 30. Safety

- Development database `ecommerce` was not reset, migrated destructively, or modified by this audit.
- No frontend implementation was added.
- No payment, SMS, email, or external provider was added.
- No Returns/Refunds work was added.
- No concurrency or Filament CRUD/media/deletion QA was repeated.

FRONTEND INTEGRATION READINESS: NOT READY
