# Documentation Cleanup Inventory

Status: Historical / Non-authoritative
Last reviewed: 2026-09-07
Applies to: Documentation architecture change on 2026-09-07

## Before

The repository contained 56 first-party root Markdown files: `AGENTS.md`, `README.md`, `DEPLOYMENT.md`, and 53 dated audit/phase/incident/migration reports. Generated/vendor documentation was excluded from this count.

## After

The root Markdown surface is intentionally limited to `AGENTS.md` (project rules) and `README.md` (human entry point). `DEPLOYMENT.md` became the historical `docs/history/audits/DEPLOYMENT_LEGACY.md`; the current runbook is `docs/05-operations/deployment.md`. The 47 dated reports were moved without deletion to `docs/history/audits/`, `docs/history/incidents/`, or `docs/history/migrations/`.

## Classification policy

Canonical current guidance is under `docs/01-overview` through `docs/08-scaling`. Historical evidence remains under `docs/history`. Change records are under `docs/changes`. No report was deleted during this cleanup. `docs/history/archived-docs/` remains available for future redundancy reviews and is empty in this change.

## Safety

No application code, migrations, configuration, tests, frontend source, database, provider settings, or generated runtime state was changed by the documentation reorganization.
