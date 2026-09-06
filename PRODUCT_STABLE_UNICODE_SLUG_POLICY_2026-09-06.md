# Product Stable Unicode Slug Policy

## 1. Root Cause

The Filament Product name field used `afterStateUpdated` to assign `Str::slug($name, ...)` on every blur. That reactive callback changed populated slugs and transliterated Persian names.

## 2. Previous Behavior

Renaming a Product in the Product form rewrote its slug; Persian input could become an ASCII transliteration.

## 3. New Slug Policy

Existing non-blank Product slugs remain stable. Automatic generation runs only on the authoritative Product save path when the slug is blank.

## 4. Unicode/Persian Handling

Generated slugs preserve Unicode letters and digits, trim unsafe punctuation, collapse whitespace/underscores/dashes, and use hyphens. Persian letters and Persian digits remain Unicode.

## 5. Unique Suffix Algorithm

The first free candidate is used; collisions are generated deterministically as `-2`, `-3`, and so on. Soft-deleted Products are included in uniqueness checks because the database slug index remains global.

## 6. Explicit Slug Behavior

Explicit non-blank slugs are preserved exactly. Filament validates duplicate explicit slugs and does not silently rewrite them.

## 7. Filament Behavior

The Product name field is no longer reactive to slug state. A blank slug is accepted and generated during save; a populated slug is not changed by name edits.

## 8. Central Write Path

`App\Services\Catalog\ProductSlugService` performs Unicode normalization and generated-collision lookup. `Product::saving` invokes it for blank slugs, covering direct model/service/command writes as well as Filament.

## 9. Database Uniqueness

`products.slug` already has a unique database index in the existing schema; no migration was added.

## 10. Concurrency

The database unique index remains the final authority for concurrent writes. The focused change does not add a speculative locking subsystem or alter Product concurrency architecture.

## 11. Existing Data

No existing Products were bulk-updated. Existing slugs and public URLs remain unchanged.

## 12. Public Route Test

A published Product with a Persian Unicode slug resolves through `/products/{product:slug}` with HTTP 200.

## 13. Focused Tests

`tests/Feature/Filament/Products/ProductSlugPolicyTest.php`: 10 passed, 22 assertions.

Combined Product/Filament/storefront/API regression: 31 passed, 242 assertions.

## 14. Full Suite

443 passed, 2,852 assertions, 0 failures, 0 skipped (87.65s). Pest emitted its existing result-cache permission warning; it did not affect the green result.

## 15. Database Safety

No destructive database command was run. No migration was required.

## 16. Raw Frontend

`D:\uni-shop-project\front` was unchanged.

## 17. AGENTS.md

Added the permanent stable Unicode Product slug rules without removing existing project rules.
