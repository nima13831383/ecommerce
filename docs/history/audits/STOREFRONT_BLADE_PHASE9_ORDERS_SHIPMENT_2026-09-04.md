# 1. Raw Templates

- Orders: `D:\uni-shop-project\front\orders.html`
- Order Detail: `D:\uni-shop-project\front\order-detail.html`
- Shared account shell/sidebar: `D:\uni-shop-project\front\account.html` and the converted storefront account partials.
- The existing account stylesheet bundle already contains the order-card, detail, timeline, and responsive presentation rules; no raw source files were modified.

# 2. Routes

- `GET /account/orders` (`storefront.account.orders`) — authenticated customer Orders list.
- `GET /account/orders/{order}` (`storefront.account.orders.show`) — authenticated customer Order Detail.
- Existing `POST /orders/{order}/payment` remains the only customer payment mutation and is reused for eligible retry/continue-payment.
- There are no customer Shipment mutation routes.

# 3. Orders Query / Presenter

`StorefrontOrderQuery` scopes every query to the authenticated `User`, supports bounded latest-first pagination/status filtering, resolves either the public order number or a numeric identity only within that owner scope, and eager-loads summary/detail relations. `StorefrontOrderPresenter` converts Orders into explicit storefront-safe arrays and centralizes status labels, money casts, snapshots, payment eligibility, and shipment presentation.

# 4. Orders List

Pagination is Laravel bounded pagination (10 per page) with query strings preserved. Status filters use only real `OrderStatus` values. Empty customers receive a real empty state and no demo rows. The dashboard shows only real recent Orders and count.

# 5. Order Detail

The former placeholder is replaced by a real SSR detail view with order identity/date, status, timeline, item lines, totals, payment state, snapshots, shipping information, and shipment state. Historical data remains renderable when the current Product is soft-deleted or unpublished.

# 6. OrderItem Snapshots

Item name, SKU, variation attributes, quantity, unit price, discount, tax, and line total come from persisted `OrderItem` snapshots. Current Product data is used only for an optional image and a Product Detail link when the Product is still public.

# 7. Address / Shipping Snapshots

Shipping and billing address output is whitelisted from Order JSON snapshots. Shipping output contains only customer-safe service/mode/payment type, amount, and currency; internal origin/package/weight metadata is not rendered.

# 8. Money Presentation

All Order, item, discount, tax, shipping, payment, and grand-total values are cast to integer Rial in the presenter and formatted only for display with `number_format`. Historical Order totals are never recomputed from current Product pricing.

# 9. Order Status Presentation

Machine enum values remain authoritative. A single presenter mapping supplies Persian labels for pending, awaiting payment, processing, shipped, delivered, completed, cancelled, refunded, and failed states.

# 10. Customer Timeline

The detail timeline is built from persisted `OrderStatusHistory` rows of type `status`, ordered chronologically. Internal comments, actor IDs, reconciliation details, and technical events are excluded.

# 11. Payment Presentation

Only safe payment status, amount, created time, and paid time are exposed. Gateway responses, reconciliation payloads, idempotency keys, and internal transaction data are excluded.

# 12. Continue / Retry Payment

`StorefrontOrderPresenter::paymentRetryAllowed()` requires an owned Order, configured local/testing gateway, unpaid state, an eligible pending/awaiting-payment/failed Order status, and active unexpired item reservations. The existing provider-neutral payment initiation route is reused. Paid and cancelled Orders show no action.

# 13. Shipment Presentation

- No Shipment: customer-safe processing message.
- Pending: `در انتظار پردازش`.
- Ready: `آماده ارسال`.
- Shipped: `ارسال شده`.
- Delivered: `تحویل شده`.
- Cancelled: `لغو شده`.

The view reflects the actual single Shipment relation and never creates or mutates one.

# 14. Tracking

Real tracking number, carrier/service, tracking URL, shipped timestamp, delivered timestamp, and safe Shipment history are rendered only when present. No tracking number or external URL is fabricated.

# 15. Ownership / IDOR

`whereBelongsTo($user)` scopes list and detail queries. Numeric IDs and public order numbers for another customer return 404 and do not disclose existence. No request-supplied user ID or Shipment identity is trusted.

# 16. Sensitive Data Exclusions

Responses/views exclude roles/permissions, inventory transactions/reservations, gateway responses, reconciliation internals, idempotency keys, checkout fingerprints, admin notes, notification error payloads, and Shipment mutation metadata.

# 17. Account Dashboard Integration

The authenticated account dashboard displays a bounded recent-Order preview and real total count. Unsupported wallet, loyalty, fake pending, and fabricated discount metrics remain absent.

# 18. Product Image / Storage Deployment Note

Order item images use the existing `PublicImageResource` contract and `/storage` public-media mapping, with the storefront placeholder when unavailable. Deployment requires the existing Laravel public storage link: `php artisan storage:link`.

