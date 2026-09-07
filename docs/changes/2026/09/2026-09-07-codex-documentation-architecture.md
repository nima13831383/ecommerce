# Documentation Architecture Change

Status: Change record
Date: 2026-09-07

## Scope

Reorganized first-party Markdown into a canonical `docs/` architecture, reduced root documentation to `AGENTS.md` and `README.md`, preserved historical reports under `docs/history`, and added human/agent navigation.

## Files and sections

- Added canonical overview, codebase, domain, flow, operations, testing, security, and scaling guides.
- Added `docs/README.md`, history/change indexes, cleanup inventory, and final reorganization report.
- Moved dated root reports without deletion; archived legacy deployment notes.
- Updated `AGENTS.md` with Documentation Maintenance rules and the moved historical readiness path.
- Replaced the default Laravel README with a project entry point.

## Validation

Relative Markdown links, core paths/classes, and command names were reviewed. `git diff --check` is required before commit. No application test suite or development database command is needed for this documentation-only change.

## Safety

No application code, migration, configuration, test, frontend source, provider setting, secret, or database was changed.
