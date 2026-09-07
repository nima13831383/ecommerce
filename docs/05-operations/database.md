# Database Operations

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Development database `ecommerce` contains valuable persistent data. Allowed validation is `php artisan migrate` and `php artisan migrate:status`. Never run `migrate:fresh`, `db:wipe`, truncation, schema drops, database recreation, or destructive seeding against it. Automated tests use isolated SQLite or an explicitly suffixed MySQL test database.
