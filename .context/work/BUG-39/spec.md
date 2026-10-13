# Specification — BUG-39 Corrigir bloqueios de segurança do MFA administrativo

## Objective

Close all four review blockers in optional admin MFA: require the current password at enrollment; invalidate any authenticated web session when login starts an MFA challenge and gate every protected route until challenge success; store recovery codes only as one-way hashes; and make recovery-code consumption atomic.

## Scope

- Add focused feature coverage for each reported blocker, including an authenticated pre-existing session and a PostgreSQL concurrent-use case.
- Change MFA enrollment, login/challenge session handling, the shared authenticated-route boundary and recovery-code storage/consumption.
- Update the authentication/API behavior documentation and record validation evidence.
- Extend the route inventory and OpenAPI export tests to cover all six MFA routes and byte-identical repeated exports under `LC_ALL=C`.

## Out of scope

- Changing MFA factors, API route names, session-only authentication, login throttling or generated OpenAPI snapshots.
- Editing `contracts/openapi.json`; its producer runs only on `main`.
- Creating another PR, merging PR #84 or releasing to production. The user directed a push to the existing PR #84 only.
- Modifying `/opt/data/work/starter-kit-api` or running Composer installation.

## Acceptance criteria

1. Missing or incorrect current password returns 422 and creates no enrollment; the current password succeeds.
2. MFA login logs out and invalidates a pre-existing web session. Every `auth:sanctum` route denies an admin with enabled MFA until the current session completes a challenge; a completed challenge permits access.
3. Database dump contains hashes that verify recovery codes but contains no plaintext code; plaintext is returned once from generation.
4. Concurrent PostgreSQL consumers of one recovery code produce exactly one successful consume and leave no code available.
5. Pint and configured PHPStan/Larastan pass; focused MFA Pest tests pass on SQLite, the PostgreSQL concurrency case is executable in CI, and strict context validation passes.
6. `ScaffoldTest` lists all six MFA routes; the OpenAPI export test includes those paths and confirms two exports are byte-identical without changing the tracked snapshot.

## Rules and unknowns

- Preserve Sanctum session/cookie and CSRF semantics; HTTP tests use `ActsAsSanctumUser` where applicable.
- PostgreSQL and Redis are CI-only for this run; local Pest uses SQLite, and the PostgreSQL race test skips locally.
- Issue URL: NOT FOUND. No external issue or PR will be created.
