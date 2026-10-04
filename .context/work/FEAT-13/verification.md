# Verification — FEAT-13

## Environment, Runtime Resources, and Baseline
- **Date:** 2026-10-04.
- **Compose Project:** `starter-wave3-auth` (isolated from primary dev stack `starter-kit-api`).
- **Container Image:** `starter-wave3-auth-dev:local` (built from `Dockerfile`, PHP 8.5.11-fpm).
- **Test Database:** `postgres-test` service running `postgres:18-alpine@sha256:77f585114c32fbca283dc835b0596f4e52b51b4c6662d7810b2f4084f60a1873`, internal `testing` network, tmpfs, database `starter_api_test`.
- **Ancillary Services:** `redis-test` (`redis:alpine@sha256:6cbef353e480a8a6e7f10ec545f13d7d3fa85a212cdcc5ffaf5a1c818b9d3798`), `mailpit-test` (`axllent/mailpit@sha256:c076638db1e15662150be4fb62b8a6e96ef6ba5bde90c838a0239225854830f7`).
- **Environment File:** Local untracked `/home/hiyan/Development/github/personal/starter-kit-api-wave3-auth-worktree/.env` (contains local non-secret dev credentials; no production credentials).
- **Base Commit:** `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` (`origin/main`).
- **Baseline Totals:** 57 tests, 293 assertions passing.
- **Candidate Commit (Pushed `c4eacbf`):** 82 tests, 460 assertions passing.
- **Current Patched State Totals:** 82 tests, 526 assertions passing (+25 tests, +233 assertions over baseline).

## Quality Gates and Complete Sanitized Commands
1. `docker compose -p starter-wave3-auth run --rm --no-deps api composer validate --strict`
   - Exit code: 0
   - Output: `./composer.json is valid`
2. `docker compose -p starter-wave3-auth run --rm --no-deps api composer check-platform-reqs`
   - Exit code: 0
   - Output: All PHP 8.5.11 platform requirements and extensions satisfied.
3. `docker compose -p starter-wave3-auth run --rm --no-deps api composer audit`
   - Exit code: 0
   - Output: `No security vulnerability advisories found.`
4. `docker compose -p starter-wave3-auth run --rm --no-deps api composer lint`
   - Exit code: 0
   - Output: `PASS ... 73 files` (0 style issues).
5. `docker compose -p starter-wave3-auth run --rm --no-deps api composer analyse`
   - Exit code: 0
   - Output: `[OK] No errors` (strict Larastan Level 10, no baseline).
6. `docker compose -p starter-wave3-auth --profile test run --rm test vendor/bin/pest`
   - Exit code: 0
   - Output: `Tests: 82 passed (526 assertions), Duration: 30.68s` (0 failures, 0 errors).

## CSD and Project Integrity
1. `python3 scripts/validate_context.py --strict`
   - Exit code: 0
   - Output: `Context validation passed.`
2. `python3 scripts/validate_context.py --strict --examples`
   - Exit code: 0
   - Output: `Context validation passed.`
3. `python3 -m unittest discover -s tests -v`
   - Exit code: 0
   - Output: `Ran 7 tests in 1.310s, OK`
4. `npx @owlcodium/context-spec-develop@1.2.1 doctor --scope project`
   - Exit code: 0
   - Output: `CSD is healthy (1.2.1)`

## Real HTTP Socket Smoke Verification (`HttpAuthSmokeTest`)
Executed against ephemeral single-process `php -S 127.0.0.1:8099` running with child `APP_ENV=testing` on dedicated test database `starter_api_test`.
- **Fail-Closed Runtime Isolation:** Verified in `setUp()`, `tearDown()`, and test method. Refuses execution if `APP_ENV !== 'testing'`, database connection is not `pgsql`, target database is not `starter_api_test`, host is not `postgres-test`, or if `DB_URL`, `DATABASE_URL`, or `TEST_TOKEN` are present in configuration or inherited environment. Process explicitly unsets URL and token variables (`false`).
- **CSRF & Cookie Issuance:** `GET /sanctum/csrf-cookie` returns 204; sets `XSRF-TOKEN` cookie and `HttpOnly` session cookie. Verified `SameSite=Lax` attribute on raw response headers.
- **Negative CSRF:** `POST /api/v1/login` missing CSRF token returns 419; invalid CSRF token returns 419.
- **Denied CORS:** Unauthorized origin (`http://evil.example`) receives no `Access-Control-Allow-Origin` approval.
- **Non-Stateful Bypass:** Login attempt with non-stateful origin is rejected with 419 (`EnsureStatefulSession`).
- **Successful Login:** Returns 204 with credentialed CORS headers (`Access-Control-Allow-Origin: http://localhost:5173`, `Access-Control-Allow-Credentials: true`).
- **Genuine Session Fixation Proof:** Pre-login cookie jar snapshot is replayed against `GET /api/v1/auth/my-profile` post-login; returns 401 unauthenticated, while the authenticated jar returns 200 with approved profile schema. This proves session migration without relying on randomized encrypted cookie string inequality.
- **Existing-Session Deactivation Interception:** Authenticated session for active account receives HTTP 403 once account is deactivated in the database (`EnsureAccountNotDeactivated`).
- **Logout & Invalidation Proof:** Pre-logout authenticated cookie snapshot is replayed post-logout; returns 401 unauthenticated, proving server-side session invalidation.
- **Stale CSRF Token Rejection:** State-changing POST with pre-logout CSRF token returns 419. Fresh CSRF token from new cookie jar is accepted (returns 401 on bad credentials instead of 419).

*Limitation Note:* This test exercises the HTTP socket, cookie handling, and middleware boundary. It is distinct from a full browser-driven SPA E2E test.

## RFC vs Product Identity Contract
- Decision: Email case-insensitivity is a product identity contract for this system, enforced at the database level via `lower(email)` unique expression index and in the application via `mb_strtolower(trim($email))`. It is not an assertion of universal RFC 5321/5322 behavior, but guarantees unambiguous user account identification.

## Scope Boundary & Resolution
- `tests/Feature/ScaffoldTest.php` line 57 was updated in candidate commit `c4eacbf` to accommodate newly registered auth endpoints in public surface assertion (`api/v1/login`, `api/v1/auth/my-profile`, `api/v1/logout`). Initially flagged as an ownership boundary BLOCKER, this modification is now RESOLVED by explicit parent authorization. Verification confirmed the diff against base `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` contains strictly those three approved routes with no weakening or unrelated edits. Independent review remains PENDING.
