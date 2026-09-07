# Orders

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

`OrderService` owns creation, idempotency, snapshots, status transitions, reservation coordination, and history. Order items preserve names, SKU/variation labels, pricing, tax, discount, and quantities as historical values. Customer pages are read-only except for explicitly eligible payment initiation/retry. Customer access is scoped to the authenticated owner and must not expose admin notes or internal reconciliation data.
