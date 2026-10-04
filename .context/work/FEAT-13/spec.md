# Specification — FEAT-13

## Functional Specifications

### 1. Endpoint: `POST /api/v1/login`
- **Request payload:** JSON object with `email` (required, string, email format) and `password` (required, string).
- **Validation:** Enforced via `LoginRequest`. Failed validation returns standard HTTP 422 `{ "message": "...", "errors": { ... } }`. No sensitive values echoed in errors.
- **Normalization:** Inbound email is normalized via `mb_strtolower(trim($email))` across validation, rate limiting, and database queries.
- **Database Query:** Backed by the lower-email index: `User::query()->whereRaw('lower(email) = ?', [$normalizedEmail])->first()`.
- **Anti-Enumeration and Constant-Time Defense:**
  - If user record is not found, a dummy password hash is checked using `Hash::check($password, $dummyHash)` to prevent timing analysis.
  - Generic 401 `{ "message": "Invalid credentials." }` returned for: non-existent email, incorrect password for active user, and incorrect password for deactivated user.
- **Deactivated Account Handling:** If credentials match but `deactivated_at !== null`, returns HTTP 403 `{ "message": "This account has been deactivated." }`.
- **Session Authentication:**
  - On valid credentials for an active account: authenticates via `Auth::guard('web')->login($user)`.
  - Regenerates session ID via `$request->session()->regenerate()` to prevent session fixation.
  - Returns empty body HTTP 204.
  - Never issues Personal Access Tokens (PAT) or JWTs.

### 2. Endpoint: `GET /api/v1/auth/my-profile`
- **Authentication:** Protected by `auth:sanctum` (configured with web guard). Unauthenticated requests return HTTP 401.
- **Account Eligibility:** An explicitly deactivated account (`deactivated_at !== null`) accessing profile with an existing session receives HTTP 403.
- **Resource Representation:** Enforced via `MyProfileResource` returning `{ "data": { "id": int, "name": string, "email": string, "email_verified_at": string|null, "roles": string[], "permissions": string[] } }`.
- **Field Invariants:**
  - `email_verified_at`: RFC 3339 UTC formatted string (e.g. `2026-10-04T12:00:00Z`) or `null`.
  - Unverified accounts (`email_verified_at === null`): `roles` and `permissions` are strictly empty arrays (`[]`), regardless of database role assignments.
  - Verified active accounts: `roles` contains sorted array of assigned role names; `permissions` contains sorted array of effective permissions (including direct and role-inherited permissions).
  - Explicitly excludes `cpf`, `password`, `remember_token`, and Eloquent model attributes.
  - Does not invoke administrative `UserPolicy::view`.

### 3. Endpoint: `POST /api/v1/logout`
- **Authentication:** Requires active session. Unauthenticated calls return HTTP 401.
- **Action:** `Auth::guard('web')->logout()`, `$request->session()->invalidate()`, and `$request->session()->regenerateToken()`.
- **Response:** HTTP 204 empty body.
- **Post-condition:** Subsequent requests with the prior session cookie return HTTP 401.
- **Deactivated Account Disposition:** If a deactivated account holds a session, calling logout is permitted to terminate the session and clear cookies cleanly with HTTP 204.

### 4. Stateful Session & CSRF Enforcement
- Session cookies configured with `HttpOnly` and `SameSite=lax`.
- `POST /login` and `POST /logout` enforce CSRF validation (`PreventRequestForgery`). Missing or invalid CSRF returns HTTP 419.
- Requests attempting to bypass stateful Origin/Referer without a session are safely rejected (`EnsureStatefulSession`) with HTTP 419, preventing missing session crashes and CSRF bypass.
- CORS: Exact allowed origin `http://localhost:5173` with credentials enabled. Denied origins receive no CORS allow headers.

### 5. Login Rate Limiting
- Login rate limit: 5 attempts per minute.
- Key format: `hash('sha256', mb_strtolower(trim($email)) . '|' . $request->ip())`.
- Key isolation: isolates different IPs and normalized email addresses. Variants with whitespace and uppercase collide in the same bucket, preventing evasions.
- Exceeding limit returns HTTP 429 with `Retry-After` header.
- Global 60/min limit in `AppServiceProvider` is preserved.

### 6. Case-Insensitive Email Uniqueness (API #9 Slice)
- Additive migration adding index `users_email_lower_unique` on `users (lower(email))`.
- Product contract: Case-insensitivity is an intentional application domain identity contract.
- Fail-closed migration: If colliding case-variant emails exist prior to migration, migration aborts cleanly with descriptive non-PII exception, without modifying, truncating, or rewriting any records.
- Rollback: `DROP INDEX IF EXISTS users_email_lower_unique`. Preserves original `users_email_unique` constraint and all table rows.
