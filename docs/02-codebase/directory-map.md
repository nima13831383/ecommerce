# Codebase Directory Map

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

| Path | Responsibility |
| --- | --- |
| `app/Http` | Web/API controllers, requests, resources, middleware |
| `app/Models` | Eloquent persistence models and relationships |
| `app/Services` | Application/domain services and authoritative calculations |
| `app/Jobs` | Queue work for notifications and cache maintenance |
| `app/Events`, `app/Listeners` | Domain facts and asynchronous side effects |
| `app/Enums` | Stable domain states and types |
| `app/Filament` | Admin resources, pages, widgets, and actions |
| `app/Console` | Commands and scheduled operations |
| `bootstrap` | Application and exception bootstrap |
| `config` | Environment-backed infrastructure configuration |
| `database` | Migrations, factories, and isolated seed support |
| `resources/views` | Blade layouts, storefront pages, mail, and admin-adjacent views |
| `routes` | Web, API, auth, and console route definitions |
| `public` | Public assets and linked storage |
| `tests` | Feature/unit/concurrency/browser-facing test harnesses |
| `docs` | Canonical guides, operations, security, scaling, changes, and historical evidence |

Generated directories such as `vendor`, `node_modules`, compiled views, and test artifacts are not documentation sources.
