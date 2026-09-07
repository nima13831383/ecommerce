# Inventory

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

`InventoryService` is authoritative for adjustments, availability, reservations, commits, releases, and expiry. Operations use transactions and row locks where required. Cart additions and shipping quotes do not reserve stock; checkout/order flow owns reservation and later commit/release. Inventory transaction and reservation records are never public storefront data.

Run `php artisan inventory:expire-reservations` through the coordinated scheduler. Inspect the service before adding stock behavior; do not perform application-memory-only concurrency fixes.
