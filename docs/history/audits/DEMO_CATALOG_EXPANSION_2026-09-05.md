# 1 Previous Catalog

Before this expansion, the deterministic storefront demo command reconciled 6 Products: 5 published Products and 1 draft control Product (`DEMO-HIDDEN-001`), with 15 existing variable variations. Existing Product identities, signatures, media, and inventory were preserved.

# 2 New Products

The command adds exactly 10 Products: 5 variable and 5 simple. All are published, use unique deterministic SKUs/slugs, Persian names/descriptions, integer Rial prices, and local deterministic primary media.

| Name | SKU | Type | Category | Brand | Regular Price (Rial) | Sale Price (Rial) | Stock | Variations |
|---|---|---|---|---|---:|---:|---:|---:|
| ادو پرفیوم نور Aurora | DEMO-PERFUME-002 | variable | perfume | Aurora | variation-driven | variation-driven | variation-owned | 6 |
| دستبند استیل Luna کلاسیک | DEMO-BRACELET-002 | variable | accessories | Lumière | variation-driven | variation-driven | variation-owned | 4 |
| رژ لب مات Velvet رنگی | DEMO-LIPSTICK-002 | variable | cosmetics | Maison Noir | variation-driven | variation-driven | variation-owned | 4 |
| کیف آرایش روزمره رنگی | DEMO-MAKEUP-BAG-001 | variable | accessories | Lumière | variation-driven | variation-driven | variation-owned | 3 |
| بادی اسپلش نسیم شب | DEMO-BODY-MIST-001 | variable | perfume | Aurora | variation-driven | variation-driven | variation-owned | 3 |
| گردنبند استیل آفتاب | DEMO-NECKLACE-001 | simple | jewelry | Lumière | 11,500,000 | 9,200,000 | 6 | — |
| ریمل حجم‌دهنده سایه | DEMO-MASCARA-001 | simple | cosmetics | Maison Noir | 7,800,000 | — | 2 | — |
| کرم آبرسان ابریشم | DEMO-CREAM-001 | simple | skincare | Maison Noir | 16,500,000 | 13,200,000 | 12 | — |
| ضدآفتاب مینرال روشن | DEMO-SUNSCREEN-001 | simple | skincare | Aurora | 14,900,000 | 11,920,000 | 0 | — |
| سرم مو ابریشمین | DEMO-HAIR-SERUM-001 | simple | haircare | Aurora | 18,900,000 | 16,065,000 | 5 | — |

After reconciliation the deterministic catalog contains 16 Products total: 15 published and 1 draft, with 35 total variations (15 existing plus 20 new).

# 3 New Taxonomy

Two deterministic Product Categories were added: `زیورآلات` (`jewelry`) and `مراقبت مو` (`haircare`). Existing four categories, three brands, and three tags were reused. The first development run reported 7 new attributes and 19 new attribute values; subsequent runs reused them.

# 4 Variable Products

Combinations are generated through the existing `ProductVariantService` and canonical signatures:

- `DEMO-PERFUME-002`: 30ml/50ml/100ml × standard/gift = 6.
- `DEMO-BRACELET-002`: gold/silver × small/large = 4.
- `DEMO-LIPSTICK-002`: rose/classic-red/warm-nude/cherry = 4.
- `DEMO-MAKEUP-BAG-001`: pink/olive/black = 3.
- `DEMO-BODY-MIST-001`: 100ml/200ml/300ml = 3.

Each generated combination has a unique canonical signature. Existing variable Products and their signatures were not recreated.

# 5 Pricing

All prices are integer Rial values. Four of the five new simple Products are discounted, and sale variations are present across the expanded variable catalog (including gift perfume, gold bracelet, rose lipstick, olive makeup bag, and 200ml body mist). No floating-point monetary values or alternate currency conversions were introduced. Storefront/API pricing continues to use the existing authoritative resolver/query path.

# 6 Inventory

