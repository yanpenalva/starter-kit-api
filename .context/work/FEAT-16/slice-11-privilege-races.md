# Slice 11 — current authority for privileged mutations

## Intake and scope

- Parent work: FEAT-16 (RBAC policies) and FEAT-19 (`PrivilegeTransaction`); this records the requested privilege-race follow-up without reopening either parent.
- Track/type: product / feature. Owner: yanpenalva. Risk: high. Phase: verify.
- Classification: complexity high, impact cross-module, security sensitive, confidence high; routing derived/effective extended, no override.
- Delivery: `feat/slice-11-privilege-races` → `main`, remote `origin`, EXC-0001; branch starts at `origin/main` `98cf64147080c03e17c14d664ac6ac08231fcb02`.
- Outcome: every privileged write rechecks the current actor and relevant policy after the shared transaction lock is held, so an in-flight request cannot use a revoked permission or stale loaded role relation.
- Scope: the existing user and role mutation Actions, their focused tests, architecture documentation, and this evidence record. No route, response, token, frontend, migration, or OpenAPI changes.
- Acceptance: tests begin with a stale actor whose permission was revoked in persistence; each mutation rejects with HTTP 403 and leaves its target unchanged. A PostgreSQL test races the real user-status and role-permission Actions through separate connections, preserving the last admin and the grant ceiling, and verifies mutation rollback when audit insertion fails. Existing authorized action and HTTP tests continue to pass.

## Plan and gates

1. Add stale-actor regression tests for each user/role write action and observe them fail on `origin/main`.
2. Resolve the actor under `PrivilegeTransaction`'s lock and repeat the action policy check inside that transaction.
3. Run relevant SQLite Pest, Pint, PHPStan, strict context validation, and `git diff --check`; record PostgreSQL-only limitations.
4. Commit and push the feature branch; do not open a PR or merge.

## Execution evidence

- TDD red: on a detached `origin/main` worktree at `98cf641`, the focused 8-test regression failed in all 8 cases because each stale actor still completed its mutation. The worktree was removed after the run.
- `source /opt/data/php/env.sh && vendor/bin/pest -c /tmp/phpunit.sqlite.xml tests/Feature/Actions/PrivilegeRaceTest.php tests/Feature/Actions/UsersActionsTest.php tests/Feature/Actions/RolesActionsTest.php tests/Feature/Admin/UsersAdminHttpTest.php tests/Feature/Admin/RolesAdminHttpTest.php`: 67 passed / 321 assertions.
- `composer lint`: passed (Pint).
- `composer analyse`: passed (PHPStan Level 10, 128 files, no errors).
- `python3 scripts/validate_context.py --strict`: passed.
- `git diff --check`: passed.
- Slice-11 concurrency regression (2026-10-12): `PrivilegeMutationPostgresRaceTest` starts an isolated worker process with its own PostgreSQL connection, synchronizes both real Actions at the advisory lock, and checks last-admin preservation, the permission grant ceiling and audit-failure rollback. The implementation clears the permission cache under the shared lock before rechecking the current actor, so a worker cannot authorize from stale cached permissions.
- `source /opt/data/php/env.sh && /opt/data/php/root/usr/bin/php8.5 vendor/bin/pest -c /tmp/phpunit.sqlite.api-fixwork6.xml tests/Feature/Actions/PrivilegeMutationPostgresRaceTest.php tests/Feature/Actions/PrivilegeRaceTest.php tests/Feature/Actions/UsersActionsTest.php tests/Feature/Actions/RolesActionsTest.php tests/Feature/PrivilegeFoundationTest.php`: 42 passed / 136 assertions; the PostgreSQL-only test skipped on SQLite.
- `source /opt/data/php/env.sh && DB_HOST=127.0.0.1 DB_PASSWORD=ci-ephemeral-test-only /opt/data/php/root/usr/bin/php8.5 vendor/bin/pest -c /tmp/phpunit.pgsql.api-fixwork6.xml tests/Feature/Actions/PrivilegeMutationPostgresRaceTest.php`: 1 passed / 39 assertions on local PostgreSQL 17. CI's PostgreSQL 18 run remains separate.
- `composer lint`: passed (Pint).
- The `composer analyse` wrapper hit its 300-second process timeout at 19/129 files; the complete PHPStan Level 10 analysis was then run directly with `--debug` and the scratch-specific config below.
- PHPStan level 10 over 129 files: passed with no errors using the scratch-specific configuration and `--debug` to keep analysis workers attached to this worktree.
- `python3 scripts/validate_context.py --strict`, `python3 scripts/validate_context.py --strict --examples` and `npx @owlcodium/context-spec-develop@1.2.1 doctor --scope project`: passed; `--examples` reported that no example suite is installed.
- `python3 -m unittest discover -s tests -v`: 113 tests, 2 failures and 5 errors because `oasdiff` is absent from this local environment; these are contract-tool checks outside this slice.
- `git diff --check`: passed.
- Delivery: implementation commit `2765b36` was pushed to `origin/feat/slice-11-privilege-races`; no PR or merge was created.
- Production deployment and issue closure were not performed.

## Final worker verification

- The portable worker uses Symfony Process instead of `pcntl`; the test confirms distinct PostgreSQL backend PIDs and waits for the real competing Action to reach the advisory lock. Its trigger matches a unique `application_name` set by this test's parent connection, so unrelated lock waiters cannot release the barrier.
- PostgreSQL 17 focused run: `source /opt/data/php/env.sh && REDIS_HOST=127.0.0.1 REDIS_QUEUE=testqueue DB_HOST=127.0.0.1 DB_PASSWORD=ci-ephemeral-test-only MAIL_HOST=127.0.0.1 MAIL_PORT=1025 MAILPIT_API_URL=http://127.0.0.1:8025 APP_BASE_PATH=/opt/data/cache/scratch/api-fixwork6 php8.5 vendor/bin/pest -c /tmp/phpunit.pgsql.api-fixwork6.xml tests/Feature/Actions/PrivilegeMutationPostgresRaceTest.php`: 1 passed / 29 assertions.
- SQLite focused run with the same custom PHPUnit bootstrap: `source /opt/data/php/env.sh && REDIS_HOST=127.0.0.1 REDIS_QUEUE=testqueue APP_BASE_PATH=/opt/data/cache/scratch/api-fixwork6 php8.5 vendor/bin/pest -c /tmp/phpunit.sqlite.api-fixwork6.xml tests/Feature/Actions/PrivilegeMutationPostgresRaceTest.php tests/Feature/Actions/PrivilegeRaceTest.php tests/Feature/Actions/UsersActionsTest.php tests/Feature/Actions/RolesActionsTest.php tests/Feature/PrivilegeFoundationTest.php`: 42 passed / 136 assertions; 1 PostgreSQL-only skip.
