# Notifications

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Notification intent is created from domain events where appropriate and delivery is handled asynchronously by `DeliverCustomerNotification`. The job is retryable with bounded attempts/backoff and must be idempotent enough for duplicate execution. Customer-facing data excludes internal error payloads and provider secrets.
