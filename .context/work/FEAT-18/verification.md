# Verification — FEAT-18 role detail and rename slice

## Evidence

- `source /opt/data/php/env.sh && PATH=/opt/data/home/.local/bin:$PATH APP_BASE_PATH=/opt/data/cache/scratch/api-fixwork4 APP_ENV=testing composer test -- --configuration=/tmp/phpunit-api-fixwork4-20261012.xml tests/Feature/Admin/CustomRoleManagementTest.php tests/Feature/Admin/RolesAdminHttpTest.php tests/Feature/Actions/RolesActionsTest.php` — exit 0; 30 passed, 125 assertions, SQLite in-memory. The temporary bootstrap maps `App`, `Tests`, factories and seeders to this worktree because the existing `vendor` symlink resolves to another checkout.
- `source /opt/data/php/env.sh && composer lint` — exit 0; Pint passed.
- `source /opt/data/php/env.sh && APP_BASE_PATH=/opt/data/cache/scratch/api-fixwork4 APP_ENV=testing composer run analyse -- --autoload-file=/tmp/api-fixwork4-autoload-20261012.php` — exit 0; Larastan level 10, 131 files, no errors.
- `source /opt/data/php/env.sh && composer validate --strict` — exit 0.
- `source /opt/data/php/env.sh && composer check-platform-reqs` — exit 0; PHP 8.5.11 and required extensions available.
- `source /opt/data/php/env.sh && composer audit` — exit 0; no security advisories.
- `python3 scripts/validate_context.py --strict` — exit 0.
- `python3 scripts/validate_context.py --strict --examples` — exit 0; warns that no example suite is installed.
- `PATH=/tmp/api-fixwork4-arm-bin-20261012:$PATH python3 -m unittest discover -s tests -v` — exit 0; 113 passed. Used the official `oasdiff` v1.33.0 Linux ARM64 release, checksum-verified and kept under `/tmp` ([release](https://github.com/oasdiff/oasdiff/releases/tag/v1.33.0)).
- `npx --yes @owlcodium/context-spec-develop@1.2.1 doctor --scope project` — exit 0; CSD healthy.
- `git diff --check` — exit 0.

## Limitations

- A full suite run with the SQLite override exited 2 (57 failed, 223 passed, 1,966 assertions). Several tests require PostgreSQL-specific settings and services; for example, `UserSchemaTest` asserts the configured PostgreSQL database name. The focused role suite passed on SQLite.
- PostgreSQL 18/Redis Compose tests were not run locally: the task forbids Composer installation, and the Compose test image build invokes `composer install`. CI remains the PostgreSQL integration gate.

## Review

- Permission policy is evaluated after the role is locked inside `PrivilegeTransaction`; a focused test asserts the transaction is active at the policy call, and a direct-action test proves admin role permissions remain unchanged.
- Rename uniqueness is rechecked inside the transaction and a database unique-index violation is converted to the `name` validation error (422); tests cover direct duplicate calls and a collision after the transactional check.
- All role audit callers pass allowlisted action codes; the rename HTTP test verifies `role.rename` persists and returns successfully.
- Independent review: NOT FOUND; this is the author review only.
