# Queue Workers

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Local database queues can run with `php artisan queue:work`. Production should use a supervised worker fleet with explicit queue priorities, retry/backoff, timeout, and failure monitoring. Cache rebuild queues are dedicated and should not be mixed with transactional work. Use `queue:failed`, `queue:retry`, `queue:forget`, and `queue:restart` deliberately; preserve evidence before removing failures.
