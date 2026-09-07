# Uni Shop E-commerce

Status: Canonical project entry point
Last reviewed: 2026-09-07

Laravel 12 e-commerce platform with a Filament administration panel, commerce domain services, a Persian RTL Blade SSR storefront, payments, blog/CMS, and production-oriented operations tooling.

## Quick start

1. Copy `.env.example` to `.env` and configure a local database.
2. Run `composer install` and `npm install`.
3. Run `php artisan key:generate` and `php artisan migrate`.
4. Build assets with `npm run build` or use `composer run dev` locally.

Never reset the persistent development database. Use the isolated testing environment for automated tests.

## Documentation

The canonical documentation entry point is [`docs/README.md`](docs/README.md). It contains the reading order, architecture maps, domain guides, operational runbooks, testing guidance, security rules, scaling notes, and historical evidence.

Project-wide agent rules remain in [`AGENTS.md`](AGENTS.md).

## Main surfaces

- Laravel web routes and Blade SSR storefront
- Filament admin panel at `/admin`
- Versioned public APIs under `/api/v1`
- MySQL-backed commerce domains with configurable cache, queue, session, and storage drivers
- Queue workers and optional Horizon monitoring in production

## Testing

Use `php artisan test --compact` for the isolated suite. Run focused tests when changing a domain, and use the documented MySQL-only concurrency harness only with an isolated `*_testing` database.

## Operations

See [`docs/05-operations/application-runbook.md`](docs/05-operations/application-runbook.md), [`docs/05-operations/deployment.md`](docs/05-operations/deployment.md), and [`docs/05-operations/command-reference.md`](docs/05-operations/command-reference.md) for safe local and production procedures.
