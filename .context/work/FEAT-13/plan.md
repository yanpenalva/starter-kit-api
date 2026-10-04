# Plan — FEAT-13

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | Sole Builder @agy | none | Migration additive lower(email) unique index + tests constraint/concurrency | 1 | architecture,security,testing |
| S2 | Sole Builder @agy | S1 | FormRequest, Resource, Middleware, Controllers (login, profile, logout) + tests | 2 | core,security,testing |
| S3 | Sole Builder @agy | S2 | Rate limiting login limiter + real HTTP cookie-jar smoke tests (CSRF, fixation, CORS) | 3 | security,testing |
| S4 | Independent parent/oracle reviewer | S3 | Independent review PASS / review verification before delivery integration | 4 | core,security |

## Implementation Steps

### Step 1: Database Migration (API #9 Slice)
1. Create new additive migration `database/migrations/2026_10_04_000001_add_case_insensitive_email_unique_index_to_users_table.php`.
2. Inspect for existing case-colliding rows (`lower(email)` count > 1); if present, throw `RuntimeException` with non-PII message without rewriting or deleting existing data.
3. Execute `CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))`.
4. Down method drops only the new index: `DROP INDEX IF EXISTS users_email_lower_unique`.

### Step 2: Form Requests, Resources, and Middleware
1. Create `app/Http/Requests/Auth/LoginRequest.php` validating `email` and `password`.
2. Create `app/Http/Resources/Auth/MyProfileResource.php` formatting profile representation with sorted roles/permissions and RFC 3339 UTC timestamp.
3. Create `app/Http/Middleware/EnsureStatefulSession.php` rejecting requests without session with HTTP 419.
4. Create `app/Http/Middleware/EnsureAccountNotDeactivated.php` checking `$user->deactivated_at !== null` and returning HTTP 403.

### Step 3: Controllers and Actions
1. Create `app/Http/Controllers/Auth/LoginController.php`:
   - Validates credentials against normalized email.
   - Performs constant-time dummy hash check when user is absent.
   - Evaluates active state (blocked 403 if deactivated).
   - Authenticates via web guard, regenerates session, returns 204.
2. Create `app/Http/Controllers/Auth/MyProfileController.php`:
   - Returns `MyProfileResource` for the authenticated user.
3. Create `app/Http/Controllers/Auth/LogoutController.php`:
   - Web guard logout, session invalidate, csrf regenerate, returns 204.

### Step 4: Route Wiring and Rate Limiting
1. In `app/Providers/AppServiceProvider.php`, define `login` RateLimiter (5/min keyed by SHA-256 hash of normalized email and IP).
2. In `routes/api.php`, register `/login`, `/auth/my-profile`, and `/logout` with appropriate middleware (`throttle:login`, `EnsureStatefulSession`, `auth:sanctum`, `EnsureAccountNotDeactivated`).

### Step 5: Test Implementation
1. `tests/Feature/Auth/LoginTest.php`:
   - Valid credentials active user -> 204, session regenerated.
   - Unknown email vs wrong password -> identical 401.
   - Deactivated user with wrong password -> 401 (no enumeration).
   - Deactivated user with correct password -> 403.
   - Validation failures -> 422 with structured errors, no credential echo.
   - Case-insensitive email login (`User@EXAMPLE.test` matches `user@example.test`).
   - Rate limiting: 5 requests allowed, 6th returns 429 with `Retry-After`. Normalized email/IP isolation.
2. `tests/Feature/Auth/ProfileTest.php`:
   - Unauthenticated -> 401.
   - Verified active user -> 200 with server-owned roles and permissions.
   - Unverified active user -> 200 with minimal profile, strictly empty `roles: []` and `permissions: []`.
   - Deactivated user with active session -> 403.
   - No CPF, password, remember_token, or hidden fields.
3. `tests/Feature/Auth/LogoutTest.php`:
   - Unauthenticated -> 401.
   - Authenticated -> 204, session invalidated, subsequent profile request -> 401.
   - Deactivated account session termination -> 204.
4. `tests/Feature/Auth/HttpAuthSmokeTest.php`:
   - Real HTTP server (`php -S`) with Guzzle and CookieJar.
   - `GET /sanctum/csrf-cookie` obtains XSRF cookie.
   - `POST /api/v1/login` with and without CSRF (verifies 419 on missing/spoofed token, 204 on valid token).
   - Session fixation proof (session ID changes across login).
   - CORS validation (allowed localhost:5173 with credentials vs denied origin without headers).
5. `tests/Feature/Auth/EmailUniquenessConstraintTest.php`:
   - Rejection of duplicate case-variant emails (`user@example.test` and `USER@example.test`).
   - Concurrency test with second PDO connection and `lock_timeout='500ms'`.
   - Migration fail-closed behavior on existing collisions.
   - Rollback safety test.
