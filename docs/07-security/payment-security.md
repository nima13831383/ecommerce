# Payment Security

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

The Order determines the payment amount. A callback is untrusted until the persisted attempt, authority, amount, and provider Verify response match. Transitions are transactional and idempotent; duplicate callbacks cannot repeat inventory/notification side effects. Do not expose gateway responses, secrets, authorities, idempotency keys, or reconciliation internals to customers.
