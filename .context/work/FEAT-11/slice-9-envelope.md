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

## Snapshot policy and execution

The local export is the candidate snapshot for this branch, following the procedure read from `origin/feat/slice-12-contract-release-docs:docs/contract-release.md`. The official artifact remains a separate post-integration producer output from `main`; local export does not claim its provenance. This supersedes the earlier producer-only note in this record. The current request explicitly authorized the candidate snapshot update and prohibited PRs and merges.

- Snapshot commit already present on the branch and remote before this verification: `3d20590a582e4f04ff36378ea8563017e7dbd3c2`.
- Two candidate exports on that exact source used PHP 8.5.11, a temporary SQLite database migrated from this revision, and separate `LC_ALL=C php artisan scramble:export` invocations targeting `storage/app/openapi-pass1.json` and `storage/app/openapi-pass2.json` in an isolated copy because the worktree's `vendor` symlink points to another checkout.
- Both exports have SHA-256 `249c1385f63a974320528e3cc1b16876f410d00ff6de0b16cdd801775cf2c968`; `cmp` passed between both exports and against the committed snapshot.
- The official OpenAPI artifact from `main` was not produced or reviewed; no PR or merge was created.

## Execution evidence

- TDD: before transformation, the focused generated-document test failed because the users collection lacked required `links`/`meta` fields; the final `OpenApiResponseShapeTest` and existing snapshot tests pass after the transformer change.
- `vendor/bin/pest -c /tmp/phpunit.sqlite.xml tests/Feature/Contract/OpenApiResponseShapeTest.php tests/Feature/Contract/OpenApiSnapshotTest.php tests/Feature/Admin/UsersAdminHttpTest.php tests/Feature/Admin/RolesAdminHttpTest.php tests/Feature/Admin/AuditFeedTest.php`: 53 passed / 1,004 assertions.
- `composer lint`: passed (Pint).
- `composer analyse`: passed (PHPStan Level 10, no errors).
- `python3 scripts/validate_context.py --strict`: passed.
- The earlier no-snapshot note is superseded by the candidate export evidence above.
- Focused contract tests in the isolated SQLite copy (`OpenApiResponseShapeTest` and `OpenApiSnapshotTest`): 3 passed, 677 assertions, no warnings.
- `python3 scripts/validate_context.py --strict`: passed after the evidence and onboarding updates.
- Git delivery: code commit `2bed058` was pushed to `origin/feat/slice-9-openapi-envelopes` successfully.
- Production deployment, PR, merge and issue closure were not performed.
