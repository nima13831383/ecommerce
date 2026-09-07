# Backup and Restore

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Use the approved `db:backup-development` tooling only when its target and destination are confirmed. Backups must be encrypted/retained outside the application node and restore-tested in an isolated environment. A restore plan must include database, media, settings/secrets, queues, and post-restore cache invalidation; do not restore over `ecommerce` casually.
