# Scheduler

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Inspect schedules with `php artisan schedule:list` and run a safe local tick with `php artisan schedule:run`. Reservation expiry, cache recovery, generation pruning, and optional Horizon snapshots use overlap prevention and single-server coordination. Production needs one scheduler invocation per interval, not one uncoordinated process per node.
