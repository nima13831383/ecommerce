# SMS.ir OTP Authentication + Core Site Settings

## 1. Previous Auth Architecture

The storefront used Laravel Breeze `web` session authentication with email/password login, registration, password reset, CSRF-protected forms, and Persian Blade auth pages.

## 2. Auth Mode Setting

`auth.customer_auth_mode` is a persisted Core Site Setting with `email_password` (default) and `sms_otp` options. Switching modes selects the active storefront path only; it does not mutate existing credentials.

## 3. SMS Settings

| Key | Type | Default | Secret | Purpose |
| --- | --- | --- | --- | --- |
| `sms.default_provider` | string | `smsir` | No | Active provider alias |
| `sms.smsir.enabled` | boolean | `false` | No | Provider activation |
| `sms.smsir.sandbox` | boolean | `true` | No | Sandbox mode |
| `sms.smsir.api_key` | string | null | Yes | Encrypted current-mode SMS.ir API key |
| `sms.smsir.verify_template_id` | integer | null | No | Production Verify template |
| `sms.smsir.verify_parameter_name` | string | `CODE` | No | Production Verify parameter |

OTP settings are `auth.otp.code_length=5`, `ttl_seconds=120`, `resend_cooldown_seconds=60`, and `max_attempts=5`, with strict registry validation.

## 4. Settings Migration

`2026_09_05_180000_add_sms_otp_core_settings.php` is additive and inserts missing rows only. Existing settings and values are preserved.

## 5. User Schema

The existing unique nullable `users.mobile` and `mobile_verified_at` fields were reused. Email and password are nullable to support genuinely passwordless SMS accounts; existing email/password accounts remain unchanged. The canonical customer format remains the project-established `09XXXXXXXXX`; Persian/Arabic digits, `98...`, and `+98...` inputs normalize before storage/comparison.

## 6. Official SMS.ir Package

Installed `ipe/smsir-php v1.0.1`. `SmsIrOtpSender` uses a project-owned factory to construct `Ipe\Sdk\SmsIrService` with the decrypted Site Settings key and `https://api.sms.ir/v1/`. The package singleton/Facade is intentionally not runtime authority because it reads `SMSIR_API_KEY` from the environment.

## 7. Sandbox and Production

Sandbox uses the same API URI, a Sandbox-specific key, template `123456`, and parameter `CODE`; production template configuration is inactive while Sandbox is enabled. In local/testing only, the deterministic QA code is `12345`, still hashed in persistence. Production Sandbox is rejected/fails closed and can never expose or use the deterministic code.

## 8. OTP Architecture

`auth_otp_challenges` stores ULID, normalized mobile, purpose, hash, expiry, attempts, max attempts, sent, consumed, and invalidated timestamps. Challenges are purpose-bound (`login`/`register`), single-use, expire without grace, increment failed attempts atomically, and invalidate prior usable challenges after a successful resend. Plaintext codes are not persisted or logged.

## 9. Login and Registration

SMS login sends an OTP only for an existing verified mobile and authenticates only after successful verification/session regeneration. Unknown mobiles do not create users. SMS registration stores profile input in the session, creates the permanent user only after registration OTP verification, sets `mobile_verified_at`, and relies on the database unique constraint for duplicate safety. Email/password Breeze and password-reset routes remain intact in email mode.

## 10. Filament and Diagnostics

The value-only Settings UI now recognizes Auth/SMS records, masks the API key, preserves blank secret edits, and displays Sandbox guidance. `settings:status` and new `sms:status` report safe operational state without credentials.

## 11. Bug Found and Fixed

`SMSIR-OTP-BUG-001` — **Medium** — the initial OTP migration used multiple non-null MySQL `TIMESTAMP` fields, which this local MySQL configuration rejected for `sent_at`. No OTP table was created. The unapplied migration was corrected to portable `DATETIME` timestamp fields, then migrated successfully. A pre-migration database backup existed before the attempt.

## 12. Validation Evidence

- Focused Auth/Settings/storefront auth regression: **53 passed, 333 assertions**.
- Final focused Email/SMS Auth and Settings regression: **19 passed, 107 assertions**.
- Full isolated suite: **407 passed, 2,683 assertions; 0 failures; 0 skipped**.
- Development migration: completed; follow-up `php artisan migrate` reported `Nothing to migrate`.
- `settings:status`: 23 registered/persisted, no missing/unknown entries; email/password mode remains selected and SMS is disabled.
- `sms:status`: Sandbox template `123456` and `CODE`, no configured API key, non-operational as expected.

## 13. Real Sandbox and Browser QA

`SMS.IR REAL SANDBOX: BLOCKED — API KEY REQUIRED`

No SMS.ir Sandbox credential was supplied or invented. Adapter and browser-facing application behavior are isolated-test covered with a test double. Real Playwright SMS flows were not run because the required actual Sandbox key is unavailable.

## 14. Safety

Development `ecommerce` was backed up before migration at `D:\uni-shop-project\db\backups\ecommerce_2026-09-05_200012.sql` and after validation at `D:\uni-shop-project\db\backups\ecommerce_2026-09-05_200632.sql`. The raw template directory `D:\uni-shop-project\front` was not modified. No real SMS was sent, no provider secret was recorded, and no destructive database command was used.
