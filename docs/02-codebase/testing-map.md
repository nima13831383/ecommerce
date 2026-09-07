# Test Map

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Feature tests cover web storefront, API, Filament, auth, commerce services, and provider boundaries. Unit tests cover focused rules. MySQL concurrency tests are opt-in and must use an isolated database whose name ends in `_testing`; they must never target `ecommerce`. Browser tests are separate and only count as browser evidence when a real runner executes them.

Use the nearest existing test directory for a change and add regression coverage at the same boundary as the defect. See [`../06-testing/testing-strategy.md`](../06-testing/testing-strategy.md) and [`../06-testing/test-environments.md`](../06-testing/test-environments.md).
