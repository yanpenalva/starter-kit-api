# Verification — BUG-39

Date: 2026-10-12

## Application gates

| Gate | Result | Evidence |
| --- | --- | --- |
| Pint | PASS | `source /opt/data/php/env.sh && /opt/data/php/root/usr/bin/php8.5 /opt/data/php/composer lint` |
| PHPStan/Larastan level 10 | PASS | Full configured analysis: `vendor/bin/phpstan analyse --autoload-file=/tmp/bootstrap.api-fixwork3.php --memory-limit=512M --no-progress --error-format=table`; result: `No errors`. The temporary bootstrap loads this checkout's application code because `vendor` is a symlink with an autoload map for another worktree. |
| Focused MFA Pest | PASS | `vendor/bin/pest --configuration=/tmp/phpunit.sqlite.api-fixwork3.xml tests/Feature/Auth/AdminMfaTest.php tests/Feature/Auth/MfaRecoveryCodeConcurrencyTest.php`; 103 assertions, no failures. |
| Full Pest suite on local SQLite | NOT PASSABLE HERE | The run ended with 64 failures, 217 warnings and 3 passes because repository tests assert the dedicated PostgreSQL test configuration and integration tests require CI services. PostgreSQL, Redis and Mailpit were not started locally, as instructed. |

Pest emits warnings because this checkout has no `.env` file. The PostgreSQL-only recovery-code concurrency test is skipped on SQLite; it forks two consumers behind a start barrier and asserts exactly one success when CI runs it with PostgreSQL 18.

## Context and repository gates

- `python3 scripts/validate_context.py --strict`: PASS.
- `python3 scripts/validate_context.py --strict --examples`: PASS; expected warning that no example suite is installed.
- `python3 -m unittest discover -s tests -v`: PASS, 113 tests. The pinned `oasdiff` 1.33.0 Linux ARM64 binary was verified and kept in `/tmp` because the repository installer targets AMD64.
- `npx @owlcodium/context-spec-develop@1.2.1 doctor --scope project`: PASS (`CSD is healthy (1.2.1)`).
- `git diff --check`: PASS.

## Runtime limits

The test configuration and bootstrap are temporary files under `/tmp`. No Composer installation was run, no local `.env` was added, and no PostgreSQL or Redis service was used. The PostgreSQL concurrency assertion remains for CI execution.

## Follow-up verification — merge and MFA surface coverage (2026-10-13)

- Merge resolution commit: `d55d03bb6c041d7cd5fb4f7bd7f348c2df2b99eb0`. `User` retains the MFA fields, casts and `hasMfaEnabled()` from the feature branch, plus `markEmailAsVerified()` from `main`.
- Scaffold route inventory: PASS — one assertion after adding all six registered MFA routes.
- OpenAPI contract tests: PASS — 562 assertions, including all six MFA paths. A new test exports twice under `LC_ALL=C` and compares bytes; `contracts/openapi.json` is unchanged.
- Focused MFA Pest: PASS — 103 assertions on SQLite. The PostgreSQL row-lock concurrency behavior remains for CI.
- Merged identity/action/admin Pest tests: PASS — 335 assertions across `UsersActionsTest`, `UsersAdminHttpTest` and `PublicIdentityTest`.
- Pint: PASS — `composer lint`.
- PHPStan/Larastan level 10: PASS — configured paths, with the temporary bootstrap for this checkout and `APP_ENV=testing`, `MAIL_MAILER=array`.
- Context validation: PASS — strict plus examples mode; expected warning that no example suite is installed.
- Python tooling suite: PASS — 113 tests with the pinned `oasdiff` 1.33.0 ARM64 binary available from `/tmp`.
- CSD doctor: PASS — `npx @owlcodium/context-spec-develop@1.2.1 doctor --scope project`.
- Pest emits environment warnings because this worktree has no `.env`; no local `.env` was created. Tests use the temporary SQLite configuration. The full PostgreSQL Compose profile was not run locally.

## Follow-up verification — OpenAPI freshness after main #79, #83 and #86 (2026-10-13)

- Merged `origin/main` at `4a8531661b73d22c3dfac932e65faa4c0b936f58`. Resolved API/auth context conflicts by retaining both the admin MFA and active-session contracts.
- Exported twice under `LC_ALL=C` after applying migrations to `/tmp/api-fixwork3-openapi.sqlite`; `cmp storage/app/openapi-ci1.json storage/app/openapi-ci2.json`: PASS.
- Refreshed `contracts/openapi.json`; `cmp storage/app/openapi-ci1.json contracts/openapi.json`: PASS. SHA-256: `0a1321d8cb263563f385c73f8d6972e2a1350a298cf2c17b3ebf064d14b6289f`.
- OpenAPI structural validation: PASS. Contract compatibility against main SHA `4a8531661b73d22c3dfac932e65faa4c0b936f58` with pinned `oasdiff 1.33.0`: PASS, no breaking changes.
- Focused Pest (`OpenApiSnapshotTest`, `AdminMfaTest`, `MfaRecoveryCodeConcurrencyTest`): PASS, 707 assertions and 16 environment warnings; SQLite skips the PostgreSQL-only concurrency assertion.
- Pint: PASS. PHPStan/Larastan level 10 with `APP_ENV=testing` and the temporary autoload bootstrap: PASS, no errors.
- Context validation (strict and examples), Python tooling tests (113), CSD doctor, and `git diff --check`: PASS. The examples validator reports that no example suite is installed.
- Direct Scramble exports report two warnings while evaluating `RenameRoleRequest::rules()` without a bound route model. Exports are byte-identical and the CI freshness and compatibility checks pass.
- No Composer installation or local `.env` was created. PostgreSQL Compose tests were not run locally; PR CI remains responsible for PostgreSQL 18 coverage.
