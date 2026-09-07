# Database Safety

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

The normal development database is `ecommerce` and contains valuable data. Never run `migrate:fresh`, `db:wipe`, truncation, schema drops, `DROP DATABASE`, database recreation, or destructive seeding against it unless the user explicitly authorizes that exact action in the current task. Tests must be isolated; normal migration validation is additive.
