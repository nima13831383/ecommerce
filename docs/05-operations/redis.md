# Redis

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Redis is optional local infrastructure and a supported production backend for cache, queues, locks, rate limiting, sessions, and coordination. Configure host/port/credentials/TLS/database through environment/configuration. Application code should use Laravel abstractions; Redis must not become the only local development dependency or a source of truth for transactional state.
