# Inventory Flow

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Adjustment or checkout command → transaction and row lock → `InventoryService` validates available state → reservation/commit/release transaction and history → event/job side effects after commit. Expired reservations are recovered by the scheduled command. Cart and cache never become inventory authority.
