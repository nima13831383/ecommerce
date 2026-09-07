# Deployment

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Deploy code, additive migrations, compiled assets, configuration, and shared storage in a controlled order. Confirm `APP_KEY`, `APP_URL`, database, mail, payment/SMS secrets, queue/cache/session drivers, storage, and Site Settings. Run `php artisan storage:link` for local public media, then health/migration checks. Start workers and scheduler with multi-node safeguards; use Horizon only with Redis and supported production process extensions. Never print secrets or use development reset commands during deployment.
