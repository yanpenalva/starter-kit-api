# Specification — BUG-42 Security dependency refresh (November 2026)

## Request

- Source: Direct user instruction in agent conversation
- Requester: yanpenalva
- Date: 2026-11-20

## Objective

Refresh project dependencies within current declared compatible constraints in `composer.json`, updating to selected locked version `laravel/framework 13.43.0` and `symfony/* v8.1.10/v8.1.11`, resolving 9 known security vulnerability advisories without breaking application behavior, contracts, or static analysis, making only minimal test-harness adjustments for teardown mock lifecycle compatibility.

## Scope

- Update `composer.lock` using project-supported Docker Composer commands to `laravel/framework 13.43.0`.
- Resolve all 9 security advisories affecting `laravel/framework` and `symfony/*` packages.
- Maintain the exact pin of `dedoc/scramble` at `0.13.49`.
- Make minimal test-harness adjustment in `tests/TestCase.php` to skip `disconnectDatabaseConnections()` when `db` is resolved as a MockInterface mock.
- Run quality verification: validate, platform requirements check, audit, Pint lint, Larastan level 10 analyse, and full test suite under isolated test profile with Mailpit test service overrides (`MAIL_HOST=mailpit-test`, `MAIL_PORT=1025`).
- Record delivery metadata: branch `security/dependency-refresh-2026-11`, target `main`, remote `origin`.
- Record user's direct task-specific approval granted in this conversation on 2026-11-20 for isolated branch delivery, commits, push, PR merge into main, and auxiliary branch deletion.
- Register approved, scoped, expiring exception EXC-0002 waiving the GitHub Issue link requirement for this task only.
- Leave all modified files uncommitted for parent orchestrator finalization.

## Out of scope

- No changes to `composer.json` constraint definitions.
- No changes to production application behavior, contracts, or schemas.
- No upgrades of Docker images or digests.
- No global host Composer modifications.
- No major version migrations or speculative feature additions.
- No starting of unrelated services or touching persistent dev/prod databases.
- No Git commit, push, PR creation, merge, or branch deletion by the builder.

## Evidence and context

- Repository evidence: `composer.lock` audit identified 9 advisories across `laravel/framework` and `symfony/*`.
- Product or incident evidence: CVE-2026-128083, CVE-2026-121619, CVE-2026-118514, CVE-2026-45070, CVE-2026-118516, CVE-2026-123792.
- Relevant project context: `.context/project/quality.md`, `.context/project/security.md`, `.context/project/delivery.md`.
- Unknowns (`NOT FOUND`): None for this dependency update scope.

## Rules and contracts

- Business or operational rules: In-place non-breaking patch/minor upgrades only.
- Interfaces, data and permissions: No API signature, contract, or database schema changes.
- Security and privacy impact: Resolves all current known security advisories in application dependencies.

## Tests and acceptance criteria

- `composer validate --strict` exits with code 0.
- `composer check-platform-reqs` exits with code 0.
- `composer audit` reports 0 security vulnerability advisories.
- `composer lint` (Pint) exits with code 0.
- `composer analyse` (Larastan level 10) exits with code 0.
- `composer test` (PHPUnit suite) passes cleanly under isolated test profile (468 tests, 0 errors, 0 failures, 0 risky).
- `python3 scripts/validate_context.py --strict` exits with code 0.

## Open decisions

- Final review and Git delivery finalization owned by parent orchestrator.
