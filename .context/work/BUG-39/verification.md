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
