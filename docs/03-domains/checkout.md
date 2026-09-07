# Checkout

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

`CheckoutService` previews and places orders from the current cart, owned addresses, semantic shipping choices, coupon context, and an idempotency key. It rebuilds authoritative pricing, tax, inventory, shipping, and a request fingerprint; clients never submit trusted totals. Order creation and item/snapshot persistence are transactional. Same-key retries are safe only according to the service's fingerprint/idempotency contract.
