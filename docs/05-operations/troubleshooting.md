# Troubleshooting

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Start with environment and logs, then `about`, `migrate:status`, `settings:status`, `cache:storefront-status`, `queue:failed`, and `schedule:list`. For payment issues inspect the persisted attempt/authority and provider-safe diagnostics; for inventory inspect reservations/locks; for cache inspect generation/rebuild state. Avoid broad cache flushes, destructive database resets, and logging credentials.
