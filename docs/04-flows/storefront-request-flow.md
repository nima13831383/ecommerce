# Storefront Request Flow

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Browser request → `web` middleware/session/CSRF → route/controller → FormRequest or boundary validation → query/domain service → presenter/view model → Blade response. Mutations flash safe messages and redirect. Server recalculates authoritative values on every sensitive write. API requests follow the same boundary principle but return Resources and machine-readable errors.
