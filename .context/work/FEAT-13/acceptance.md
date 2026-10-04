# Acceptance — FEAT-13 vs API issue #13 (delivered `main` 1390009)

Source: immutable `main` at `1390009d37f6a3493b21be7d83dbd32ae355d579` (merge of PR #37; source auth code `617e562`, docs head `2bcf108`). Main CI success run 37229764954. GitHub issue #13 stays OPEN; closure is the parent's act. Verdicts below are CSD evidence only.

## Scope bullets (issue body)

- Sanctum stateful SPA auth, HttpOnly session cookies, CSRF on `sanctum/csrf-cookie`, `POST /api/v1/login`, `GET /api/v1/auth/my-profile`, `POST /api/v1/logout`: YES. Routes registered in `routes/api.php`; smoke proves cookie issuance, CSRF 419 negatives, login 204, profile 200, logout invalidation.
- Active-user validation and verified-email rule, session regeneration after login, session invalidation plus CSRF token regeneration on logout: YES. `EnsureAccountNotDeactivated` (403 on existing deactivated session), `LoginController` regenerates session, `LogoutController` logs out the web guard, invalidates session, regenerates token. Unverified accounts receive minimal identity with empty roles/permissions (`MyProfileResource`).
- No PAT issuance on SPA login; personal tokens reserved for a separately authorized integration contract: YES. `DisallowHeaderAuthentication` rejects any non-empty `Authorization` header with 401 before Sanctum; `DB::listen` proof of zero queries against `personal_access_tokens`; no PAT references in production paths.
- Profile response carries identity plus effective roles/permissions via Resources; frontend is not session authority: YES. `MyProfileResource` returns id, name, email, RFC 3339 UTC `email_verified_at`, sorted roles/permissions; excludes cpf, password, remember token.
- Rate limiting, authentication, and failure messages without account-existence leakage: YES. `login` limiter 5/min keyed by SHA-256 of normalized email plus IP; dummy bcrypt check plus generic 401 on unknown email, wrong password, and deactivated account with wrong password; explicit 403 only with correct credentials on a deactivated account.

## Acceptance criteria (issue checkboxes)

- Sessionless calls 401, wrong credentials 401, blocked user 403, invalid CSRF 419: YES. Covered by `LoginTest`, `ProfileTest`, `LogoutTest`, and `HttpAuthSmokeTest` (89 passed, 572 assertions; smoke 1 passed, 60 assertions).
- Session works after login and protected access fails after logout: YES. Genuine fixation proof (pre-login jar replayed post-login gives 401; authenticated jar gives 200) and genuine logout invalidation proof (pre-logout snapshot replayed post-logout gives 401).
- Cookie, SameSite, CORS, and no-PAT-in-payload scenarios covered: YES. `SameSite=Lax` asserted on raw headers; denied origin gets no allow headers; credentialed CORS on the allowed origin; login returns 204 empty.

## Explicit non-requirements

- Browser-driven E2E is not in the issue body; acceptance here is real-HTTP-socket level. The wave-3 browser gate stays in progress and is not claimed.
- Production proxies and Unicode-collation limits are documented as not production-approved; no production approval is claimed.
- No GitHub approving review was created; producer is the Muse 1.3 worker, reviewer is the independent GLM-Go read-only session `ses_ef7c66907ffeNNr4HSjPwArWdL` (FINAL PASS, zero blockers).
