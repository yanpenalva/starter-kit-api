# Slice 11 — current authority for privileged mutations

## Intake and scope

- Parent work: FEAT-16 (RBAC policies) and FEAT-19 (`PrivilegeTransaction`); this records the requested privilege-race follow-up without reopening either parent.
- Track/type: product / feature. Owner: yanpenalva. Risk: high. Phase: execute.
- Classification: complexity high, impact cross-module, security sensitive, confidence high; routing derived/effective extended, no override.
- Delivery: `feat/slice-11-privilege-races` → `main`, remote `origin`, EXC-0001; branch starts at `origin/main` `98cf64147080c03e17c14d664ac6ac08231fcb02`.
- Outcome: every privileged write rechecks the current actor and relevant policy after the shared transaction lock is held, so an in-flight request cannot use a revoked permission or stale loaded role relation.
- Scope: the existing user and role mutation Actions, their focused tests, architecture documentation, and this evidence record. No route, response, token, frontend, migration, or OpenAPI changes.
- Acceptance: tests begin with a stale actor whose permission was revoked in persistence; each mutation rejects with HTTP 403 and leaves its target unchanged. Existing authorized action and HTTP tests continue to pass.

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
- PostgreSQL concurrency proof is covered by existing `CorrelationRedactionTest::test_concurrent_admin_mutations_serialize_through_the_global_lock`; this local environment has SQLite only, so the PostgreSQL test was not run locally.
- Delivery: implementation commit `2765b36` was pushed to `origin/feat/slice-11-privilege-races`; no PR or merge was created.
- Production deployment and issue closure were not performed.
