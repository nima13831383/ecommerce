# Architecture Decisions

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

1. **Blade SSR is the storefront architecture.** The raw template is converted into same-application Laravel views; the API remains an adapter for other consumers and justified AJAX.
2. **Services own commerce authority.** Controllers, Blade, JavaScript, and Filament do not duplicate price, tax, stock, shipping, payment, or transition rules.
3. **Orders use snapshots.** Historical display must not depend on mutable or deleted catalog records.
4. **Storefront caches are read optimizations only.** Transactional state is always loaded or recalculated from authoritative services.
5. **Site Settings are registry-backed.** Core keys and types are application structure; values are persisted and validated through the settings service.
6. **Provider adapters are replaceable.** ZarinPal and SMS.ir are behind contracts, with no provider secrets in documentation or source control.
7. **Development data is protected.** Destructive database commands are forbidden against the normal `ecommerce` database; tests use isolation.
