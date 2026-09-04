# STOREFRONT DEMO BLOG — 2026-09-04

## 1. Demo Blog Command

Command: `php artisan demo:storefront-blog`

Environment guard: the command runs only in `local`, `development`, or `testing`; it refuses other environments, including production.

Idempotency strategy: stable normalized Persian slugs identify demo posts. A private content marker (`<!-- storefront-demo-blog -->`) protects existing non-demo content from overwrite. Categories and tags reconcile by stable slug/name, and featured images use deterministic SHA-256-derived filenames under the existing `public` storage disk.

## 2. Categories

Created on the first successful development reconciliation: 5.

Reused on the second reconciliation: 5.

Categories: راهنمای خرید، عطر و ادکلن، مراقبت پوست، زیبایی و آرایش، استایل و اکسسوری.

## 3. Tags

Created on the first successful development reconciliation: 10.

Reused on the second reconciliation: 10.

Tags: راهنمای خرید، عطر، پوست، آرایش، اکسسوری، انتخاب محصول، نگهداری، سبک زندگی، هدیه، محبوب.

## 4. Public Articles

Count: 18 published Posts.

| Title | Slug | Category | Published At | Image |
| --- | --- | --- | --- | --- |
| چطور عطر مناسب خودمان را انتخاب کنیم؟ | `چطور-عطر-مناسب-خودمان-را-انتخاب-کنیم` | راهنمای خرید | deterministic recent date | yes |
| راهنمای انتخاب هدیه برای مناسبت‌های مختلف | `راهنمای-انتخاب-هدیه-برای-مناسبت-های-مختلف` | راهنمای خرید | deterministic recent date | yes |
| ۵ نکته مهم قبل از خرید محصولات مراقبت پوست | `۵-نکته-مهم-قبل-از-خرید-محصولات-مراقبت-پوست` | راهنمای خرید | deterministic recent date | yes |
| راهنمای انتخاب اکسسوری برای استایل روزمره | `راهنمای-انتخاب-اکسسوری-برای-استایل-روزمره` | راهنمای خرید | deterministic recent date | yes |
| تفاوت ادو پرفیوم، ادو تویلت و پرفیوم چیست؟ | `تفاوت-ادو-پرفیوم-ادو-تویلت-و-پرفیوم-چیست` | عطر و ادکلن | deterministic recent date | yes |
| چطور ماندگاری عطر را بیشتر کنیم؟ | `چطور-ماندگاری-عطر-را-بیشتر-کنیم` | عطر و ادکلن | deterministic recent date | yes |
| راهنمای انتخاب رایحه مناسب فصل پاییز و زمستان | `راهنمای-انتخاب-رایحه-مناسب-فصل-پاییز-و-زمستان` | عطر و ادکلن | deterministic recent date | yes |
| اشتباهات رایج در نگهداری عطر و ادکلن | `اشتباهات-رایج-در-نگهداری-عطر-و-ادکلن` | عطر و ادکلن | deterministic recent date | no |
| روتین ساده مراقبت پوست برای شروع | `روتین-ساده-مراقبت-پوست-برای-شروع` | مراقبت پوست | deterministic recent date | yes |
| سرم آبرسان چیست و چه زمانی استفاده می‌شود؟ | `سرم-آبرسان-چیست-و-چه-زمانی-استفاده-می‌شود` | مراقبت پوست | deterministic recent date | yes |
| چطور نوع پوست خود را بهتر بشناسیم؟ | `چطور-نوع-پوست-خود-را-بهتر-بشناسیم` | مراقبت پوست | deterministic recent date | yes |
| ترتیب استفاده از محصولات مراقبت پوست | `ترتیب-استفاده-از-محصولات-مراقبت-پوست` | مراقبت پوست | deterministic recent date | yes |
| چطور رنگ رژ لب مناسب را انتخاب کنیم؟ | `چطور-رنگ-رژ-لب-مناسب-را-انتخاب-کنیم` | زیبایی و آرایش | deterministic recent date | yes |
| نکات ساده برای ماندگاری بیشتر آرایش | `نکات-ساده-برای-ماندگاری-بیشتر-آرایش` | زیبایی و آرایش | deterministic recent date | yes |
| لوازم آرایشی ضروری برای یک کیف روزمره | `لوازم-آرایشی-ضروری-برای-یک-کیف-روزمره` | زیبایی و آرایش | deterministic recent date | no |
| راهنمای انتخاب دستبند متناسب با استایل | `راهنمای-انتخاب-دستبند-متناسب-با-استایل` | استایل و اکسسوری | deterministic recent date | yes |
| چطور اکسسوری‌ها را با هم ست کنیم؟ | `چطور-اکسسوری‌ها-را-با-هم-ست-کنیم` | استایل و اکسسوری | deterministic recent date | yes |
| ایده‌های ساده برای کامل کردن استایل با اکسسوری | `ایده‌های-ساده-برای-کامل-کردن-استایل-با-اکسسوری` | استایل و اکسسوری | deterministic recent date | yes |

