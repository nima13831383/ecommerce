# Hero Copy Visual Position Correction — 2026-09-06

## 1. Root Cause

Yes: RTL logical-position semantics caused the visual reversal. The prior desktop rules used `inset-inline-end` for slides 1/3 and `inset-inline-start` for slide 2. In an RTL document those logical directions mapped opposite to the artwork-based screen-coordinate requirement, placing slides 1/3 copy on the physical left and slide 2 copy on the physical right.

The correction uses physical `right` and `left` anchors only for the three art-directed desktop slide rules. No Hero artwork, slide order, or gesture implementation changed.

## 2. Slide 1

Main subject side: physical left (perfume bottle, flowers, and fabric).

Previous copy side: physical left, covering the artwork subject region.

Final copy side: physical right, in the marble/negative-space field.

Final CSS anchor: `right: 13%; left: auto; max-width: 410px`.

## 3. Slide 2

Main subject side: physical right (dropper bottle, cream jar, and stones).

Previous copy side: physical right, over the subject.

Final copy side: physical left, in the water/negative-space field.

Final CSS anchor: `left: 13%; right: auto; max-width: 410px`.

## 4. Slide 3

Main subject side: physical left (accessories, perfume, lipstick, and flowers).

Previous copy side: physical left, over the artwork subject.

Final copy side: physical right, in the marble/negative-space field.

Final CSS anchor: `right: 12%; left: auto; max-width: 390px`.

## 5. Desktop Screenshots

Real after screenshots were captured and manually inspected for all three slides:

* 1536×1024: `C:\Users\nima\.codex\visualizations\2026\09\03\01a06603-a821-7bb2-8ed0-0680f98bafbe\hero-copy-position-after\slide-1.png`, `slide-2.png`, `slide-3.png`.
* 1440×900: `C:\Users\nima\.codex\visualizations\2026\09\03\01a06603-a821-7bb2-8ed0-0680f98bafbe\hero-copy-position-responsive\desktop-1440-slide-1.png`, `slide-2.png`, `slide-3.png`.
* 1920×1080: `C:\Users\nima\.codex\visualizations\2026\09\03\01a06603-a821-7bb2-8ed0-0680f98bafbe\hero-copy-position-responsive\desktop-1920-slide-1.png`, `slide-2.png`, `slide-3.png`.

Visual review result: Slide 1 copy is right of the product subject; Slide 2 copy is left of the product subject; Slide 3 copy is right of the product subject. The copy has a clear content-padding zone before its nearest arrow in every screenshot.

## 6. Tablet QA

At 1024×768, all three slides retain the desktop art direction without crowding: slides 1/3 copy remains physically right and slide 2 copy remains physically left. The corresponding screenshots are in `hero-copy-position-responsive\tablet-1024-slide-1.png` through `slide-3.png`. No horizontal overflow occurred.

## 7. Mobile Regression

At 390×844 and 430×932, the existing `max-width: 600px` rules keep all copy blocks `position: relative` and centered over the dedicated mobile images. The desktop physical offsets do not leak into mobile. Screenshots for all three slides at both widths were captured under `hero-copy-position-responsive\mobile-390-*` and `mobile-430-*`; manual review found no overflow or clipping.

## 8. Gesture Regression

Touch: verified by the narrow Playwright mobile test; horizontal swipe advances, while a vertical-dominant swipe does not.

Trackpad: existing horizontal wheel test remains green, including threshold, one-slide behavior, reverse gesture, and vertical-wheel non-interference.

Vertical scroll: remains protected by the unchanged touch/wheel direction checks.

Arrows: verified by the mobile interaction regression.

Dots: verified by the mobile interaction regression and the visual-coordinate test.

## 9. Visual Coordinate Browser Test

Added a Playwright regression that uses rendered `getBoundingClientRect()` screen coordinates rather than CSS classes or RTL logical directions. It runs at 1440, 1536, and 1920 widths and asserts:

| Slide | Required physical side | 1536px copy center X | 1536px Hero center X |
| --- | --- | ---: | ---: |
| 1 | right | 1126.10 | 768.00 |
| 2 | left | 370.76 | 768.00 |
| 3 | right | 1177.14 | 768.00 |

The same test also requires a 24px-or-greater clearance from the adjacent visual arrow.

## 10. Focused Tests

* `php artisan test --compact tests/Feature/Storefront/HomePageTest.php`: **5 passed, 40 assertions, 0 failures**.
* New desktop coordinate browser test: **1 passed**.
* New mobile arrow/dot/touch state browser test: **1 passed**.
* Complete `storefront-polish.spec.js` matrix (desktop, tablet, 390px mobile, 430px mobile): **10 passed, 14 intentional project-scope skips, 0 failures**.

## 11. Full Suite

`php artisan test --compact`: **456 passed, 2,925 assertions, 0 failures, 0 skipped** (96.59 seconds).

Pest emitted its existing non-fatal result-cache permission warning after the successful run; the test process exited with code 0.

## 12. Raw Frontend

`D:\uni-shop-project\front` was not modified.

## Files Changed

* `public/storefront/assets/css/homepage/hero.css`
* `tests/Browser/storefront-polish.spec.js`
* `HERO_COPY_VISUAL_POSITION_CORRECTION_2026-09-06.md`

## Quality

`vendor/bin/pint --dirty`: passed.

`git diff --check`: passed.

## Final Status

`HERO COPY ART-DIRECTION: VERIFIED PASS`