# 19. Bugs Found

- `ORDERS-UI-BUG-001` — Severity: MEDIUM. Scenario: Order Detail with an item image. Input: rendered detail request. Expected: HTTP 200 with safe public image. Actual: namespace typo (`AppHttp\\Resources`) caused a server error. Exception: class not found. Root cause: incorrect import in the new presenter. Affected file: `app/Services/Storefront/StorefrontOrderPresenter.php`.
- `ORDERS-UI-BUG-002` — Severity: MEDIUM. Scenario: Order list/detail SSR. Input: authenticated request. Expected: account sidebar renders and page returns 200. Actual: missing `$user` view data caused an undefined variable error. Root cause: new OrderController views did not receive the authenticated user. Affected file: `app/Http/Controllers/Storefront/OrderController.php`.
- `ORDERS-UI-BUG-003` — Severity: MEDIUM. Scenario: eager-loaded Order/Shipment detail. Input: typed nested relation closure. Expected: relation query executes. Actual: Laravel supplied a relation-specific builder and rejected the `Eloquent\\Builder` type declaration. Root cause: overly narrow closure type hints. Affected file: `app/Services/Storefront/StorefrontOrderQuery.php`.

# 20. Production Fixes

Corrected the presenter import, supplied authenticated user view data, and removed incompatible nested eager-load closure type hints. No Order, Payment, Shipment, inventory, or domain lifecycle rules were changed.

# 21. Orders Storefront Tests

`tests/Feature/Storefront/OrdersTest.php`: **6 passed, 50 assertions, 0 failures, 0 skipped**. This covers guest redirect, owner scoping, pagination, empty/dashboard state, snapshots after Product deletion, status/timeline/payment presentation, safe retry eligibility, and all Shipment lifecycle views.

# 22. Shipment Presentation Tests

The same focused runtime file exercises no Shipment, pending, ready, shipped/tracking, delivered, and cancelled states, ownership, single-Shipment behavior, and absence of mutation routes: **6 passed, 50 assertions, 0 failures, 0 skipped**.

# 23. Payment Regression

`tests/Feature/Storefront/PaymentTest.php`: **5 passed, 40 assertions, 0 failures, 0 skipped**.

# 24. Checkout Regression

`tests/Feature/Storefront/CheckoutTest.php`: **7 passed, 57 assertions, 0 failures, 0 skipped**.

# 25. Shipment Domain Regression

`tests/Feature/Fulfillment/ShipmentServiceTest.php`: **6 passed, 26 assertions, 0 failures, 0 skipped**.

# 26. Storefront Suite

`php artisan test tests/Feature/Storefront`: **53 passed, 450 assertions, 0 failures, 0 skipped**.

# 27. Browser Interaction

**Browser Orders/Shipment interaction NOT VERIFIED.** PHP Feature tests prove SSR, ownership, and server contracts; no browser runner was used in this phase.

# 28. Full Isolated Suite

`php artisan test`: **317 passed, 1,996 assertions, 0 failures, 0 skipped**.

# 29. Migration

`php artisan migrate`: **Nothing to migrate**. `php artisan migrate:status`: all listed migrations **Ran**.

# 30. Pint

`vendor/bin/pint --dirty`: **passed**.

# 31. git diff --check

`git diff --check`: **passed with no output**.

# 32. Production Files Changed

- `app/Services/Storefront/StorefrontOrderQuery.php`
- `app/Services/Storefront/StorefrontOrderPresenter.php`
- `app/Http/Controllers/Storefront/OrderController.php`
- `app/Http/Controllers/Storefront/AccountController.php`
- `routes/web.php`
- `resources/views/storefront/account/orders.blade.php`
- `resources/views/storefront/account/order-detail.blade.php`
- `resources/views/storefront/account/index.blade.php`
- `tests/Feature/Storefront/OrdersTest.php`
- `STOREFRONT_BLADE_PHASE9_ORDERS_SHIPMENT_2026-09-04.md`

# 33. Raw Frontend Source

`D:\uni-shop-project\front` is unchanged.

# 34. Safety

- Development `ecommerce` data was untouched; no destructive database command was run.
- Customers cannot mutate Order status or Shipment state/tracking through storefront routes.
- No Returns/Refunds, customer cancellation, reorder, or status-change workflow was added.
- No real payment provider was added.
- Internal Payment, Inventory, reservation, reconciliation, and admin data is not exposed.

# 35. Remaining Storefront Work

- Blog
- Real Payment provider after provider selection
- Final browser/visual QA
- Guest Cart merge only if a later product requirement defines it
- Any genuinely unfinished static pages

`BLADE STOREFRONT PHASE 9 ORDERS SHIPMENT: VERIFIED PASS`
