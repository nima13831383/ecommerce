# Storefront UI, Settings, and Persian-Digit Polish — 2026-09-05

## 1. New Site Settings

| Key | Group | Default | Validation |
| --- | --- | ---: | --- |
| `catalog.products_per_page` | `catalog` | 10 | required integer, 1–100 |
| `blog.posts_per_page` | `blog` | 10 | required integer, 1–100 |

Both are registered core settings, have Persian labels and descriptions, persist through the existing settings contract, and are edited as values only in Filament Site Settings.

## 2. Settings Migration

Migration: `2026_09_05_120028_add_storefront_pagination_core_settings`.

It inserts only missing rows with `insertOrIgnore`; it never overwrites an operator-configured value. Fresh isolated migrations create both settings, and focused tests prove defaults, typed `SettingsService` values, validation, and preservation across sync.

## 3. Product Pagination

`Storefront\ProductController` now injects `SettingsService` and uses `catalog.products_per_page`; the request cannot override this server-side archive limit. The raw category pagination variant uses `.category-pagination`, Persian visible page digits, previous/next and disabled states, while URLs remain ASCII. Product filter/sort state is retained by paginator URLs; the existing AJAX progressive enhancement intercepts only pagination clicks and preserves normal links as fallback.

## 4. Blog Pagination

`Storefront\BlogController` now obtains its limit from `blog.posts_per_page`. The distinct raw blog variant uses `.article-pagination` and its active-page class; it stays normal SSR navigation and retains query strings.

## 5. Mobile Filter Drawer

Root cause: the Blade archive retained an inert desktop filter form, but the mobile trigger had no instantiated drawer or lifecycle.

Fix: the page script creates a raw-class-compatible drawer from the actual form, including RTL dialog state, backdrop, close/Escape/resize handling, body lock, focus restoration, synchronized control values, auto-applied AJAX filters, History API updates, and progressive links. Reset now synchronizes the reset server form into the cloned drawer, preventing stale selections. Browser checks cover 430, 390, 375, and 320 px widths, plus drawer accordion, backdrop and Escape closing.

## 6. Header Oscillation

Root cause: competing scroll listeners/direction-derived transitions could toggle visibility around a moving header layout threshold.

Fix: one passive scroll listener and stable hysteresis (`collapseAt: 72`, `expandAt: 24`) control only visual row classes. Browser QA holds 67, 72, and 77 px positions across repeated frames and confirms stable state.

## 7. Persian Digits Architecture

`App\Support\PersianNumber` is the centralized presentation formatter for digits, integers, money and percentages. `JalaliDate::format()` now Persianizes human display, while picker values remain canonical ASCII for safe inputs. Storefront cards, cart, checkout, orders, payment result, header counts and pagination use it; key Filament table/infolist/presentation paths use it too.

Machine values intentionally remain ASCII: routes, URLs/query strings, IDs, SKU/technical identifiers, API payloads, numerical form submissions and service calculations. Editable numeric controls retain canonical input semantics.

## 8. Auth Header

The shared global header is now rendered for storefront auth pages; the global footer is intentionally suppressed. Guest/auth header state remains supplied by the existing shared partial.

## 9. Password Eye Icons

Login, registration and reset password controls use the raw template's inline `#i-eye` SVG symbol. One accessible jQuery initializer toggles visibility without duplicate binding.

## 10. Auth Mobile Spacing

The auth page has responsive breathing room below the now-global header and preserves the raw mobile background/card layout. A broken background URL was corrected from the storefront root to the actual copied asset path under `public/storefront/assets`, eliminating the browser 404.

## 11. Auth Trust Section

Current requirement: **REMOVED by user request.** The section was removed once from the shared auth layout; no empty trust container remains. The global header remains, the global footer remains absent, and mobile auth spacing was revalidated. This is an intentional approved divergence from the raw template.

## 12. About Page

The page now follows the raw section structure: breadcrumb, hero/copy, CTA, story blocks and four trust/service cards. The shared header marks About active.

## 13. FAQ Page

The page now follows the raw FAQ structure: breadcrumb, title/intro, category controls, search shell, accordion items and CTA. The existing raw-compatible FAQ script supplies accessible accordion state; the shared header marks FAQ active.

## 14. Playwright QA

Real Chromium Playwright was added as a development dependency and run through `playwright.config.js`.

- Desktop 1440 and tablet 768: sticky state at 67/72/77 px, product pagination, blog pagination, auth header/form/eye icon, absent trust/footer, About and FAQ.
- Mobile 430, 390, 375 and 320 px: product filter drawer state, body lock, accordion, auto-AJAX filter URL/pagination state, backdrop/close behavior, and auth header/form/spacing with absent trust/footer.
- Result after the user-directed auth-layout change: **11 passed, 7 intentionally project-scoped skips** in 15.8 seconds.
- Captured console, page and request failures: none.

## 15. Focused Tests

Post-change focused verification:

- Auth/Breeze feature tests: **13 passed, 55 assertions**.
- Storefront feature suite: **66 passed, 546 assertions**.

## 16. Full Suite

**364 passed, 2,343 assertions, 0 failures, 0 skipped** — 75.55 seconds.

## 17. Settings Status

```text
Registered: 8
Persisted: 8
Missing: none
Unknown: none
Needs configuration: none
```

## 18. Migration Status

The additive migration ran once. A subsequent `php artisan migrate` reported `Nothing to migrate`; `migrate:status` shows the new migration as ran in batch 3.

## 19. Pre-Migration Backup

`D:\uni-shop-project\db\backups\ecommerce_2026-09-05_120019.sql`

## 20. Post-Fix Backup

`D:\uni-shop-project\db\backups\ecommerce_2026-09-05_121859.sql`

## 21. Database Safety

Only normal additive migration/status/settings checks were run against `ecommerce`. No reset, wipe, truncate, destructive seed, drop, or schema recreation was used. Automated tests ran in the isolated test environment.

## 22. Files Changed

Focused production changes include the two setting definitions, additive migration, SettingsService-consuming archive controllers, Filament setting form/table presentation, `PersianNumber`, Jalali presentation, raw-compatible storefront Blade/CSS/JS parity fixes, and the Playwright harness. Focused feature and browser tests were added/updated.

Pre-existing unrelated demo catalog changes were preserved without modification.

## 23. Raw Frontend

`D:\uni-shop-project\front` was read as the visual/interactions source and remains unchanged.

## 24. Remaining Limitations

None within this focused settings/parity scope. Numeric editable inputs intentionally preserve canonical machine submission semantics; their presentation is not a second numeric-parsing system.

`STOREFRONT UI + SETTINGS POLISH: VERIFIED PASS`
