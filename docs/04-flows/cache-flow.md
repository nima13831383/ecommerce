# Storefront Cache Flow

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Canonical query key → `StorefrontQueryCache` checks active generation → fresh hit returns → stale hit may serve SWR while one lock-holder refreshes → hard miss uses an atomic lease/waiter path → build-before-swap publishes a complete generation. Detail writes enqueue targeted refresh after commit; obsolete slugs are invalidated. Cache failures degrade safely and never replace live commerce truth.
