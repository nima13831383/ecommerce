# Technology Stack

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

| Area | Current technology |
| --- | --- |
| Runtime | PHP 8.2+, Laravel 12 |
| Admin | Filament 5, policies/Gates, Spatie permissions |
| Web UI | Blade SSR, Persian RTL, Tailwind-compatible compiled assets, jQuery/Alpine where retained by the template |
| API | Laravel routes under `/api/v1`, API Resources, FormRequests |
| Data | MySQL in development/production; isolated SQLite or MySQL testing as documented |
| Cache/session/queue | Laravel abstractions; database locally, Redis/Horizon where production requires it |
| Payments | Provider-neutral contract with ZarinPal adapter and server-side verification |
| SMS | SMS.ir adapter behind OTP service; tests use doubles and never live credentials |
| Frontend tooling | Vite, Tailwind, Playwright, npm scripts |
| Media | Laravel public storage disk with `storage:link` deployment requirement |

The complete dependency declarations are authoritative in `composer.json`, `composer.lock`, `package.json`, and `package-lock.json`.
