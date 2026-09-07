# Coupons

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

`CouponService` owns percent, fixed-cart, and fixed-product rules, targeting, date windows, spend limits, sale exclusions, and usage limits. Excluded users take precedence; explicit inclusion and role rules are evaluated according to current service policy, while product exclusions always apply. Applying a coupon to a cart evaluates eligibility; redemption occurs only at the authoritative order/checkout point. No free-shipping coupon type is active.
