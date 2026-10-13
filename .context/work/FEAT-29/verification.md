# Verification — FEAT-29

## TDD evidence

- Before the filter implementation, the new actor-name test returned all 3 fixture rows instead of 1; the 256-character `search` request returned 200 instead of 422. Both failures were observed on SQLite.
- After implementation, the focused SQLite suite passed.

## Commands and results

| Check | Command / environment | Result |
| --- | --- | --- |
| Focused SQLite tests | PHP 8.5.11; `APP_ENV=testing`, `MAIL_MAILER=array`; isolated `/tmp/phpunit-w2.sqlite.xml`; `vendor/bin/pest` on `UsersAdminHttpTest`, `SessionProfileRefreshTest`, `AuditFeedTest`, `FiltersTest`, `AdminContractTest`, `OpenApiResponseShapeTest` | Exit 0; 70 passed, 603 assertions |
| Focused PostgreSQL driver tests | Local PostgreSQL 17 test database; same audit/filter tests | Exit 0; 17 passed, 165 assertions |
| Pint | `composer lint`, PHP 8.5.11 | Exit 0; Pint passed |
| PHPStan | `composer analyse`, PHP 8.5.11, Larastan level 10 | Exit 0; no errors |
| Context | `python3 scripts/validate_context.py --strict` | Exit 0 |
| Candidate export | `LC_ALL=C php artisan scramble:export --path=contracts/openapi.json` using the worktree-aware PHP 8.5 bootstrap and migrated temporary SQLite database | Exit 0; candidate includes `search` as nullable string with `maxLength: 255` |
| Determinism check | Second C-locale export to `/tmp/feat-29-openapi-second.json`, compared with the candidate using `cmp -s` | Exit 0; byte-identical |

The Scramble export reports two existing warnings for route-dependent validation in `RenameRoleRequest`; neither concerns the audit request. The full `composer test` suite is intentionally left to CI PostgreSQL 18 as requested.

## Reproduction commands

```sh
APP_ENV=testing MAIL_MAILER=array php8.5 vendor/bin/pest -c /tmp/phpunit-w2.sqlite.xml \
  tests/Feature/Admin/UsersAdminHttpTest.php \
  tests/Feature/Auth/SessionProfileRefreshTest.php \
  tests/Feature/Admin/AuditFeedTest.php \
  tests/Feature/Support/FiltersTest.php \
  tests/Feature/Admin/AdminContractTest.php \
  tests/Feature/Contract/OpenApiResponseShapeTest.php
APP_ENV=testing MAIL_MAILER=array composer lint
APP_ENV=testing MAIL_MAILER=array composer analyse
python3 scripts/validate_context.py --strict
LC_ALL=C php artisan scramble:export --path=contracts/openapi.json
```

The local PHP wrappers provide PHP 8.5.11 and the required `LD_LIBRARY_PATH`/extension configuration. The focused test XML and OpenAPI bootstrap under `/tmp` point Composer's shared dependencies at this worktree; no repository runtime configuration was changed.

## Scope evidence

- Audit search matched one-letter terms against actor name, the event serialized as description, and action; mixed-case matching, action-filter composition, wildcard escaping, max length, `logs.view`, and no added name/email/raw-description exposure were verified.
- The two preserved multi-role tests passed, including replacement and the profile permission union. No role-policy or schema code changed.
- `contracts/openapi.json` changes only the audit `search` query parameter; the official artifact remains a main-only workflow output.

## Remaining verification

- CI PostgreSQL 18 full suite and contract freshness/compatibility gates.
- Frontend mock/UI integration and end-to-end session smoke are outside this backend task.
- GitHub Issue association is NOT FOUND; no external issue was created.
