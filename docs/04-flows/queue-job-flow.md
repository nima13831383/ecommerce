# Queue and Job Flow

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Domain event or command → queued Job on its intended queue → worker retry/backoff/timeout/uniqueness → idempotent side effect → failed-job inspection/retry. Cache rebuild jobs use `cache-rebuild`; detail refresh jobs use `cache-refresh`; notification delivery is separate. Production Redis/Horizon is optional infrastructure; local database queues are supported.
