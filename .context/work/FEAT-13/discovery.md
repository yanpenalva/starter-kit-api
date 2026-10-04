# Discovery — FEAT-13

## Context and Baseline

- **Work Item:** FEAT-13 (Product / Feature).
- **Owner:** yanpenalva.
- **Track & Type:** product / feature.
- **Profile:** senior-software-engineer.
- **Branch:** `feat/wave-3-api-auth` created from exact `origin/main` base `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` (user checkpoint PR36 merged wave2 gate PASS).
- **Target:** `main`, remote `origin` (`git@github.com:yanpenalva/starter-kit-api.git`).
- **Policy Exception:** [EXC-0001](../../exceptions/EXC-0001.json) applies (approved through 2026-12-31 for roadmap delivery branches targeting `main`), with explicit user restriction: **NO PR/merge** in this execution; independent parent/oracle review remains PENDING before delivery/integration.
- **Baseline:** 57 tests, 293 assertions passing on isolated PostgreSQL 18 (`-p starter-wave3-auth`).

## Primary Scope: API #13 & Outstanding API #9 Slice

1. **API #13 (Sanctum SPA Session Authentication):**
   - Stateful session authentication via Laravel Sanctum (session cookies, HttpOnly, SameSite=lax, CSRF protection).
   - `POST /api/v1/login`: FormRequest validation, normalized case-insensitive email lookup, timing-safe password evaluation, session regeneration, 204 response. No PAT/JWT. Unknown email and wrong password share generic 401. Deactivated account with correct credentials returns 403; with invalid credentials returns 401 to prevent enumeration. Rate limited to 5 attempts/minute per hashed identity (SHA-256 of normalized email and client IP).
   - `GET /api/v1/auth/my-profile`: Returns authenticated user representation (`{ data: { id, name, email, email_verified_at, roles, permissions } }`). Minimal profile for unverified users (always empty roles/permissions). Explicitly deactivated account with existing session cookie receives HTTP 403.
   - `POST /api/v1/logout`: Web guard logout, session invalidation, CSRF regeneration, 204 response. Anonymous calls return 401.
   - Real cookie, SameSite, and CORS verification (`http://localhost:5173` with credentials; denied origins without CORS headers).

2. **API #9 Slice (Case-Insensitive Email Uniqueness):**
   - Outstanding requirement from [API #9](https://github.com/yanpenalva/starter-kit-api/issues/9) and [FEAT-9](../FEAT-9/work-item.json) (comment id 5981903856): existing PostgreSQL table `users` had case-sensitive `email` unique index.
   - Additive new migration adding PostgreSQL expression unique index: `CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))`.
   - Index design reasoning: The project explicitly chose case-insensitive email uniqueness as a **product identity contract** for user accounts, not as an assertion of universal RFC 5321/2822 compliance (which technically allows case-sensitive local-parts). Under this contract, `user@example.test` and `USER@example.test` represent the identical subject identity across login, registration, and rate limiting.
   - Fail-closed behavior on existing colliding rows without rewriting or deleting existing data.
   - Clean, safe rollback removing only the new index without altering data or preexisting constraints.
