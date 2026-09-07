# Architecture

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

## Request boundaries

Web and API requests validate at the boundary, call application/domain services, and return Blade view data or API Resources. Controllers and Filament resources remain thin. Blade never calls the application's own API for normal SSR; both adapters share query and domain layers.

## State authority

Products, prices, tax, coupons, inventory, shipping, checkout totals, payment verification, order transitions, and shipment transitions are authoritative in Laravel services and persisted state. Client JavaScript may collect choices and display returned values but never computes authoritative money or state.

## Persistence and consistency

Critical multi-step changes use database transactions, unique constraints, locks, idempotency, or domain services as appropriate. Orders snapshot mutable commercial data. Queues handle non-critical side effects and cache maintenance. Cache is never the source of truth for transactional customer state.

## Deployment shape

Production is compatible with multiple Laravel nodes, shared MySQL, shared Redis where selected, workers, a coordinated scheduler, and shared/object media storage. Local development may use database sessions/cache/queues. See [`../05-operations/deployment.md`](../05-operations/deployment.md) and [`../08-scaling/scalability-overview.md`](../08-scaling/scalability-overview.md).
