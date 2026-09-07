# Coupon Flow

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Customer submits code → current Cart/User context reaches `CouponService` → targeting, product, role/user, time, spend, and sale rules evaluate → Cart recalculates authoritative discount → safe result renders. Usage is not redeemed during mere cart application; checkout/order owns consumption according to current domain rules.
