# STOREFRONT DEMO PRODUCTS — 2026-09-04

## 1. Demo Creation Mechanism

Command: `php artisan demo:storefront-products`

Environment safety: the command runs only in `local`, `development`, or `testing` environments and fails in any other environment.

Idempotency key strategy: product SKU + slug, attribute slug, attribute-value slug within its attribute, variation SKU + the canonical `ProductVariantService::combinationSignature()`, and product image path.

The command reconciles only records carrying the stable demo identifiers below. It does not add itself to `DatabaseSeeder`.

## 2. Categories / Brands Reused or Created

Run 1 created these categories: `عطر و ادکلن`, `اکسسوری`, `لوازم آرایشی`, and `مراقبت پوست`.

Run 1 created these brands: `Aurora`, `Lumière`, and `Maison Noir`.

Run 1 created three demo tags: `جدید`, `محصول متغیر`, and `پیشنهاد ویژه`.

Run 2 reused all categories, brands, and tags without duplicates.

## 3. Variable Perfume

Product ID: `1`

SKU: `DEMO-PERFUME-001`

Name: `ادو پرفیوم Aurora Velvet`

Status: published and featured

Attributes/options:

| Attribute | Options |
| --- | --- |
| حجم | 30 میلی‌لیتر, 50 میلی‌لیتر, 100 میلی‌لیتر |
| نوع بسته‌بندی | استاندارد, کادویی |

Variations: 6 active variations, generated through `ProductVariantService`.

| Combination | SKU | Price (IRR) | Stock | Canonical signature |
| --- | --- | ---: | ---: | --- |
| 30ml / استاندارد | DEMO-PERFUME-30-STANDARD | 18,500,000 | 8 | `1:1|2:4` |
| 30ml / کادویی | DEMO-PERFUME-30-GIFT | 20,500,000 | 3 | `1:1|2:5` |
| 50ml / استاندارد | DEMO-PERFUME-50-STANDARD | 24,900,000 | 12 | `1:2|2:4` |
| 50ml / کادویی | DEMO-PERFUME-50-GIFT | 27,500,000 | 0 | `1:2|2:5` |
| 100ml / استاندارد | DEMO-PERFUME-100-STANDARD | 36,900,000 | 5 | `1:3|2:4` |
| 100ml / کادویی | DEMO-PERFUME-100-GIFT | 40,500,000 | 2 | `1:3|2:5` |

Price range from `ProductPriceResolver`: minimum `18,500,000`, maximum `40,500,000` IRR.

The parent uses 0 stock because variable inventory is managed at variation level. Weight is stored in kilograms and volume in cubic centimeters; the parent is marked `fragile`.

## 4. Variable Bracelet

Product ID: `2`

SKU: `DEMO-BRACELET-001`

Name: `دستبند استیل Luna`

Status: published and featured

Attributes/options:

| Attribute | Options |
| --- | --- |
| رنگ | طلایی, نقره‌ای, رزگلد |
| سایز | Small, Medium, Large |

Variations: 9 active variations, generated through `ProductVariantService`.

| Combination | SKU | Regular / sale (IRR) | Stock |
| --- | --- | ---: | ---: |
| طلایی / Small | DEMO-BRACELET-GOLD-S | 7,500,000 / 6,900,000 | 6 |
| طلایی / Medium | DEMO-BRACELET-GOLD-M | 7,900,000 | 5 |
| طلایی / Large | DEMO-BRACELET-GOLD-L | 8,300,000 | 4 |
| نقره‌ای / Small | DEMO-BRACELET-SILVER-S | 6,900,000 | 8 |
| نقره‌ای / Medium | DEMO-BRACELET-SILVER-M | 7,400,000 | 0 |
| نقره‌ای / Large | DEMO-BRACELET-SILVER-L | 7,800,000 | 3 |
| رزگلد / Small | DEMO-BRACELET-ROSE-S | 7,800,000 | 5 |
| رزگلد / Medium | DEMO-BRACELET-ROSE-M | 8,400,000 | 4 |
| رزگلد / Large | DEMO-BRACELET-ROSE-L | 8,900,000 | 2 |

Price range from `ProductPriceResolver`: minimum `6,900,000`, maximum `8,900,000` IRR. The discounted price is active on the gold/Small variation. The product-level variable resolver intentionally reports `is_discounted: false`; variation-level pricing reports the discount.

## 5. Simple Sale Product

Product ID: `3`

SKU: `DEMO-SERUM-001`

Product: `سرم آبرسان Hydra Glow`

Status: published and featured; stock `15`; regular price `12,500,000` IRR; active sale price `9,900,000` IRR; sale window is currently active.

## 6. Simple Normal Product

Product ID: `4`

SKU: `DEMO-LIPSTICK-001`

Product: `رژ لب Velvet Rose`

Status: published and featured; stock `20`; price `7,500,000` IRR; no sale.

## 7. Out-of-Stock Product

Product ID: `5`