Each article contains a distinct Persian title, excerpt, and two controlled HTML paragraphs with no scripts, iframes, or external trackers.

## 5. Draft Control

`پیش‌نویس داخلی وبلاگ` is a draft (`پیش-نویس-داخلی-وبلاگ` after current normalization), has no publication date, and is excluded from the public scope.

## 6. Scheduled Controls

Future: `ترندهای زیبایی فصل آینده` and `راهنمای خرید هدیه‌های نوروزی`, both scheduled in the future and excluded by the current `Post::published()` scope.

Boundary behavior: not applicable. The current domain requires scheduled Posts to have a future timestamp; no scheduled-at-or-before-now control was invented.

## 7. Featured Images

With image: 16 public Posts plus 2 scheduled controls (18 deterministic local SVG files total).

Without image: 2 public Posts, intentionally exercising the existing placeholder.

Storage: existing `public` disk under `storage/app/public/blog/demo`; deployment still uses `php artisan storage:link`. No remote or copyrighted images were downloaded.

## 8. Search Coverage

Several articles contain the keyword `عطر`; `/blog?search=عطر` returns multiple public results. `عبارت-ناموجود-تست` remains suitable for empty-state verification.

## 9. Category Coverage

Published distribution is 4 / 4 / 4 / 3 / 3 across the five categories. `/blog?category=عطر-و-ادکلن` returns the four perfume articles.

## 10. Related Article Coverage

Every category has at least three published articles, so the existing same-category related query returns meaningful articles on detail pages.

## 11. Unicode Slug Verification

`/blog/چطور-عطر-مناسب-خودمان-را-انتخاب-کنیم` returned HTTP 200 with the article and related section.

## 12. Development Run 1

Successful reconciliation after implementation: categories reused 5, tags reused 10, public Posts created 16 and reused 2 (the two records were created during the initial safe command debugging attempt), draft controls created 1, scheduled controls created 2, images created 17 and reused 1. Final development state was 18 published, 2 scheduled, and 1 draft demo Post.

## 13. Development Run 2

`php artisan demo:storefront-blog` completed successfully with public Posts reused 18, draft controls reused 1, scheduled controls reused 2, and images reused 18. Database counts and deterministic file paths remained unchanged; no duplicate categories, tags, Posts, or demo image files were added.

## 14. Storefront Runtime Verification

`/blog`: HTTP 200 with public demo articles; future scheduled and draft controls absent.

Page 2: HTTP 200 and populated through the existing 9-per-page paginator.

Category: `/blog?category=عطر-و-ادکلن` returned perfume articles.

Search: `/blog?search=عطر` returned multiple results.

Article: perfume, skincare, and the Persian-slug article detail routes returned HTTP 200.

Placeholder: the no-image article `لوازم-آرایشی-ضروری-برای-یک-کیف-روزمره` rendered `جای تصویر اصلی مقاله`.

Related: article detail pages rendered `مطالب مرتبط` with same-category published articles.

## 15. Tests

`tests/Feature/Console/StorefrontDemoBlogCommandTest.php`: 2 passed, 29 assertions; run independently twice.

## 16. Blog Regression

`tests/Feature/Storefront/BlogTest.php`: 2 passed, 20 assertions.

Blog/Post and Filament Blog regressions: 11 passed, 77 assertions.

## 17. Full Suite

Final independent full isolated run: **323 passed, 2,059 assertions, 0 failures, 0 skipped** (62.74s). An earlier run exposed a transient order-sensitive ProductDelete media failure; that focused test passed independently and the clean rerun completed without failures. No Blog code or test failure remained.

## 18. Migration

No schema changes were introduced. Development migrations were not reset; the command only added/reconciled its own demo records.

## 19. Pint

`vendor/bin/pint app/Console/Commands/CreateStorefrontDemoBlog.php tests/Feature/Console/StorefrontDemoBlogCommandTest.php`: passed.

## 20. git diff --check

Passed with no whitespace errors.

## 21. Files Changed

Created:

* `app/Console/Commands/CreateStorefrontDemoBlog.php`
* `tests/Feature/Console/StorefrontDemoBlogCommandTest.php`
* `STOREFRONT_DEMO_BLOG_2026-09-04.md`

## 22. Raw Frontend Source

`D:\uni-shop-project\front` unchanged.

## 23. Safety

* `ecommerce` was not reset.
* Existing non-demo Posts were not deleted or overwritten; conflicting slugs fail safely.
* The command refuses production environments.
* No external images were downloaded.
* No comments, Product relations, new Blog features, or unrelated business features were added.

`STOREFRONT DEMO BLOG: VERIFIED PASS`
