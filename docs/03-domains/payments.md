# Payments

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

`PaymentService` persists attempts, binds the order-derived amount, initiates the configured gateway, and verifies server-side. ZarinPal is the current adapter behind the provider-neutral contract. Callback query values are untrusted: a payment becomes successful only after the persisted attempt, authority, amount, and server verification all match. Callbacks are idempotent and session-independent where required. Never log or expose provider secrets or raw gateway payloads.
