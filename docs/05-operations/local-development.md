# Local Development

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Use `.env` derived from `.env.example`, local MySQL/database queues or the configured project defaults, `composer install`, `npm install`, and `npm run build`/`composer run dev`. Run migrations additively. Use `php artisan test --compact` with the testing environment; do not point tests at `ecommerce`. Use demo commands only when explicitly needed and understand that they write development data.