SKU: `DEMO-POCKET-PERFUME-001`

Product: `عطر جیبی Midnight`

Status: published and featured; price `4,900,000` IRR; stock `0`.

## 8. Unpublished Control Product

Product ID: `6`

SKU: `DEMO-HIDDEN-001`

Product: `محصول مخفی تستی`

Status: draft; not featured; stock `0`. Its public detail route returned 404 and it was absent from home/listing responses.

## 9. Images

All files are locally generated SVG placeholders stored through the configured `ProductImage` storage disk. No external or hotlinked images were used.

| Product | Image count | Primary |
| --- | ---: | --- |
| Aurora Velvet perfume | 3 | `primary.svg` |
| Luna bracelet | 2 | `primary.svg` |
| Hydra Glow serum | 1 | `primary.svg` |
| Velvet Rose lipstick | 1 | `primary.svg` |
| Midnight pocket perfume | 1 | `primary.svg` |
| Hidden control product | 0 | — |

Every demo product with images has exactly one primary image. All eight physical files were confirmed present on the configured disk.

## 10. Inventory

All stock initialization used `InventoryService::setOnHand()`.

Variable product stock was written by `ProductVariantService`, which delegates to `InventoryService`. No variable parent stock was written as inventory.

Run 1 created 15 opening-stock transactions: 13 stocked variations plus the two stocked simple products. Zero-stock variations and the zero-stock simple product correctly required no inventory transaction. Run 2 created zero inventory transactions.

Current available quantities equal on-hand quantities because no reservations exist:

* Perfume variations: `8, 3, 12, 0, 5, 2`.
* Bracelet variations: `6, 5, 4, 8, 0, 3, 5, 4, 2`.
* Simple products: serum `15`, lipstick `20`, pocket perfume `0`.

## 11. Variation Resolver Verification

The authoritative `/api/v1/products/{product}/resolve-variation` route was used without changing resolver logic.

* Perfume `50 میلی‌لیتر + استاندارد` resolved to `DEMO-PERFUME-50-STANDARD`, with availability `true`.
* Perfume `50 میلی‌لیتر + کادویی` resolved to `DEMO-PERFUME-50-GIFT`, with availability `false` because on-hand is zero.
* Both variable product detail pages rendered their configured axes and options.

## 12. Price Resolver Verification

`ProductPriceResolver` returned:

* Perfume: minimum `18,500,000`, maximum `40,500,000` IRR.
* Bracelet: minimum `6,900,000`, maximum `8,900,000` IRR.
* Hydra Glow: regular `12,500,000`, sale/effective `9,900,000`, discounted `true`.

## 13. Storefront Verification

Live Laravel SSR route verification against the current development database:

* Home `/`: 200; featured published demo products were present.
* Listing `/products`: 200; published demo products were present and the draft control product was absent.
* Perfume detail: 200.
* Bracelet detail: 200.
* Hidden control detail: 404.

## 14. Idempotency Run 1

Command output: created 4 categories, 3 brands, 3 tags, 6 products, 4 attributes, 11 attribute values, 15 variations, and 8 images. It initialized two simple-product inventory balances plus the 13 non-zero variation balances.

## 15. Idempotency Run 2

Command output: reused 4 categories, 3 brands, 3 tags, 6 products, 4 attributes, 11 attribute values, 15 variations, and 8 images. It created zero products, variations, images, or inventory adjustments. No duplicates were created.

## 16. Tests

Added `tests/Feature/Console/StorefrontDemoProductsCommandTest.php`.

Focused verification: 21 tests passed, 167 assertions, including the new command tests, existing canonical variation tests, and existing home/listing/detail storefront tests.

The new tests cover product counts, 6/9 variations, unique canonical signatures, idempotent rerun counts, inventory transaction behavior, sale pricing, media, unpublished visibility, SSR routes, and valid/zero-stock variation resolution.

## 17. Migration

`php artisan migrate`: passed with `Nothing to migrate`.

`php artisan migrate:status`: all existing migrations were already `Ran`. No schema changes were added.

## 18. Pint

`vendor/bin/pint --dirty`: passed.

## 19. git diff --check

Passed with no whitespace errors.

## 20. Files Changed

Created:

* `app/Console/Commands/CreateStorefrontDemoProducts.php`
* `tests/Feature/Console/StorefrontDemoProductsCommandTest.php`
* `STOREFRONT_DEMO_PRODUCTS_2026-09-04.md`

The pre-existing dirty worktree changes were preserved. `D:\uni-shop-project\front` was not modified.

## 21. Safety

* Existing development Products were not deleted.
* `ecommerce` was not reset, wiped, truncated, or recreated.
* No Cart, Order, Payment, Shipment, Reservation, or Coupon usage data was created by the demo command; current observed counts for each are zero.
* No raw frontend files were modified.
* No production database path is permitted by the command environment guard.

## Final Status

**STOREFRONT DEMO PRODUCTS: VERIFIED PASS**
