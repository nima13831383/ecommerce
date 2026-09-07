# SMS.ir Real Sandbox Runtime Audit — 2026-09-05

## 1. User Observation

**Always `12345`:** Expected local/testing Sandbox policy. `CustomerOtpService::code()` deliberately uses the deterministic value only when the environment is `local` or `testing` and `sms.smsir.sandbox` is enabled. It supplies the parameter value; SMS.ir does not generate or replace it.

**Panel empty:** Expected. SMS.ir Sandbox simulates Verify sends, does not send a physical SMS or consume real credit, and does not persist normal delivery reports in the SMS.ir panel. The immediate Verify API response is the evidence. The official [SMS.ir REST API documentation](https://sms.ir/rest-api/) documents the Verify endpoint, `X-API-KEY` boundary, and unified response status/message shape.

## 2. Expected Sandbox Behavior

* Real SMS: not sent in Sandbox.
* Panel reports: not expected in Sandbox.
* API response: required and captured through the real configured adapter.

## 3. Current Runtime Settings

The safe `settings:status` and `sms:status` diagnostics reported:

| Setting | Runtime value |
| --- | --- |
| `auth.customer_auth_mode` | `sms_otp` |
| Provider | `smsir` |
| SMS.ir enabled | yes |
| Sandbox | yes |
| API key configured/decryptable | yes / yes (verified internally; never printed) |
| Effective Verify template | `123456` |
| Effective parameter | `CODE` |
| Environment | `local` |
| Gateway operational | yes |

The configured OTP settings remain five digits, with the registered TTL and resend-cooldown values read through `SettingsService`. No setting was changed for this audit.

## 4. OTP Code Source

`app/Services/Auth/CustomerOtpService.php` selects `12345` only for local/testing plus enabled Sandbox. Outside that condition it generates a numeric code with PHP `random_int()` using the configured length. A focused regression sets a six-digit production-mode configuration and proves the sender receives a six-digit non-deterministic value, not `12345`.

## 5. Actual Send Path

1. `SmsOtpAuthController::requestLogin()` / `requestRegistration()` validates the request and invokes `requestOtp()`.
2. `CustomerOtpService::request()` normalizes the mobile, checks mode/configuration, throttles/cooldown, generates the code, and calls its `OtpSenderInterface`.
3. `SmsIrOtpSender::sendVerificationCode()` loads `SmsGatewayConfiguration`, then calls `SmsIrClientFactory`.
4. The factory supplies the official `Ipe\Sdk\SmsIrService`, whose `verifySend($mobile, $templateId, $parameters)` performs the Verify request.
5. Only after a non-failing provider result does `CustomerOtpService` create the hashed `AuthOtpChallenge` in its transaction.

## 6. Real HTTP Request

One user-authorized, controlled request was made using an existing verified QA account. No physical SMS was sent.

| Evidence | Result |
| --- | --- |
| Endpoint | `https://api.sms.ir/v1/send/verify` |
| Adapter call | yes |
| Sandbox | `true` |
| Template / parameter | `123456` / `CODE` |
| Provider status | `1` (successful SDK result) |
| Masked mobile | `*******9763` |
| Provider message / message ID / cost | not exposed by the SDK result returned for this Sandbox Verify send |

The permanent redacted runtime event `smsir.verify_send_succeeded` records only provider, Sandbox flag, endpoint path, template ID, mobile suffix, and provider status. It never records the API key or plaintext OTP.

## 7. Sent-State Contract

The provider call is before the challenge transaction. A successful result is followed by persisted challenge creation; a provider exception/rejection prevents challenge creation. The controlled challenge was a login challenge with a hash present, `sent_at` and expiry present, zero attempts, and no consumption. The plaintext OTP is not stored.

## 8. Bug Findings

`SMSIR-SANDBOX-BUG-001` — **Low severity**

* **Input:** successful OTP request redirect.
* **Expected:** Persian customer message.
* **Actual:** the internal flash key `otp-sent` was rendered literally.
* **Root cause:** login view displayed arbitrary `session('status')` text.
* **Fix:** map the known `otp-sent` status to `کد تأیید ارسال شد.` in the auth views. The message is only set after `CustomerOtpService::request()` succeeds.

## 9. `otp-sent`

It was an internal flash-status key, not a provider response or intended customer copy. It is no longer rendered. Login runtime coverage asserts the Persian success copy appears and `otp-sent` does not.

## 10. Provider Failure Behavior

Missing/incomplete configuration fails closed through `SmsGatewayConfiguration`; the controller returns a safe validation error and does not set the OTP-session state. SDK exceptions are translated to the safe Persian message `سرویس پیامک موقتاً در دسترس نیست.` and produce a redacted warning event. Focused tests prove provider failure creates no challenge and renders neither sent copy nor local Sandbox helper.

## 11. Database Challenge

The one controlled request created a normal, non-destructive `AuthOtpChallenge`:

* normalized mobile (only suffix `9763` retained in audit evidence)
* purpose `login`
* hash present; no plaintext code stored
* `sent_at` and expiry present
* attempts `0`
* unconsumed

The challenge was created only after the successful provider result.

## 12. Browser QA

**NOT VERIFIED in this focused audit.** Playwright infrastructure exists, but the requested one-request limit was used for direct adapter/runtime evidence. A browser form flow would require a second provider request to establish its browser session. PHP HTTP tests verify the rendered helper and successful verification flow without issuing another real Sandbox request.

## 13. Focused Tests

* `tests/Feature/Auth/SmsOtpAuthenticationTest.php`
* `tests/Feature/Settings/SmsOtpSettingsTest.php`

Initial focused run: **15 passed, 105 assertions**.

Auth and Settings regression run: **53 passed, 310 assertions**.

Coverage includes deterministic Sandbox code scope, production random-code path, successful verification, missing/incomplete configuration, provider failure without sent state, template/parameter SDK contract, secret masking, and the corrected customer text.

## 14. Full Suite

`php artisan test --compact`: **409 passed, 2693 assertions, 0 failures, 0 skipped**.

Pest emitted its existing non-test-affecting result-cache permission warning for `vendor/pestphp/pest/.temp/test-results`; the suite exit result was successful.

## 15. Database Safety

No destructive database command ran. `php artisan migrate` reported `Nothing to migrate`; `migrate:status` shows all migrations ran. The one normal hashed OTP challenge is the only audit data mutation. Site Settings were unchanged.

## 16. Raw Frontend

`D:\uni-shop-project\front` was not modified.

## 17. Final Verdict

The empty SMS.ir panel is expected for Sandbox. The application made the real Verify request through the official SDK, received a successful provider status before persisting a usable challenge, and exposes no successful OTP UI state after provider failure.

`SMS.IR REAL SANDBOX: VERIFIED PASS`
