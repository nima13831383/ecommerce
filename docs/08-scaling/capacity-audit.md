# Capacity Audit

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

The latest audit is evidence, not a production capacity guarantee. Local Windows validation used PHP 8.2.12, PDO MySQL, and reachable MySQL; `pcntl`, `posix`, and Redis extensions were unavailable locally. Twenty-one real MySQL concurrency tests with 273 assertions passed against isolated `ecommerce_testing`. Production RPS/capacity remains uncertified until production-like load, worker, database, cache, and media measurements are available. The audit verdict was good with identified bottlenecks.
