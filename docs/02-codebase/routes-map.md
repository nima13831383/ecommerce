# Route Map

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

## Web storefront and account

Public catalog, home, blog, static pages, cart, account, address, checkout, payment-return, and customer order routes are defined in `routes/web.php`. Auth/Breeze and SMS OTP routes are in `routes/auth.php`. Customer mutations use `web`, CSRF, and ownership checks.

## API

`routes/api.php` registers `/api/v1/products`, product detail, variation resolution, and JSON customer auth. The API is not the normal SSR data path.

## Admin and diagnostics

Filament is mounted under `/admin`. The shipping calculator diagnostic is restricted to local/testing environments. Provider callbacks are session-independent and must verify persisted payment attempts server-side.

## Console and scheduled work

`routes/console.php` schedules reservation expiry, cache recovery/pruning, and optional Horizon snapshots with overlap and single-server safeguards. Commands are indexed in [`../05-operations/command-reference.md`](../05-operations/command-reference.md).
