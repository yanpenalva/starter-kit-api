# Slice 9 — OpenAPI collection envelopes and nullables

## Intake and scope

- Parent work: FEAT-11 / API #11 (completed 2026-10-06); this records a user-directed contract completeness increment without reopening or changing the completed parent status.
- Track/type: product / feature. Owner: yanpenalva. Risk: medium. Profile: senior-software-engineer.
- Delivery: `feat/slice-9-openapi-envelopes` → `main`, remote `origin`, EXC-0001; branch starts at `origin/main` `98cf64147080c03e17c14d664ac6ac08231fcb02`.
- Outcome: generated OpenAPI preserves the user-role item shape from the baseline while adding optional pagination envelope fields and documenting nullable date-time fields.
- In scope: Scramble document transformation, focused generated-document tests, contract documentation and this evidence record.
- Acceptance: `UserResource.roles.items` remains the baseline object with required `relations` in every user response; GET users, roles and activity-log responses specify required `data` and optional `links`/`meta`; pagination link/meta members match Laravel output; nullable resource timestamps are `string|null` with `date-time`; user CPF remains optional and nullable.
- No frontend changes, auth transport changes, runtime response changes, or compatibility-policy changes.

## Plan and gates

1. Add focused OpenAPI export assertions and observe failure against the current generated document.
2. Extend the Scramble transformer for paginated list envelopes and date-time metadata, then rerun the focused contract test.
3. Run relevant SQLite Pest, Pint and PHPStan; validate the changed context documentation.
4. Commit and push the validated feature branch, update existing PR #87, and do not create another PR or merge.

## Snapshot policy and execution

The local export is the candidate snapshot for this branch, following the procedure read from `origin/feat/slice-12-contract-release-docs:docs/contract-release.md`. The official artifact remains a separate post-integration producer output from `main`; local export does not claim its provenance. The current request explicitly authorizes this candidate snapshot update, push, and update to existing PR #87; no other PR and no merge are authorized.

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

## Compatibility follow-up (2026-10-12)

- The focused test now requires only `data` at the collection-envelope root. Red run in the isolated worktree failed as expected: the export still marked `links` and `meta` required. The transformer now adds both properties without adding them to the root `required` list.
- `oasdiff 1.33.0` against `origin/main` `98cf64147080c03e17c14d664ac6ac08231fcb02` reports 12 errors across six user responses, rather than three list endpoints. Each response changes `data/items/roles/items` (or `data/roles/items`) from `object` to `string` and removes the stale required nested property `relations`.
- This contract correction is intentional and unavoidable without changing runtime JSON: `app/Http/Resources/Users/UserResource.php` already emits `roles` from `getRoleNames()` as a list of strings, while the baseline OpenAPI snapshot incorrectly described each item as an object containing `relations`. No runtime payload was changed. These 12 baseline findings remain for review and are not represented as additive.
- `links` and `meta` remain in the generated contract as optional root properties; the API currently emits them. The oasdiff failure is limited to the existing `UserResource.roles` mismatch.

## Additive compatibility decision (2026-10-13)

- User decision: this slice is additive against the baseline. Preserve `UserResource.roles.items` as the baseline object with its required `relations` field; do not publish the `string` item schema from the earlier candidate. Keep `links` and `meta` as optional root properties, with only `data` required.
- The focused export test first failed against the string-item transform and passed after `fixResourceArraySchemas` stopped overriding `UserResource.roles`; the Scramble-generated baseline object shape is retained by the transformer.
- This work preserves the published OpenAPI shape and does not change runtime response code. The earlier 12 `oasdiff` findings are superseded by this decision and must be clear in the final comparison.
- `origin/main` was integrated with a merge commit before implementation. Delivery updates existing PR #87 only; do not create another PR or merge it.

## Verification after additive decision (2026-10-13)

- Main integration: merge commit `0bb0b82577a9d991b1f020a9f6d1a5c823262a9a` includes `origin/main` at `4a8531661b73d22c3dfac932e65faa4c0b936f58` (PRs #79, #83 and #86).
- TDD: `OpenApiResponseShapeTest` failed while the transformer forced role items to strings; it passed after the override was removed and the test asserted the baseline object plus required `relations`.
- Deterministic candidate export: two `LC_ALL=C` Scramble exports on the merged source compared byte-identically with `cmp`; the committed candidate snapshot has SHA-256 `88bcccf993dd74915da9db2dd1a4eaaba1f4354b616054fe8b74c70042a3fb66`.
- Contract tests: `OpenApiResponseShapeTest` and `OpenApiSnapshotTest` passed, 3 tests / 726 assertions.
- Full Pest on the documented local PostgreSQL 17 profile: 313 passed / 2,917 assertions. The configured Docker/PostgreSQL 18 CI gate remains authoritative.
- `composer lint`: passed. PHPStan Level 10 direct invocation with the worktree autoload bootstrap: passed, no errors.
- `python3 scripts/validate_context.py --strict --examples`: passed (the repository has no example suite); CSD doctor 1.2.1 passed.
- `python3 -m unittest discover -s tests -v`: 113 tests ran but 7 failed because the pinned `oasdiff` executable is absent on this ARM64 host. GitHub CI installs the pinned Linux AMD64 executable; the post-push CI compatibility result is still required before handoff.
- The vendor symlink was retained; a temporary autoload bootstrap pointed local verification at this worktree without running Composer install.
