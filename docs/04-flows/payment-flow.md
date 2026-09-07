# Payment Flow

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Owned unpaid eligible Order → `PaymentService` creates/persists attempt from Order amount → gateway request/redirect → provider callback → persisted authority/amount match → server-side Verify → idempotent Payment/Order transition → inventory commit and notifications downstream. Browser return alone never marks paid.
