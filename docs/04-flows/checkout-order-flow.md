# Checkout to Order Flow

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Owned Cart + Address + semantic shipping/payment choices + idempotency key → `CheckoutService` preview/fingerprint → transactional `OrderService` creation with snapshots and reservation → commit → downstream payment/notification work. Duplicate same-key requests replay safely; conflicting fingerprints are rejected. No client total is trusted.
