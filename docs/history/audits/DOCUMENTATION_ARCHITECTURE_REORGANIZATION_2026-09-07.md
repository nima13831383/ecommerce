# Documentation Architecture Reorganization

Status: Historical / Non-authoritative
Last reviewed: 2026-09-07
Applies to: Documentation-only change

## 1. Objective

Create a navigable human/agent documentation architecture while preserving project rules and all historical Markdown evidence.

## 2. Inventory

Before the change there were 56 first-party root Markdown files. The root now contains only `AGENTS.md` and `README.md`; dated reports were moved, not deleted.

## 3. Canonical entry points

`README.md`, `AGENTS.md`, and `docs/README.md` are the current entry points. `docs/README.md` defines the AI reading order and links the canonical sections.

## 4. Canonical structure

Overview, codebase maps, domains, flows, operations, testing, security, and scaling now live under `docs/01-overview` through `docs/08-scaling`.

## 5. Domain coverage

Catalog, inventory, cart, checkout, orders, payments, coupons, shipping, shipments, users/auth/OTP, notifications, blog, settings, and storefront each have a focused guide.

## 6. Flow coverage

Storefront request, cart, checkout/order, inventory, payment, coupon, shipping, auth/OTP, cache, queue/job, and notification flows are documented.

## 7. Operations coverage

Runbooks cover application operation, local development, workers, scheduler, cache, Horizon, Redis, database, media, deployment, backup/restore, troubleshooting, and command reference.

## 8. Testing coverage

Testing strategy, isolated environments, concurrency, browser tests, and a QA checklist are documented.

## 9. Security coverage

Security model, authorization/IDOR, secrets/settings, payment verification, and database safety are documented.

## 10. Scaling coverage

Scalability and the latest capacity/concurrency audit are summarized with explicit limits; no production RPS guarantee is implied.

## 11. Historical preservation

Phase reports, QA reports, parity work, payment/SMS evidence, incident records, and reconstruction evidence remain under `docs/history` and are marked there as historical/non-authoritative.

## 12. Deployment source

The former root deployment notes are preserved as `docs/history/audits/DEPLOYMENT_LEGACY.md`; `docs/05-operations/deployment.md` is the current concise runbook.

## 13. AGENTS integration

`AGENTS.md` retains existing architecture, commerce, concurrency, Filament, shipping, payment, inventory, testing, storefront, settings, cache, and database-safety rules and gains a Documentation Maintenance section.

## 14. Change record

The modifying task is recorded at [`../../changes/2026/09/2026-09-07-codex-documentation-architecture.md`](../../changes/2026/09/2026-09-07-codex-documentation-architecture.md).

## 15. Link validation

Relative links were checked against the current repository tree; external URLs were not fetched. Commands and class names in canonical docs were cross-checked against repository files or Artisan command output where practical.

## 16. Code scope

No application PHP, Blade, JavaScript, migration, configuration, test, or raw frontend source was changed.

## 17. Data safety

No development database command was run. No provider request, credential rotation, or settings mutation was performed.

## 18. Secret safety

New canonical docs and this report contain no passwords, API keys, tokens, or customer credentials. Existing historical evidence was retained rather than rewritten.

## 19. Human navigation

The root README points to the canonical documentation entry point and safe operations/testing guidance.

## 20. Agent navigation

Agents are instructed to read `AGENTS.md`, then `docs/README.md`, then relevant domain/flow/runbook/security pages before changing code.

## 21. Status semantics

Canonical pages identify current guidance. Historical reports are explicitly separated and must not silently override current code.

## 22. No broad rewrite

The cleanup reorganized Markdown and added concise current guidance; it did not rewrite application architecture or historical findings.

## 23. Validation commands

`git diff --check` is the final whitespace validation. Documentation link/path checks are performed by a repository-local script during review.

## 24. Completion

This report is complete when the docs tree, root surface, AGENTS maintenance section, link checks, and Git diff review all pass.
