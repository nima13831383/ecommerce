# Cart Flow

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Session/user resolves the current Cart → semantic Product/variation and quantity are validated → `CartService` locks/recalculates price, tax, coupon, and availability → Cart persists → Blade renders current totals. Guest token ownership remains server-side. No inventory reservation is created by add/update/remove.
