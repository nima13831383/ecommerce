# Catalog and Variable Products

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Products are public only when active, published, and not soft-deleted. `ProductCatalogQuery` supplies public listing/detail reads and effective-price filtering; `ProductPriceResolver` is authoritative for current, regular, sale, and variable range pricing. `ProductVariantService` owns canonical option combinations, uniqueness, generation limits, and synchronization. The storefront must submit option identifiers and let Laravel resolve variations.

Product slugs are stable after creation. Blank slugs are generated with Unicode/Persian preservation and deterministic collision suffixes; explicit slugs remain unique and are not silently regenerated on name edits. Public resources exclude inventory ledgers, costs, reservations, and admin metadata.
