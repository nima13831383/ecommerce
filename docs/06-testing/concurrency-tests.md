# Concurrency Tests

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Concurrency coverage targets inventory reservations, coupons, payments, checkout idempotency/races, shipments, and notification intent. Run sequentially and/or in the documented MySQL harness with real transactions, locks, unique constraints, and isolated fixtures. The latest audit evidence records 21 real MySQL concurrency tests and 273 assertions against isolated `ecommerce_testing`; production capacity still requires production-like load testing.
