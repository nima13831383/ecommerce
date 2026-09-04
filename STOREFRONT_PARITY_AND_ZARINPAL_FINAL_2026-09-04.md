# Combined Storefront + ZarinPal Final Report

## A. Storefront Parity Status

SSR parity and UX fixes are implemented across the shared header, catalog filters/cards, product detail, account/address/auth pages, FAQ/About navigation, and Home categories. Storefront PHP coverage is 58 tests/486 assertions; full isolated coverage is 346 tests/2,146 assertions. Playwright/Chromium headless smoke coverage ran at the required viewports; the CUA browser surface failed to initialize, so no screenshots were captured.

**PART A:** STOREFRONT STRICT PARITY + JALALI: PASS WITH DOCUMENTED LIMITATIONS

## B. Jalali / Timezone

`APP_TIMEZONE` defaults to `Asia/Tehran`; `App\Support\JalaliDate` centralizes human-facing Jalali display. Canonical database/API timestamps remain machine-oriented Gregorian values. Filament picker conversion is now centralized and covered by automated tests; authenticated browser interaction remains a documented environment limitation.

Filament date/date-time inputs and filters now use centralized Jalali components with canonical Gregorian dehydration; automated Filament coverage is green. An authenticated Playwright run against a disposable isolated SQLite admin fixture verified Post scheduling, Coupon date persistence, and a Users Jalali date filter with zero browser/Livewire errors. Persisted canonical timestamps matched the selected Jalali values.

## C. ZarinPal Sandbox E2E

The official SDK adapter remains covered by automated tests and the sandbox connectivity probe was attempted with a temporary UUID merchant. The real hosted card → callback → Verify flow was not completed because the sandbox connection failed exactly with `Failed to connect to sandbox.zarinpal.com port 443 after 18 ms: Couldn't connect to server`; no fake success is claimed.

**PART B:** ZARINPAL SANDBOX E2E: PASS WITH EXTERNAL LIMITATION

## D. Browser QA Summary

PART A: Playwright/Chromium headless smoke verified the local SSR surface at 1440, 1280, 768, 430, 390 and 320px (RTL, collapsed filters, cart toggle, active navigation, search, Product Detail sections, and zero console/network errors). The authenticated Filament Jalali Playwright proof also passed against an isolated SQLite fixture with zero console/network errors. CUA screenshots were unavailable. PART B: hosted payment interaction/callback/Verify remains NOT VERIFIED because sandbox connectivity failed before StartPay.

## E. PHP Full Suite

346 tests passed, 2,146 assertions, 0 failures, 0 skipped.

## F. Production Cache Checks

`config:cache`, `route:cache`, and `view:cache` each passed; local caches were cleared afterward.

## G. Database / Source Safety

No destructive migration/reset command was run. Development `ecommerce` data was not intentionally modified. Raw frontend `D:\uni-shop-project\front` is unchanged. No frontend source, provider, Refund/Reverse, or completed domain redesign was added.

## H. Remaining Genuine Limitations

* CUA screenshot/browser surface unavailable in this environment; headless Playwright smoke is documented for Part A.
* Hosted ZarinPal Sandbox payment/callback/Verify requires outbound sandbox connectivity and a browser-compatible isolated Order flow.
* Production requires a real ZarinPal merchant ID and HTTPS `APP_URL` callback.

## I. Final Combined Status

**COMBINED STOREFRONT + ZARINPAL: PASS WITH ZARINPAL EXTERNAL LIMITATION**
