# Storefront API Phase 2A — Customer Authentication Boundary

## 1. Frontend Inspection

The authoritative local frontend/template directory is `D:\uni-shop-project\front`. It is a separate static HTML/Tailwind/jQuery template (`index.html`, `login.html`, `register.html`, account pages, and `package.json` scripts), not a Laravel Blade, React, or Vue application. Its authentication forms are visual-only and currently have no backend URL, API client, or Laravel integration. No frontend files were changed.

The backend boundary therefore remains compatible with the existing Laravel/Breeze web-session architecture. A future same-site Blade/SSR adapter can submit the forms with the Laravel session cookie and CSRF token; a separate-origin deployment must explicitly configure its HTTP origin, cookies, CORS, and CSRF delivery before being enabled. The template's extra visual fields (for example, phone input) are not silently added to this contract; they require a later domain/API decision.

## 2. Chosen Authentication Strategy

**Same-origin/session-cookie authentication using Laravel's existing `web` guard.**

Evidence: Breeze is installed and already owns registration, login, logout, password reset, email verification, and profile behavior; `config/auth.php` defines only the `web` session guard; Sanctum/token authentication is not installed; and the frontend is currently a static visual template without a separate-origin API client. No second credential system was introduced.

## 3. Guard / Middleware

Customer auth routes use the `web` middleware group (session + CSRF) and `auth:web` for authenticated operations. Product catalog routes remain public and unchanged. Future customer-owned APIs should use this same first-party session boundary rather than mixing guards.

Unauthenticated `/api/v1/*` requests are rendered as JSON `401` with code `unauthenticated`, never as an HTML login redirect.

## 4. Routes

| Method | URI | Middleware | Response |
| --- | --- | --- | --- |
| POST | `/api/v1/auth/register` | `web`, `guest` | `201`, `{ data: customer }` |
| POST | `/api/v1/auth/login` | `web`, `guest` | `200`, `{ data: customer }` |
| POST | `/api/v1/auth/logout` | `web`, `auth:web` | `200`, `{ data: null }` |
| GET | `/api/v1/auth/me` | `web`, `auth:web` | `200`, `{ data: customer }` |

The existing Breeze web routes for password reset, email verification, and profile remain authoritative and were not duplicated.

## 5. Customer Resource

`CustomerResource` exposes only `id`, `name`, `email`, and boolean `email_verified`. It excludes passwords, remember tokens, roles, permissions, deleted state, and admin metadata, including when an admin account uses the customer endpoint.

## 6. CSRF

Register, login, and logout run through the `web` middleware group, retaining Laravel's CSRF protection in non-test runtime. The API exception boundary maps a CSRF mismatch to HTTP `419` with code `csrf_token_mismatch`; CSRF was not globally disabled. Same-site Blade clients should submit the session CSRF token. Separate-origin clients require an explicitly configured first-party cookie/CSRF setup before deployment.

## 7. CORS

There is currently no `config/cors.php`, and no permissive wildcard credentialed CORS policy was added. If the static template is later served from another HTTP origin, add an environment-configured allow-list and coordinate it with cookie/SameSite/CSRF settings; the filesystem path `D:\uni-shop-project\front` is not an origin and was not hardcoded.

## 8. Cookie / Session Configuration

The current `config/session.php` uses the environment-configurable database session driver, HTTP-only cookies, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` (default `lax`), and `SESSION_PARTITIONED_COOKIE`. Separate-origin production must set the real HTTPS origin/domain, secure cookies, an appropriate SameSite policy, trusted CORS origin(s), and CSRF/token delivery consistently. No production hostname was assumed.

## 9. Login / Rate Limiting

`ApiLoginRequest` extends the existing Breeze `LoginRequest`, preserving its validation, session regeneration path, and five-attempt `RateLimiter` throttle. Invalid credentials return HTTP `422` code `invalid_credentials`; throttled attempts return HTTP `429` code `rate_limited`. Passwords and hashes are never serialized.

## 10. Register

`ApiRegisterRequest` applies the existing Breeze registration rules (including lowercase/unique email and the configured password rule), creates the User through the existing model, dispatches `Registered`, logs in through `web`, regenerates the session, and returns the public customer resource with HTTP `201`.

## 11. Logout

Logout calls the `web` guard, invalidates the session, regenerates the CSRF token, and returns JSON. A subsequent `/auth/me` request is unauthenticated.

## 12. Current Customer

`GET /api/v1/auth/me` returns the authenticated `web` user through `CustomerResource`; unauthenticated access returns the shared JSON `401` error.

## 13. Soft-Deleted User Behavior

`User` uses `SoftDeletes`; the Eloquent provider's normal query excludes trashed records. Runtime coverage confirms a soft-deleted customer's login is rejected with the same generic `invalid_credentials` response and does not reveal account existence.

## 14. Existing Breeze Compatibility

The existing Breeze authentication, registration, password reset, email verification, password confirmation/update, and profile tests remain green. The User model does not implement `MustVerifyEmail` in the current repository, so the new identity boundary does not invent an email-verification requirement for future commerce APIs.

## 15. HTTP Runtime Tests

`tests/Feature/Api/V1/Auth/CustomerAuthenticationApiTest.php` — **10 tests, 61 assertions, 0 failures, 0 skipped**.

Coverage includes registration success/validation, login/session regeneration, invalid credentials, retained Breeze throttling, authenticated `me`, unauthenticated JSON `401`, logout invalidation, soft-deleted login rejection, sensitive-field exclusion (including role-bearing admin users), and route middleware/CSRF boundary evidence.

Existing Breeze regression run (`tests/Feature/Auth` and `tests/Feature/ProfileTest.php`) — **23 tests, 61 assertions, 0 failures**.

## 16. Full Isolated Suite

**262 tests, 1,481 assertions, 0 failures, 0 skipped.** Pest emitted only its known non-fatal result-cache warning (`vendor/pestphp/pest/.temp/test-results` permission denied).

## 17. Migration

`php artisan migrate` reported **Nothing to migrate**. `php artisan migrate:status` reported every migration **Ran**.

## 18. Pint

`vendor/bin/pint --dirty` completed successfully and formatted the new API imports/style.

## 19. git diff --check

`git diff --check` completed cleanly.

## 20. Production Files Changed

- `app/Http/Controllers/Api/V1/Auth/CustomerAuthController.php`
- `app/Http/Requests/Api/V1/Auth/ApiLoginRequest.php`
- `app/Http/Requests/Api/V1/Auth/ApiRegisterRequest.php`
- `app/Http/Resources/Api/V1/CustomerResource.php`
- `bootstrap/app.php` (JSON unauthenticated/CSRF API errors)
- `routes/api.php` (customer auth routes)

## 21. Frontend Files Changed

`None`

## 22. Phase 2B Prerequisites

The customer identity boundary is ready to provide authenticated ownership for future Cart, Address, Checkout, and Order APIs. Those APIs still need to be implemented and tested in Phase 2B; no customer commerce mutation was added here.

## 23. Safety

- `ecommerce` was not reset or destructively modified.
- Development data was preserved.
- `D:\uni-shop-project\front` was unchanged.
- No real payment, SMS, or email provider was added.
- No Cart, Address, Checkout, or other Phase 2B endpoint was implemented.
- Filament/admin authentication and authorization were unchanged.

`STOREFRONT API PHASE 2A AUTH: VERIFIED PASS`
