# Blade Storefront

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

The storefront is same-application Laravel Blade SSR. The raw RTL/Persian template at `D:\uni-shop-project\front` is the visual source; `resources/views/storefront` and public assets are the converted implementation. Web controllers call shared query/domain services directly. JavaScript provides progressive enhancement only and never becomes authority for commerce values.

Verified storefront areas include catalog, variable selection, cart, account/address/geography, coupon/shipping quote, checkout/order creation, payment UX, orders/shipment views, blog, and static pages; consult phase reports in `docs/history/audits/` for evidence and limitations.
