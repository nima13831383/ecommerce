# Security Model

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Trust boundaries are browser/client input, web/API boundary, domain services, persistence, queues, and external providers. Validate at every boundary, authorize ownership server-side, use CSRF for web mutations, keep provider callbacks session-independent but persisted-attempt-bound, and expose only public presentation data. Cache and frontend state are never authority for money, stock, identity, or transitions.
