# Shipments

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

`ShipmentService` enforces at most one Shipment per Order and owns transitions, tracking updates, and history. Supported statuses are pending, ready, shipped, delivered, and cancelled. Customers may view safe status/tracking projections for their own orders but cannot create or mutate shipments.
