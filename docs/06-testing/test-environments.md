# Test Environments

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

PHPUnit uses `APP_ENV=testing`, an isolated database (SQLite memory by default), array cache/session, and synchronous queue unless a test explicitly requires another boundary. MySQL concurrency tests require an isolated database name ending `_testing`, `APP_ENV=testing`, and a reachable MySQL connection. Reject any configuration resolving to development `ecommerce`. Provider tests use doubles/sandbox-safe adapters and never live SMS or payment credentials.
