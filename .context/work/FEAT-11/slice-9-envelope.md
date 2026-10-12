# Slice 9 — OpenAPI collection envelopes and nullables

## Intake and scope

- Parent work: FEAT-11 / API #11 (completed 2026-10-06); this records a user-directed contract completeness increment without reopening or changing the completed parent status.
- Track/type: product / feature. Owner: yanpenalva. Risk: medium. Profile: senior-software-engineer.
- Delivery: `feat/slice-9-openapi-envelopes` → `main`, remote `origin`, EXC-0001; branch starts at `origin/main` `98cf64147080c03e17c14d664ac6ac08231fcb02`.
- Outcome: generated OpenAPI accurately describes the Laravel pagination envelope returned by list endpoints and the nullable date-time fields returned by user and audit resources.
- In scope: Scramble document transformation, focused generated-document tests, contract documentation and this evidence record.
- Acceptance: GET users, roles and activity-log responses specify required `data`, `links` and `meta`; pagination link/meta members match Laravel output; nullable resource timestamps are `string|null` with `date-time`; user CPF remains optional and nullable.
- No frontend changes, auth transport changes, runtime response changes, or compatibility-policy changes.

## Plan and gates

1. Add focused OpenAPI export assertions and observe failure against the current generated document.
2. Extend the Scramble transformer for paginated list envelopes and date-time metadata, then rerun the focused contract test.
3. Run relevant SQLite Pest, Pint and PHPStan; validate the changed context documentation.
4. Commit and push the validated feature branch; do not open a PR or merge.

## Snapshot constraint

The requested snapshot update remains part of acceptance, but `AGENTS.md` and `docs/onboarding.md` require `contracts/openapi.json` to come from the OpenAPI producer on `main` and prohibit using local export as its substitute. This branch cannot satisfy that condition because it is not integrated, and this task explicitly forbids PRs and merges. Do not change the snapshot here or claim it was updated. The canonical producer must export it after an authorized integration on `main`.

## Execution evidence

- TDD: before transformation, the focused generated-document test failed because the users collection lacked required `links`/`meta` fields; the final `OpenApiResponseShapeTest` and existing snapshot tests pass after the transformer change.
- `vendor/bin/pest -c /tmp/phpunit.sqlite.xml tests/Feature/Contract/OpenApiResponseShapeTest.php tests/Feature/Contract/OpenApiSnapshotTest.php tests/Feature/Admin/UsersAdminHttpTest.php tests/Feature/Admin/RolesAdminHttpTest.php tests/Feature/Admin/AuditFeedTest.php`: 53 passed / 1,004 assertions.
- `composer lint`: passed (Pint).
- `composer analyse`: passed (PHPStan Level 10, no errors).
- `python3 scripts/validate_context.py --strict`: passed.
- `git diff --check`: passed. `contracts/openapi.json` is unchanged; its requested regeneration is pending the canonical `main` producer after integration, which this task explicitly does not authorize.
- Git delivery: code commit `2bed058` was pushed to `origin/feat/slice-9-openapi-envelopes` successfully.
- Production deployment, PR, merge and issue closure were not performed.
