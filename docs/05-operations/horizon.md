# Horizon

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Horizon monitors Redis queues in production and may be installed locally without being executable on Windows when `pcntl`/`posix` are absent. Horizon is not a database-queue monitor. Configure supervisors, balancing, retention, and alerting in deployment infrastructure; the application remains queue-driver configurable.
