# SMS OTP Change Mobile + Resend Timer — 2026-09-05

## 1. Change Mobile Bug

**Root cause:** the login Blade view selected the OTP step whenever trusted session key auth.otp.login_mobile existed. The former plain /login link did not mutate that session state.

**New behavior:** POST /login/otp/change-mobile invalidates the exact session-bound login challenge, clears the mobile and challenge-ID session keys, and redirects to the mobile-entry form.

## 2. Session State

Cleared keys: auth.otp.login_mobile and auth.otp.login_challenge_id. The new challenge ID is stored only after the existing CustomerOtpService succeeds.

## 3. Challenge Invalidation

The trusted challenge ID, normalized mobile, and login purpose are matched by CustomerOtpService::invalidate(). The old challenge receives invalidated_at; the old OTP cannot authenticate.

## 4. Resend Cooldown

The sole authority remains auth.otp.resend_cooldown_seconds. CustomerOtpService::resendState() calculates available_at and remaining_seconds from the current challenge's persisted sent_at.

## 5. Countdown

Initial rendering and page reload both calculate true remaining time from the server timestamp. At zero, JavaScript enables the secondary resend button without a reload. Visible values use Persian digits; JavaScript only presents the server deadline.

## 6. Resend Security

Early direct POST remains rejected by CustomerOtpService. After cooldown, the existing request path sends through the configured sender/test double, invalidates the old challenge only after sender success, records the new challenge, and restarts its timer. A provider failure leaves the current challenge intact and does not show success.

## 7. Resend UI

The resend block is separated below the primary verification CTA, uses a secondary pink ghost treatment when enabled, and muted disabled copy while waiting. Change Mobile is a keyboard-accessible CSRF-protected button below it with visible focus styling.

## 8. Mobile QA

Playwright checked the auth shell at 430px, 390px, 375px, and 320px with no horizontal overflow. The responsive OTP controls remain within the shared auth card.

## 9. Playwright

tests/Browser/storefront-polish.spec.js: **10 passed, 5 intentional skips, 0 failed**. Console/page/network failures were absent in completed tests. The generic auth visual regression supports either configured customer auth mode and did not submit an OTP request.

## 10. Focused Tests

Sms OTP and settings: **19 passed, 144 assertions**. Auth/Settings/storefront-auth regression: **60 passed, 386 assertions**.

## 11. Full Suite

Post-change isolated run: php artisan test --compact completed with **413 passed, 2732 assertions, 0 failures, and 0 skipped** in 80.08 seconds. Pest emitted its existing non-test-affecting result-cache permission warning for vendor/pestphp/pest/.temp/test-results; the suite exit code was successful.

## 12. Database

No schema change or destructive database command ran. Stateful coverage used the isolated testing environment.

## 13. Raw Frontend

D:\uni-shop-project\front remains unchanged.

## Provider Safety

REAL SMS SEND: NOT PERFORMED — USER PROHIBITED LIVE SMS DURING THIS UX PHASE

No request reached SMS.ir, no live credit was consumed, no provider-side message was created, and no real SMS-related Site Setting was changed.

SMS OTP CHANGE-MOBILE + RESEND UX: VERIFIED PASS
