# Cache Operations

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Use `cache:storefront-status`, `cache:rebuild-products`, `cache:rebuild-blog`, and the documented cache rebuild workflow. Rebuilds enqueue work and publish build-before-swap generations; they do not synchronously warm the entire catalog. Do not use `Cache::flush()`, Redis wildcard scans, or tags to solve storefront maintenance. Preserve active/previous generations and inspect pending/running rebuilds before pruning.
