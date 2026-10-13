# Module: Authentication and Session Identity (Auth)

## Overview

The `auth` module owns stateful SPA session authentication, user identity boundary handling, session lifecycle, CSRF enforcement, login rate limiting, and account eligibility enforcement for the Starter Kit REST API.

## Boundaries and Ownership

- **Owns:**
  - Login (`POST /api/v1/login`), Profile (`GET /api/v1/auth/my-profile`), and Logout (`POST /api/v1/logout`).
  - Session creation, regeneration (anti-fixation), and invalidation.
  - CSRF token validation and session middleware enforcement for stateful API endpoints.
  - Login throttling (5 attempts/min keyed by SHA-256 hash of normalized email and client IP).
  - Account state eligibility: blocking explicitly deactivated accounts (`HTTP 403`) and restricting unverified accounts to minimal identity without roles/permissions.
  - Active-session listing and revocation for the authenticated user.
  - Session invalidation after password reset or account suspension.
  - Email boundary normalization (trim and lowercase) matching the case-insensitive database identity contract.
- **Does NOT Own:**
  - Administrative user mutations, user creation, or role assignment (owned by privileged administration / `UserPolicy`).
  - Personal Access Tokens (PAT) or Bearer token issuance (expressly disallowed for SPA authentication).
  - Public registration and email verification links (owned by API #14).
  - Password-reset issuance and notification (owned by API #15); auth owns invalidating the user's sessions after a successful reset.
  - Production TLS, session domains, or production reverse-proxy configuration.

## Endpoints and Contracts

### 1. `POST /api/v1/login`
- **Request:** JSON `{ "email": "...", "password": "..." }`.
- **Validation:** `LoginRequest` validates input presence and formats. Fails with standard HTTP 422 `{ "message": "...", "errors": { ... } }` without echoing credentials.
- **Normalization:** Email is trimmed and lowercased (`mb_strtolower(trim($email))`).
- **Database Lookup:** Case-insensitive, whitespace-trimmed lookup backed by the `users_email_lower_unique` index: `lower(btrim(email, E' \t\n\r\x0B')) = ?`.
- **Timing and Enumeration Defense:** If the account does not exist, a constant dummy hash check is performed using standard bcrypt work factor, preventing timing side-channels and account enumeration.
- **Authentication:**
  - Non-empty `Authorization` header: generic HTTP 401 `{ "message": "Unauthenticated." }` via `DisallowHeaderAuthentication`.
  - Unknown email or wrong password: generic HTTP 401 `{ "message": "Invalid credentials." }`.
  - Deactivated account with wrong password: generic HTTP 401 `{ "message": "Invalid credentials." }` (avoids revealing deactivated status without valid credentials).
  - Deactivated account with correct credentials: HTTP 403 `{ "message": "This account has been deactivated." }`.
  - Active account with correct credentials: authenticates via `web` session guard, regenerates session ID to prevent session fixation, and returns empty HTTP 204.

### 2. `GET /api/v1/auth/my-profile`
- **Authentication:** Requires active session (`auth:sanctum` / `auth:web`). Anonymous requests receive HTTP 401. Requests presenting an `Authorization` header receive HTTP 401 fail-closed via `DisallowHeaderAuthentication` before Sanctum token fallback.
- **Account Eligibility:** An explicitly deactivated account (`deactivated_at !== null`) receives HTTP 403.
- **Response Format:** HTTP 200 `{ "data": { "id": int, "name": string, "email": string, "email_verified_at": "RFC3339 UTC"|null, "roles": string[], "permissions": string[] } }`.
- **Authorization & Invariants:**
  - Returns strictly the authenticated user's own profile. Does not consult privileged `UserPolicy::view` (which governs administrative user management).
  - Unverified accounts (`email_verified_at === null`) receive minimal identity with strictly empty `roles: []` and `permissions: []`, even if database records grant them roles.
  - Verified active accounts receive sorted server-owned role names and effective permissions.
  - CPF, password, remember tokens, and sensitive attributes are excluded.

### 3. `POST /api/v1/logout`
- **Authentication:** Requires active session (`auth:sanctum` / `auth:web`). Anonymous requests receive HTTP 401. Requests presenting an `Authorization` header receive HTTP 401 via `DisallowHeaderAuthentication`.
- **Action:** Logs out of `web` guard, invalidates the server session, and regenerates the CSRF token. Returns HTTP 204.
- **Subsequent Requests:** Requests reusing the previous session cookie receive HTTP 401.

### 4. Administrative MFA
- **Enrollment:** `POST /api/v1/auth/mfa/enroll` is restricted to admins and requires the current password. It returns the TOTP secret, provisioning URI and recovery codes; recovery codes are shown as plaintext only in this response.
- **Confirmation:** `POST /api/v1/auth/mfa/confirm` verifies a TOTP code and returns `{ "confirmed": true }`. Recovery codes are not returned again.
- **Login:** A confirmed-MFA admin with valid credentials receives HTTP 200 `{ "requires_mfa": true }`. Any existing web authentication is logged out and the session is invalidated before the challenge-only session is created.
- **Challenge:** `POST /api/v1/auth/mfa/challenge` accepts a TOTP or an unused recovery code. A successful challenge authenticates the web session and marks it as MFA-verified. Recovery codes are stored as one-way password hashes and consumed under a database transaction with a row lock, so concurrent attempts cannot both use one code.
- **Protected routes:** Every `auth:sanctum` route requires a completed MFA challenge when the authenticated user is an admin with confirmed MFA. This also rejects sessions created before MFA was enabled.
- **Recovery code regeneration:** `POST /api/v1/auth/mfa/recovery-codes` returns newly generated plaintext codes once and stores only their hashes. `POST /api/v1/auth/mfa/disable` clears the TOTP secret and recovery hashes.

### 5. Active sessions
- **Driver:** `SESSION_DRIVER=database` is required so the API can enumerate and revoke the session rows used by Laravel's database handler. The migration keeps Laravel's opaque string session ID as the `sessions.id` primary key; `sessions.user_id` is a nullable bigint matching `users.id`.
- **`GET /api/v1/auth/sessions`:** Returns `{ "data": [{ "id": string, "ip_address": string|null, "user_agent": string|null, "last_activity": RFC3339 UTC|null, "is_current": boolean }] }` for the authenticated user's sessions only.
- **`DELETE /api/v1/auth/sessions/{id}`:** Deletes only a session owned by the authenticated user and returns HTTP 204. Missing and other users' IDs return the same HTTP 404 message. Deleting the current session invalidates it; its next request receives HTTP 401.
- **`DELETE /api/v1/auth/sessions`:** Deletes all of the authenticated user's sessions except the current session and returns HTTP 204. It does not affect any other user.
- **Rate limiting:** Both DELETE routes share a limit of 10 requests per minute per authenticated user; excess requests receive HTTP 429.
- **Lifecycle:** A successful password reset and account suspension delete every database session for the affected user. Failed password resets leave sessions unchanged.

## Security Architecture

- **Session Stateful Guard & Header Rejection:** Operates exclusively with Laravel Sanctum session cookies (HttpOnly, SameSite=lax). No PAT / JWT storage. Any request bearing an `Authorization` header is immediately rejected fail-closed with HTTP 401 (`DisallowHeaderAuthentication`), completely preventing fallback queries against non-existent token storage (`personal_access_tokens`).
- **CSRF Enforcement:** State-changing requests (`POST /login`, `POST /logout`) enforce CSRF validation (`PreventRequestForgery`). Bypassing stateful Origin/Referer is rejected (`EnsureStatefulSession`) rather than crashing or skipping CSRF.
- **Rate Limiting:** Login allows 5 attempts/minute per hashed identity/IP bucket; session revocation allows 10 requests/minute per authenticated user. The login bucket contains no PII.
- **Email Identity Contract:** Case-insensitive and whitespace-trimmed email uniqueness enforced at the database level by PostgreSQL expression index `CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(btrim(email, E' \t\n\r\x0B')))`.
- **Trusted Proxies Limitation:** Trusted proxies are configured as empty by design (`$middleware->trustProxies(at: [])`). No RFC1918 private ranges or localhost addresses are trusted by default. In production deployments fronted by reverse proxies or load balancers (e.g. AWS ALB, Cloudflare, NGINX), trusted proxies must be explicitly configured in deployment environment / proxy settings before trusting client IP headers for rate limiting and audit logs.
