# Slice 10 — Sanctum session profile authority and refresh

## Intake and scope

- Parent work: FEAT-13 / API #13 (completed); this records a user-directed authority-contract increment without reopening the completed parent.
- Delivery: `feat/slice-10-session-profile-authority` → `main`, remote `origin`, EXC-0001, existing PR #88; branch starts at `origin/main` `98cf64147080c03e17c14d664ac6ac08231fcb02`.
- Outcome: the API contract states that the authenticated session alone selects the profile subject, and each profile GET reflects current persisted identity, roles, permissions, and active/deactivated status.
- Working interpretation of “refresh”: re-read the authenticated model on the existing profile GET; no new endpoint, client token, or session identifier is added. User clarification was requested asynchronously.
- In scope: a SQLite-safe Sanctum profile test, a real cookie-jar assertion in the PostgreSQL-only HTTP smoke test, middleware that refreshes the profile's authenticated model, and API/auth architecture documentation.
- Acceptance: a competing user's ID and role supplied in the request cannot change the authenticated user's profile. A held stale authenticated model must show persisted identity and role changes, and a newly deactivated verified user must receive 403 on the next profile request.
- No frontend changes, auth transport changes, profile schema changes, or token/session identifier additions.

## Plan and gates

1. Add focused profile authority and refresh tests using `ActsAsSanctumUser`, observe the stale model failure, and prove request inputs cannot select another user's profile.
2. Reload the authenticated model before profile eligibility checks and serialization; make the session-versus-presentation boundary explicit in canonical API and architecture documentation.
3. Run relevant SQLite Pest, Pint, PHPStan, strict context validation, and `git diff --check`.
4. Push updates to existing PR #88; do not create another PR or merge that PR.

## Execution evidence

- Before `EnsureAccountNotDeactivated` refreshed the guard's `User` instance, the refresh test returned cached name/role data instead of persisted values. After the change, it reflects the identity and admin role update and returns 403 after deactivation.
- SQLite Pest `SessionProfileAuthorityTest.php`: 1 passed, 4 assertions; refresh plus existing profile/account tests: 15 passed, 79 assertions.
- The real HTTP cookie-jar smoke assertion uses a competing admin identity and request-supplied `user_id`/`role`; it requires the isolated PostgreSQL stack and was not run locally because that stack is unavailable.
- `composer lint`: passed (Pint).
- `composer analyse`: passed (PHPStan Level 10, no errors).
- `python3 scripts/validate_context.py --strict --examples`: passed; no example suite is installed.
- Initial implementation check: `git diff --check` passed; its profile JSON/schema and contract snapshot were unchanged at that point.
- Delivery: implementation commit `37e1ddf` was pushed to `origin/feat/slice-10-session-profile-authority`; the later user-directed PR #88 is the delivery target.
- Production deployment, PR merge and issue closure were not performed.

## Revalidation after `origin/main` advanced

- Integrated successive `origin/main` updates for merges #79, #83, #86, #89 and #87 in merge commits `d81e6a12c8c989d96b23a0a3dfc92c6b1f181612`, `9910baa3a669a884dd3d70cdc426536410466aa4` and `73c535f47c33d32c26adc4ad458aca2c42fc3a18`; resolved the API contract documentation conflict by retaining both the profile-session authority rule and the database-session-driver requirement.
- `composer lint`: passed.
- SQLite Pest for `ProfileTest`, `SessionProfileAuthorityTest`, `SessionProfileRefreshTest`, `ScaffoldTest`, `OpenApiSnapshotTest`, and `OpenApiResponseShapeTest`: 26 passed, 1 skipped. The skip is the PostgreSQL database-isolation assertion, which remains active in the PostgreSQL test profile.
- The full SQLite suite is not a passing gate in this environment because PostgreSQL-specific, Redis/Mailpit, and environment-guard tests need services/configuration that are unavailable here. PCOV reported 89.27% lines (1390/1557) with `--min=85`; the test command still exits nonzero for those environment-dependent failures, so this is not PostgreSQL CI evidence.
- Deterministic OpenAPI export ran twice with `LC_ALL=C`, compared the outputs byte for byte, and regenerated `contracts/openapi.json` at SHA-256 `88bcccf993dd74915da9db2dd1a4eaaba1f4354b616054fe8b74c70042a3fb66` (133841 bytes). The artifact is byte-identical to the latest `origin/main` snapshot.
- `scripts/contract_compat.py --base-commit=origin/main --candidate=contracts/openapi.json` could not run because `oasdiff` is not installed locally. PostgreSQL/Compose execution was unavailable because the Docker daemon is not running.
- The shared `vendor` symlink's generated autoloader points at `/opt/data/work/starter-kit-api`; local Pest checks used a temporary worktree-first autoloader so they exercised this branch's source without changing the shared vendor tree.
- Pushed the updated branch to the existing PR #88 at commit `d5d0d2fb92ce7a1af7c4122486bfe8d88fff70ca`; no new PR was created and no PR merge was issued.
