# Blog and CMS

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Public blog archive/detail queries expose published Posts only; drafts and scheduled Posts before their publication time remain private. Post categories/tags and slug routes are rendered through Blade. `StorefrontQueryCache` may cache archive/detail reads with targeted invalidation after writes. Admin publishing remains a separate Filament workflow.
