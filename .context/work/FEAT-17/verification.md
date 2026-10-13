# Verification — FEAT-17 correction

## TDD

- Red, against the original tracked `StoreUserRequest` and `RegisterController`: focused SQLite tests exited 1 with 3 expected failures. Missing internal roles returned 201 instead of 422; both public registration assertions found no `user` role.
- Green, worktree-aware bootstrap, SQLite: `UsersAdminHttpTest.php`, `PublicIdentityTest.php`, and `AdminContractTest.php` passed — 74 tests, 456 assertions, exit 0. The empty PUT assignment case remains covered and returns 422.

## Local gates

| Gate | Command/environment | Result |
| --- | --- | --- |
| Focused Pest | PHP 8.5.11, SQLite in-memory, worktree-aware `/tmp/phpunit-sp-api-b5.sqlite.xml` | Exit 0; 74 passed, 456 assertions |
| Pint | `composer lint`, PHP 8.5.11 | Exit 0 |
| Larastan | `APP_ENV=testing MAIL_MAILER=array DB_CONNECTION=sqlite DB_DATABASE=:memory: composer analyse`; `phpstan.neon` level 10 | Exit 0; 153 files, no errors |
| CSD strict | `python3 scripts/validate_context.py --strict` | Exit 0 |
| CSD strict examples | `python3 scripts/validate_context.py --strict --examples` | Exit 0; warns no example suite is installed |
| Python tooling | `PATH=/tmp/api-fixwork4-arm-bin-20261012:$PATH python3 -m unittest discover -s tests -v` with available `oasdiff 1.33.0` | Exit 0; 117 passed |
| CSD doctor | `npm_config_yes=true npx @owlcodium/context-spec-develop@1.2.1 doctor --scope project` | Exit 0; healthy |
| Diff whitespace | `git diff --check` | Exit 0 |

The first Python tooling run exited 1 because `oasdiff` was not on `PATH`; after exposing the existing local Linux ARM64 v1.33.0 binary, all 117 tests passed. Docker is installed but its daemon is unavailable, so the canonical Compose/PostgreSQL 18 suite was not run; the requested SQLite tests and local gates ran directly.

## Contract checks

- Existing catalog evidence: `Role::User` is provisioned by `RbacCatalogueSeeder`, sanctioned in FEAT-6, and has no permissions.
- No role is created by the registration flow. The PUT request remains unchanged and requires at least one role.
- `contracts/openapi.json` was not modified; its producer is restricted to `main`. The runtime behavior is documented in `docs/03-arquitetura.md`.
