# Notification Flow

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Meaningful domain event → notification intent persisted/queued → `DeliverCustomerNotification` attempts delivery with bounded retry/backoff → success/failure state is recorded without exposing provider internals. Duplicate job execution must not create uncontrolled duplicate intent or leak error payloads.
