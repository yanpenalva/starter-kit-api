# Verification — FEAT-28 — Slice 12

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

## Post-main integration validation — 2026-10-13

- `git fetch origin` — exit 0; `origin/main` was `4a8531661b73d22c3dfac932e65faa4c0b936f58`.
- `git merge origin/main` — exit 1 on automatic conflicts in API context, ADR index, and the duplicate FEAT-27 records; resolved by preserving current main's FEAT-27 session work, relocating Slice 12 to FEAT-28, and reconciling the API/ADR text against current source and workflows.
- `python3 scripts/validate_context.py --strict` — exit 0 (`Context validation passed.`) on the resolved merge worktree.
- Final run after the API/architecture/security/ADR/work-item reconciliation: `python3 scripts/validate_context.py --strict` — exit 0 (`Context validation passed.`).
- Scope: context, ADR, release-guide, and work-item documentation. No application behavior, workflow YAML, or OpenAPI snapshot was edited as part of conflict resolution.
- Independent reader review and remote CI are not claimed.
- `git diff --check HEAD` — exit 0.
- Merge commit `f5afe61cb115945240772cbbcd560c41e8919d84` — exit 0; the active pre-commit hook ran `composer lint` (Pint passed) and `python3 scripts/validate_context.py --strict` (passed).
- `git push origin feat/slice-12-contract-release-docs` — exit 0; fast-forwarded remote head from `11143c940d10d7ffb523e69a4fafc80ab03c92b0` to `f5afe61cb115945240772cbbcd560c41e8919d84`.
- `git ls-remote origin refs/heads/feat/slice-12-contract-release-docs` — exit 0; confirmed remote head `f5afe61cb115945240772cbbcd560c41e8919d84`.
- `curl -fsSL https://api.github.com/repos/yanpenalva/starter-kit-api/pulls/90` — exit 0; PR #90 is open, targets `main`, points to that head, and is not merged.

## Latest main sync — 2026-10-13

- `origin/main` was `9f3ee254a651dbd8cf61eb0fc7d0b65e928a0fa7`, including PR #89
  and PR #87. `git merge --no-edit origin/main` needed manual resolutions in
  `.context/project/api.md` and `docs/onboarding.md`; the API context, ADRs, and
  FEAT-28 now reflect the integrated behavior while preserving the canonical guide
  links.
- Merge commit: `9b19a17e20d92931f8fbe34fa68cbbbcadbbe940`.
- The `contracts/openapi.json` brought in from PR #87 was regenerated through
  `scripts/contract_export.py` using PHP 8.5 and a temporary migrated SQLite DB.
  Both `LC_ALL=C` exports matched; SHA-256 is
  `88bcccf993dd74915da9db2dd1a4eaaba1f4354b616054fe8b74c70042a3fb66`, identical
  to `origin/main`. No additional snapshot change was introduced.
- `python3 scripts/validate_context.py --strict` — passed.
- `python3 scripts/validate_context.py --strict --examples` — passed; warning that
  no example suite is installed.
- `python3 -m unittest discover -s tests/tools -p 'test_contract_release_docs.py' -v`
  — 4 tests passed.
- `npx --yes @owlcodium/context-spec-develop@1.2.1 doctor --scope project` — CSD
  healthy (1.2.1).
- Merge commit hook: `composer lint` (Pint) and strict context validation passed.
- `git diff --check HEAD` — passed.
- `python3 -m unittest discover -s tests -v` — 117 tests ran; 2 failures and 5
  errors in compatibility tests because pinned `oasdiff` is unavailable on this
  ARM64 host. This matches the documented local environment limitation.
- Composer analysis, Pest application tests, and remote CI were not rerun for this
  documentation sync. No official OpenAPI artifact was produced or published.
- Delivery remains limited to existing PR #90; it is not merged and no other PR is
  created.
