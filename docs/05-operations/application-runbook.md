# Application Runbook

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Before operating, confirm environment, `APP_URL`, database, queue/cache drivers, storage disk, and secret configuration. Use `php artisan migrate` and `php artisan migrate:status`; never reset the persistent development database. Inspect `queue:failed`, logs, cache status, settings status, and health endpoints before restarting services. Escalate payment, inventory, and database incidents with their persisted identifiers and timestamps.

Safe recovery commands are indexed in [`command-reference.md`](command-reference.md), [`troubleshooting.md`](troubleshooting.md), and [`backup-restore.md`](backup-restore.md).
