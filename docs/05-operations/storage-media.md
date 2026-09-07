# Storage and Media

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Public Product/media URLs use the configured Laravel public disk and require `php artisan storage:link` in an environment that serves local public media. Production should use shared/object storage appropriate to multiple application nodes. Use `media:reconcile-public` only with an understood target and preserve source media; do not invent alternate URL/path mechanisms.
