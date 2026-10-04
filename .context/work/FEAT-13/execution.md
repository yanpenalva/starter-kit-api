# Execution — FEAT-13

## Progress Log

- **2026-10-04:** Worktree initialized from exact `origin/main` SHA `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe`. Isolated container image built and baseline confirmed at 57 tests, 293 assertions passing.
- **2026-10-04:** CSD work item FEAT-13 created under product/feature track with extended routing. Owning auth module documented in `.context/project/modules/auth.md`.
- **2026-10-04:** Implementation completed across all layers:
  - **Migration:** `2026_10_04_000001_add_case_insensitive_email_unique_index_to_users_table.php` applies PostgreSQL expression unique index `users_email_lower_unique ON users (lower(email))` with a fail-closed collision check on preexisting rows and a clean `down()` rollback dropping only the new index.
  - **Form Request:** `app/Http/Requests/Auth/LoginRequest.php` validates `email` and `password`, normalizes email via `mb_strtolower(trim($email))`, and returns standardized 422 errors without credential leakage.
  - **Resource:** `app/Http/Resources/Auth/MyProfileResource.php` formats authenticated user profile to approved schema `{data: {id, name, email, email_verified_at, roles: [], permissions: []}}` with RFC 3339 UTC timestamps, alphabetically sorted roles/permissions, empty arrays for unverified users, and exclusion of CPF/password/tokens.
  - **Middleware:**
    - `app/Http/Middleware/EnsureAccountNotDeactivated.php`: Intercepts deactivated users (`deactivated_at !== null`) on authenticated endpoints with HTTP 403.
    - `app/Http/Middleware/EnsureStatefulSession.php`: Rejects requests missing a valid session driver with HTTP 419.
  - **Controllers:**
    - `app/Http/Controllers/Auth/LoginController.php`: Timing-safe dummy bcrypt hash check on missing identity, anti-enumeration generic 401 on invalid credentials or deactivated accounts with incorrect password, explicit 403 on deactivated accounts with correct password, session regeneration (`$request->session()->regenerate()`), and 204 empty response.
    - `app/Http/Controllers/Auth/MyProfileController.php`: Returns authenticated user representation via `MyProfileResource`.
    - `app/Http/Controllers/Auth/LogoutController.php`: Web guard logout (`Auth::guard('web')->logout()`), session invalidation (`$request->session()->invalidate()`), and CSRF token regeneration (`$request->session()->regenerateToken()`), returning 204.
  - **Rate Limiter:** `app/Providers/AppServiceProvider.php` registers `login` rate limiter (5 attempts per minute) keyed by `hash('sha256', normalizedEmail . '|' . ip)` to protect against brute-force attacks without logging or caching raw email PII.
  - **Routing:** `routes/api.php` registers `/login` (`throttle:login`, `EnsureStatefulSession`), `/auth/my-profile` (`auth:sanctum`, `EnsureAccountNotDeactivated`), and `/logout` (`auth:sanctum`).
  - **Bootstrap & Routing Safety:** `bootstrap/app.php` sets `$middleware->redirectGuestsTo(fn (): ?string => null)` to return 401 instead of RouteNotFoundException on headless API endpoints; preserves `EnsureFrontendRequestsAreStateful` in `api` group.
  - **Surface Test:** `tests/Feature/ScaffoldTest.php` line 57 updated to include the newly approved endpoints (`api/v1/auth/my-profile`, `api/v1/login`, `api/v1/logout`).
  - **Test Coverage:** All 25 new tests across 5 test suites pass cleanly:
    - `tests/Feature/Auth/EmailUniquenessConstraintTest.php` (4 passed, 18 assertions)
    - `tests/Feature/Auth/LoginTest.php` (10 passed, 62 assertions)
    - `tests/Feature/Auth/ProfileTest.php` (6 passed, 47 assertions)
    - `tests/Feature/Auth/LogoutTest.php` (4 passed, 21 assertions)
    - `tests/Feature/Auth/HttpAuthSmokeTest.php` (1 passed, 19 assertions)
