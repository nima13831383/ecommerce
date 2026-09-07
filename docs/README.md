# Documentation

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

This is the entry point for project documentation. The repository is a Laravel 12 commerce platform with Filament administration, a Persian RTL Blade SSR storefront, MySQL commerce state, configurable cache/queue/storage infrastructure, and provider adapters.

## Reading order

1. [`01-overview/project-overview.md`](01-overview/project-overview.md)
2. [`01-overview/architecture.md`](01-overview/architecture.md)
3. [`01-overview/technology-stack.md`](01-overview/technology-stack.md)
4. [`02-codebase/directory-map.md`](02-codebase/directory-map.md) and [`02-codebase/routes-map.md`](02-codebase/routes-map.md)
5. The relevant guide in [`03-domains/`](03-domains/)
6. The relevant request or background flow in [`04-flows/`](04-flows/)
7. Runbooks in [`05-operations/`](05-operations/) and tests in [`06-testing/`](06-testing/)
8. Security and scaling guidance in [`07-security/`](07-security/) and [`08-scaling/`](08-scaling/)

## Canonical documentation

Current operational and architectural truth lives in `docs/`. Historical QA, incident, migration, and phase reports live under [`history/`](history/) and are evidence, not current policy. Change records live under [`changes/`](changes/).

## AI/agent reading order

Read `AGENTS.md` first. Then read this file, the overview and architecture pages, the relevant domain and flow pages, and the applicable runbook/security page. Read historical reports only when the task explicitly needs prior evidence. Do not treat a historical report as a replacement for current code or canonical documentation.

## Maintenance rules

- Keep one canonical source of truth for each rule.
- Add or update the relevant canonical page in the same change as code-affecting work.
- Record every modifying task in `docs/changes/YYYY/MM/`.
- Keep status headers accurate (`Canonical`, `Historical / Non-authoritative`, or `Working note`).
- Link to stable repository paths and avoid links to generated files, local secrets, or user-specific machines except where an existing raw-template path is an explicit project boundary.

## Index

- [Overview](01-overview/)
- [Codebase maps](02-codebase/)
- [Domain guides](03-domains/)
- [Request and job flows](04-flows/)
- [Operations](05-operations/)
- [Testing](06-testing/)
- [Security](07-security/)
- [Scaling](08-scaling/)
- [Change records](changes/)
- [Historical evidence](history/)
