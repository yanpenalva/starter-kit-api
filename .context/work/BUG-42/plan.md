# Plan — BUG-42 Security dependency refresh (November 2026)

## Approved approach

Perform an isolated in-place update of Composer dependencies using Docker Compose, adhering strictly to declared constraints in `composer.json`. Refresh `composer.lock` to selected locked versions including `laravel/framework 13.43.0` and `symfony/*` (>=8.1.10/8.1.11), resolving all 9 advisories while retaining the exact `dedoc/scramble` pin at `0.13.49`. Apply minimal test-harness adjustment in `tests/TestCase.php` to handle Laravel 13.43.0 teardown `getConnections()` calls on strict DB mocks. Verify with Pint lint, Larastan level 10 static analysis, isolated PHPUnit suite, and context validation.

## Confirmed touchpoints

- Files/components/services:
  - `composer.lock`
  - `tests/TestCase.php`
  - `.context/exceptions/EXC-0002.json`
  - `.context/work/BUG-42/*`
- Interfaces and data: No changes to API routes, database schemas, contracts, or production code.
- Dependencies: Upgraded to selected locked versions within declared semver ranges in `composer.json` with 0 security advisories reported by `composer audit`.

## Execution order

1. Materialize CSD work evidence (`work-item.json`, `triage.md`, `reproduction.md`, `spec.md`, `plan.md`, `preflight.md`) and exception `EXC-0002.json`.
2. Run `composer update` inside isolated Docker container (`--no-deps api`) updating to `laravel/framework 13.43.0`.
3. Adjust `tests/TestCase.php` to guard `disconnectDatabaseConnections()` against MockInterface instances.
4. Run `composer validate --strict` and `composer check-platform-reqs`.
5. Run `composer audit` to verify resolution of all 9 vulnerabilities.
6. Run `composer lint` (Pint) and `composer analyse` (Larastan level 10).
7. Run isolated PHPUnit test suite under canonical Compose `test` profile with tmpfs Postgres 18 and required `MAIL_HOST=mailpit-test`, `MAIL_PORT=1025` overrides.
8. Run `python3 scripts/validate_context.py --strict` and `--examples`.
9. Confirm working tree clean for git finalization by parent orchestrator.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| T-1 | yanpenalva | none | EXC-0002 and BUG-42 evidence created and valid | 1 | core,project |
| T-2 | yanpenalva | T-1 | composer.lock updated to Laravel 13.43.0, tests/TestCase.php adjusted, composer audit reports 0 vulnerabilities | 2 | core,project |
| T-3 | yanpenalva | T-2 | lint, analyse, tests (468 passing), and validate_context pass | 3 | core,project,testing |

## Test plan

- `composer validate --strict`: Validates composer manifest and lockfile integrity.
- `composer check-platform-reqs`: Confirms runtime PHP 8.5 and extensions match.
- `composer audit`: Confirms 0 security advisories.
- `composer lint`: Verifies code formatting standards (Pint).
- `composer analyse`: Verifies static types at Larastan level 10 without baseline.
- `composer test`: Executes application PHPUnit test suite under isolated test profile with Postgres 18 tmpfs.
- `python3 scripts/validate_context.py --strict`: Confirms CSD context validity.

## Risks and rollback

- Risk: Regressions introduced by minor/patch updates in transitive dependencies or mock teardown lifecycle.
- Mitigation: Comprehensive static analysis (level 10), minimal test-harness guard in `tests/TestCase.php`, and automated test execution (468 tests).
- Rollback: Revert `composer.lock` and `tests/TestCase.php` with git restore.

## Required approvals

- Issue link waiver exception EXC-0002 approved by yanpenalva on 2026-11-20.
- Direct task-specific authorization for commit/push/PR merge/aux branch deletion approved by yanpenalva in this conversation on 2026-11-20.
