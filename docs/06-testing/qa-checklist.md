# QA Checklist

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

- Read `AGENTS.md` and the relevant canonical docs.
- Inspect current service/model/routes before changing behavior.
- Add focused regression tests at the boundary changed.
- Verify authorization and cross-owner behavior.
- Verify integer Rial, stable enums, machine timestamps, and sensitive-field exclusions.
- Use isolated tests; preserve development `ecommerce`.
- Run focused tests, relevant regressions, then `php artisan migrate`/`migrate:status`, Pint, and `git diff --check`.
- Update canonical docs and add a change record.
- Keep historical reports historical and report browser/provider limits truthfully.
