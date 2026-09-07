# Testing Strategy

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Use the smallest layer that proves the invariant: unit tests for pure rules, service tests for domain transitions, Laravel Feature tests for routes/authorization/persistence, and real-browser tests for DOM/interaction behavior. Any new storefront/API contract should cover success, validation, ownership, stable enums, integer Rial, dates, errors, and idempotency where relevant. Do not call PHP Feature tests browser E2E.
