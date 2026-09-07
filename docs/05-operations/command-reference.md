# Command Reference

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

| Purpose | Commands |
| --- | --- |
| App/test | `php artisan about`, `php artisan test --compact`, `php artisan serve` |
| DB | `php artisan migrate`, `php artisan migrate:status`, `php artisan db:backup-development` |
| Queues | `queue:work`, `queue:failed`, `queue:retry`, `queue:restart` |
| Scheduler | `schedule:list`, `schedule:run` |
| Cache | `cache:storefront-status`, `cache:rebuild-products`, `cache:rebuild-blog`, `cache:clear` |
| Settings | `settings:status`, `settings:sync` |
| Media | `storage:link`, `media:reconcile-public` |
| Inventory | `inventory:expire-reservations` |
| Payments | `payment:diagnose-zarinpal`, `payment:test-zarinpal-sandbox`, `payment:import-zarinpal-env` |
| SMS | `sms:status` |
| Demo | `demo:storefront-products`, `demo:storefront-blog` |

Run `php artisan list` for the current command surface. Commands that write data require an explicit scope review.
