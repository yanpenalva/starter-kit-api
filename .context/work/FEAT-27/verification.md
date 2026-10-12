# Verification — FEAT-27 — Slice 12

## TDD

In a detached checkout of the recorded `origin/main`, the focused documentation
check exited 1 because the release guide and vertical-slice guide/links were absent.
After the guides were added and linked, all four focused tests passed.

## Passed

- `python3 -m unittest discover -s tests/tools -p 'test_contract_release_docs.py' -v` — 4 tests passed.
- `python3 scripts/validate_context.py --strict` — passed.
- `python3 scripts/validate_context.py --strict --examples` — passed; expected warning because no example suite is installed.
- `source /opt/data/php/env.sh && /opt/data/php/root/usr/bin/php8.5 /opt/data/php/composer lint` — Pint passed.
- `source /opt/data/php/env.sh && /opt/data/php/root/usr/bin/php8.5 /opt/data/php/composer analyse` — PHPStan level 10 passed, 127 files, no errors.
- SQLite Pest, using temporary PHPUnit config with `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`: `/opt/data/php/root/usr/bin/php8.5 vendor/bin/pest --configuration=/tmp/phpunit-slice12-sqlite.xml tests/Feature/Contract/OpenApiSnapshotTest.php` — 2 tests passed, 549 assertions.
- `npx --yes @owlcodium/context-spec-develop@1.2.1 doctor --scope project` — CSD healthy (1.2.1).
- `python3 scripts/contract_cli.py --help` — passed; CLI options match the documented invocation.
- `git diff --check` — passed.

## Environment limitation

- python3 -m unittest discover -s tests -v ran 117 tests but ended with 2 failures and 5 errors in compatibility checks that require oasdiff. The executable is absent from PATH on this aarch64 host.
- The full Composer/Pest CI suite was not run locally because the canonical test profile requires PostgreSQL 18, Redis, and Mailpit. The relevant contract Pest subset passed with SQLite.
- No remote CI result is claimed.

## Scope

No application behavior, workflow YAML, frontend, dependency manifest, or contracts/openapi.json changed. No contract artifact was generated or published.

## Follow-up review verification — 2026-10-12

- Inspected `.github/workflows/quality.yml`: the contract job runs for pull requests
  and pushes to main, exports twice, and compares the result to the snapshot in the
  checked-out revision.
- Inspected `.github/workflows/openapi.yml`: `OpenAPI Contract` is manual and its
  guard rejects refs other than `main`; it uploads an artifact and does not commit
  the snapshot.
- Cross-checked the release guide, ADR 0012, onboarding, vertical-slice recipe, and
  provenance recipe for consistent candidate-snapshot and official-artifact order.
- Inspected `PrivilegeTransaction::runWithActor` on
  `origin/feat/slice-11-privilege-races` and `RenameRole` on
  `origin/feat/slice-8-custom-role-renaming`: `RenameRole` calls `run`, locks the
  role target, and authorizes the supplied actor without reloading it.
- `python3 scripts/validate_context.py --strict` — passed.
- `git diff --check` — passed.
