# Cart

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

`CartService` resolves authenticated user carts or opaque random guest tokens kept in the Laravel session. It owns add/update/remove/clear, coupon association, recalculation, and conversion preparation. Controllers submit semantic Product/variation identifiers and quantities only. Prices, tax, discount, availability, and totals are recalculated server-side. Cart state is mutable and does not reserve inventory.

Ownership is resolved from the current session/user; submitted cart IDs and line IDs are not ownership proof. Guest-to-login merge behavior must remain an explicit product decision.
