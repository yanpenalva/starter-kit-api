# Verification — FEAT-13

## Environment and Baseline
- Date: 2026-10-04.
- Container: PHP 8.5.11 pinned image, PostgreSQL 18 isolated test database (`starter-wave3-auth-postgres-test-1`).
- Base Commit: `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe`.
- Baseline: 57 tests, 293 assertions passing.
- Post-implementation: 82 tests, 460 assertions passing (+25 tests, +167 assertions).

## Quality Gates and Static Checks
- `composer validate --strict`: PASS (composer.json valid).
- `composer check-platform-reqs`: PASS (all PHP 8.5.11 extensions and polyfills satisfied).
- `composer audit`: PASS (0 security vulnerabilities).
- `composer lint` (`vendor/bin/pint --test`): PASS (73 files checked, 0 style issues).
- `composer analyse` (Larastan Level 10, no baseline): PASS (`[OK] No errors`).
- `docker compose -p starter-wave3-auth --profile test run --rm test vendor/bin/pest`: PASS (82 passed, 460 assertions, 30.04s).

## CSD and Project Integrity
- `python3 scripts/validate_context.py --strict`: PASS.
- `python3 scripts/validate_context.py --strict --examples`: PASS.
- `python3 -m unittest discover -s tests -v`: PASS (7 tests passed in 1.310s).
- `npx @owlcodium/context-spec-develop@1.2.1 doctor --scope project`: PASS (`CSD is healthy 1.2.1`).

## Real HTTP Socket Smoke Verification (`HttpAuthSmokeTest`)
1. `GET /sanctum/csrf-cookie`: 204 response, `XSRF-TOKEN` cookie set, session cookie `HttpOnly`.
2. Negative CSRF: `POST /api/v1/login` missing CSRF token returns 419.
3. Negative CSRF: `POST /api/v1/login` forged/invalid CSRF token returns 419.
4. CORS Origin: unauthorized origin `http://evil.example` denied CORS response header.
5. Successful Login: valid CSRF + credentials returns 204 with credentialed CORS headers (`Access-Control-Allow-Origin: http://localhost:5173`, `Access-Control-Allow-Credentials: true`).
6. Session Fixation: session ID changes after login.
7. Authenticated Profile: `GET /api/v1/auth/my-profile` with authenticated session cookie returns 200 with approved JSON schema.
8. Logout: `POST /api/v1/logout` invalidates session and regenerates CSRF token, returning 204.
9. Stale Session: subsequent profile request with prior cookie returns 401 Unauthenticated.

## RFC vs Product Identity Contract
- Decision: Email case-insensitivity is a product identity contract for this system, enforced at the database level via `lower(email)` unique index and in the application via `mb_strtolower(trim($email))`. It is not an assertion of universal RFC 5321/5322 behavior, but guarantees unambiguous user account identification.
