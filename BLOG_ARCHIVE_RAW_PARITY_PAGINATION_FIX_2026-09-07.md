# Blog Archive Raw Parity + Pagination Fix — 2026-09-07

## 1. Blog Visual Root Cause

The raw archive uses `h3` for regular article-card titles. The Laravel partial used `h2`, which inherited the global larger heading scale and made cards visibly heavier. The Laravel archive also omitted the featured article’s date metadata. Finally, cached paginator instances were retaining Laravel’s default `/` path when they had been built outside the `/blog` request, so generated page links could point to the homepage.

## 2. Raw CSS Source

The authoritative files are `D:\uni-shop-project\front\blog.html`, `assets/css/blog/layout.css`, `assets/css/blog/responsive.css`, and `assets/css/components/public-page.css`.

Raw card rules retained in the Laravel assets are:

* title: `.article-card h3`, `font-size: .82rem`, `line-height: 1.8`, `margin: 9px 0 6px`;
* excerpt: `.article-excerpt`, `.73rem`, line-height `2`, muted color;
* badge: `.article-badge`, `.62rem`, `4px 9px`, pill radius;
* metadata: `.article-meta`, `.62rem`, `12px` gap;
* read-more: `.article-link`, `.7rem`, weight `700`, top margin `14px`;
* card: 3-column desktop grid, `16px` gap, 14px body padding, 14px radius, 16:10 media ratio;
* responsive: 2 columns through 900px and 1 column through 520px, with the raw compact typography preserved.

## 3. Laravel Before / After

The shared Blog card now uses semantic `<h3>` markup, so the existing raw `.article-card h3` rule applies exactly. The featured article now renders its real Jalali publication date. No demo article data was copied into the application.

## 4. Featured Article Parity

The existing dynamic featured structure remains a two-column `featured-article` with raw badge, title, excerpt, metadata, CTA, and media classes. Real Post data and the existing public-image disk are still used. Unsupported reading-time data is not fabricated.

## 5. Desktop Visual QA

Compared raw `blog-1440.png` with Laravel browser captures at 1536×1024. The breadcrumb-to-intro spacing, compact card typography, badge sizing, grid gaps, featured proportions, RTL ordering, and navigation structure follow the raw layout. Additional browser geometry was checked at 1440, 1536, and 1920 widths through the existing storefront browser suite.

## 6. Mobile Visual QA

Compared raw `blog-390.png` with the Laravel 390×844 capture. The breadcrumb sits at the same post-header spacing, the intro and category pills retain the responsive flow, the featured article stacks media above copy, and article-card typography remains compact. The layout has no horizontal overflow. The 430px responsive target is covered by the browser project suite.

## 7. Breadcrumb Root Cause (Addendum)

The raw Blog page uses the shared `public-page` wrapper and `.public-breadcrumb` with a 28px desktop / 20px mobile content offset, 24px / 20px bottom spacing, `.72rem` typography, muted text, and an inline `/` separator. The Blog view already had the correct semantic order (`خانه / وبلاگ`) but the Blog page did not load `components/public-page.css`, so the wrapper spacing was absent. This produced the breadcrumb-to-hero mismatch.

## 8. Laravel Breadcrumb Fix

The Blog archive and detail views now load the existing `public-page.css` component. The markup remains:

```html
<div class="public-breadcrumb"><a href="/">خانه</a><span>/</span><span>وبلاگ</span></div>
```

The Home URL is generated with `route('storefront.home')`; no static path was introduced.

## 9. Pagination Root Cause and Fix

`StorefrontBlogQuery::paginate()` cached the complete paginator object. When a paginator was built by a cache warmer or another context, its default path could be `/`. The archive now reapplies the canonical `storefront.blog.index` route path and appends only the current request’s non-page query parameters after cache retrieval. Page 2 therefore generates `/blog?page=2` (and preserves `category`/`search` filters) without changing cache keys, TTL, generations, SWR, or refresh-ahead behavior.

## 10. Browser Pagination QA

The browser test opens `/blog`, verifies the page-2 link remains under `/blog`, clicks it, verifies `/blog?page=2`, confirms the Blog heading remains and the Home hero is absent, checks the active-page marker, and checks no horizontal overflow or console/page/network failures. Final single-worker result: **25 passed, 29 expected project-scoped skips, 0 failures** in the complete `storefront-polish.spec.js` run. An earlier parallel run had one transient mobile-project failure; that case passed independently and the deterministic final run was fully green.

## 11. Focused Feature and Cache Tests

`tests/Feature/Storefront/BlogTest.php`: **4 passed, 28 assertions** (run independently twice). Blog cache/query and targeted refresh regressions: **27 passed, 101 assertions**.

## 12. Full Suite

The complete isolated Laravel suite passed: **458 tests, 2,933 assertions, 0 failures**. Pest emitted the existing non-fatal result-cache permission warning after completion.

## 13. Quality and Safety

`vendor/bin/pint --dirty`: passed. `git diff --check`: passed. No migration or destructive database command was run. The raw source at `D:\uni-shop-project\front` remains unchanged.

## 14. Files Changed

* `app/Services/Blog/StorefrontBlogQuery.php`
* `resources/views/storefront/blog/index.blade.php`
* `resources/views/storefront/blog/show.blade.php`
* `resources/views/storefront/components/blog-card.blade.php`
* `tests/Feature/Storefront/BlogTest.php`
* `tests/Browser/storefront-polish.spec.js`

BLOG ARCHIVE RAW PARITY + PAGINATION: VERIFIED PASS

BLOG BREADCRUMB RAW PARITY: VERIFIED PASS
