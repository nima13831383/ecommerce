# Authorization

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Admin access uses policies/Gates and Filament authorization; hiding buttons is not sufficient. Customer routes use `auth` ownership scoping for carts, addresses, orders, payments, shipments, and notifications. Crafted IDs, line IDs, variation IDs, and callback values must be checked against the current principal/context and fail without revealing another user's records.