Simple Products cover in-stock, low-stock (`DEMO-MASCARA-001`, 2), and fully out-of-stock (`DEMO-SUNSCREEN-001`, 0) states. `DEMO-PERFUME-002` includes one out-of-stock variation and stocked/low-stock alternatives. The first development run reported 4 inventory adjustments (zero-stock owners require no opening transaction); the second run reported 0 adjustments. A focused regression manually changed simple and variation stock, reran the command, and verified stock and ledger counts were preserved.

# 7 Shipping Data

All 10 new Products have positive weight and volume. The perfume variable Product uses the existing `fragile` parcel classification; the other new Products use `normal`. No shipping settings, global origin, package configuration, or shipping architecture was changed.

# 8 Images

Every new Product has one deterministic local primary image. The first run reported 10 image records created; the second reported 0 created and 18 reused image records across the demo catalog. Images use the existing Product media disk and generated local SVG assets; no raw filesystem paths or external URLs were added.

# 9 First Command Run

Command: `php artisan demo:storefront-products`

Result: success. Created 2 categories, 10 Products, 7 attributes, 19 values, 20 variations, and 10 images; reused 4 categories, 3 brands, 3 tags, 4 attributes, 11 values, 15 variations, and 8 images; performed 4 inventory adjustments.

# 10 Second Command Run

Command rerun independently: `php artisan demo:storefront-products`

Result: success and idempotent. Created 0 categories, Products, attributes, values, variations, images, or inventory adjustments; all existing demo identities were reused. No duplicate records were created and changed inventory remained unchanged.

# 11 Storefront QA

The real browser smoke flow against the running Laravel app verified:

- new simple Product detail returned HTTP 200, submitted the real Add to Cart form, and appeared in `/cart`;
- new variable Product detail returned HTTP 200, rendered two option axes, resolved a selected variation through the existing endpoint, enabled Add to Cart, and appeared in `/cart`;
- final browser output: `{"simpleStatus":200,"variableStatus":200,"axisCount":2,"variationId":"16","cartLines":2,"url":"http://127.0.0.1:8001/cart"}`.

PHP storefront/API coverage also verified published visibility, Persian search/filter results, effective sale pricing, variable ranges, availability, and absence of the draft control Product. Browser coverage was intentionally limited to this narrow simple/variable add-to-cart proof; broad visual/browser QA was not repeated.

# 12 Focused Tests

`tests/Feature/Console/StorefrontDemoProductsCommandTest.php`: **4 passed, 148 assertions, 0 failures**. The file was run independently twice with the same result. Pest emitted the existing non-failing result-cache permission warning for `vendor/pestphp/pest/.temp/test-results`.

# 13 Full Suite

`php artisan test`: **359 passed, 2,285 assertions, 0 failures**. No tests were skipped. The same existing Pest result-cache permission warning was emitted without affecting the exit code.

# 14 Pre-Change Backup

`D:\\uni-shop-project\\db\\backups\\ecommerce_2026-09-05_111845.sql`

# 15 Post-Change Backup

`D:\\uni-shop-project\\db\\backups\\ecommerce_2026-09-05_112012.sql`

# 16 Migration Status

`php artisan migrate`: **Nothing to migrate.**

`php artisan migrate:status`: all migrations, including `2026_09_04_235500_persist_core_site_settings`, are **Ran**. No schema migration was added for this catalog expansion.

# 17 Database Safety

The development `ecommerce` database was modified only by the explicitly requested deterministic demo command, after a pre-change SQL backup, and was backed up again afterward. No `migrate:fresh`, wipe, truncate, drop, destructive seed, or reset command was run. Automated tests used the isolated test environment. Existing valuable Product data was not reset or manually inserted outside the command.

# 18 Files Changed

- `app/Console/Commands/CreateStorefrontDemoProducts.php`
- `tests/Feature/Console/StorefrontDemoProductsCommandTest.php`
- `DEMO_CATALOG_EXPANSION_2026-09-05.md`

No migration or unrelated domain redesign was added.

# 19 Raw Frontend

`D:\\uni-shop-project\\front` was not modified.

`DEMO CATALOG EXPANSION: VERIFIED PASS`
