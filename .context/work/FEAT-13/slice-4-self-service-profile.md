# Slice 4 — Self-service profile: PR #82 follow-up

## Intake and scope

- Product / feature continuation of API #13; owner: yanpenalva; risk: medium; phase: execute/verify; profile: senior-software-engineer.
- Classification: complexity medium, impact cross-module, security sensitive, confidence high; routing derived/effective: extended. Required context: core, project, architecture, security, testing; incident and production release deferred.
- User authorized preserving and delivering the staged `StoreUser`, `User`, invitation/activation tests and ADR 0009 alongside the PR corrections. After fetching `origin/main`, an already-started merge was found; the staged result matched PR #81 and was finalized as merge commit `262d13a`.
- Delivery: `feat/slice-4-self-service-profile` → `main`, remote `https://github.com/yanpenalva/starter-kit-api.git`, existing [PR #82](https://github.com/yanpenalva/starter-kit-api/pull/82). Commit/push explicitly authorized; no new PR or merge. Standing exception: [EXC-0001](../../exceptions/EXC-0001.json).

## Plan and acceptance

1. Reproduce the stale ScaffoldTest inventory, include profile PATCH/password PUT, and assert their HTTP methods.
2. Replace the GitGuardian finding's fixture with a documented synthetic password, preserving the failed/current-password and successful-update checks.
3. Generate the candidate OpenAPI twice under `LC_ALL=C`, compare bytes, and version the matching snapshot; run ScaffoldTest, profile Pest, lint, analysis and relevant invitation/activation/contract tests.
4. Validate context, review the combined diff, commit intended files and push the existing PR branch.

## GitGuardian evidence

Check run `115048284837` reports incident `38141019`: Generic Password at `tests/Feature/Auth/ProfileTest.php:372` in commit `7d34e0373ebbbfe16568acaf96321d569a069875`. The occurrence is the password confirmation in a synthetic HTTP test, with the same fixture used for the request and hash assertion; it does not configure an external service. All occurrences of that fixture in the test are replaced with local variable `$newPassword`, set to `fake-profile-password-123` and documented inline as test-only. The dashboard incident state has not been changed; the previous commit remains in history.

## Execution environment and initial evidence

- Worktree: `/opt/data/cache/scratch/api-g4`; `vendor` remains an untracked symlink. Dependencies were copied read-only into `/opt/data/cache/scratch/opencode/api-g4-validation/vendor`, without Composer installation, to keep autoload/cache writes and source resolution isolated from the other checkout.
- PHP 8.5.11 enabled with `source /opt/data/php/env.sh`.
- Red: `vendor/bin/pest tests/Feature/ScaffoldTest.php --filter=test_only_the_public_surface_routes_are_registered` failed on the two missing inventory entries before the correction.
- The candidate procedure follows commit `33f2881`: local deterministic export is a review candidate, not the official post-integration producer artifact from `main`.

## Verification update — 2026-10-13

- Merge: finalized the pending `origin/main` merge at `262d13a`; no conflict remained. Existing unstaged changes in `ProfileTest.php` and `ScaffoldTest.php` were preserved. The original `vendor` symlink still points to `/opt/data/work/starter-kit-api/vendor` and was not replaced or used for validation; no Composer install ran.
- Lint: `source /opt/data/php/env.sh && composer lint` passed in a temporary source copy using the isolated vendor copy. The merge commit hook also passed Pint and `python3 scripts/validate_context.py --strict`.
- Scaffold: 12 tests passed (261 assertions) under SQLite, including the route inventory and PATCH/PUT method assertions. The PostgreSQL-only connection-isolation test was not run locally because Docker/Compose is unavailable.
- Profile: `tests/Feature/Auth/ProfileTest.php` passed under SQLite (19 tests, 93 assertions).
- OpenAPI candidate: two `scripts/contract_export.py` runs with `LC_ALL=C`, `TZ=UTC`, and Scramble 0.13.47 produced identical 93,444-byte files with SHA-256 `0bb27eec2cf1634161856e8df7f47d6f4b445f02a00f2fa4305070bb89bf446f`; candidate includes PATCH `/api/v1/auth/my-profile` and PUT `/api/v1/auth/password`. It differs from `contracts/openapi.json`. The repository requires the official snapshot producer on `main`, so the local candidate was not promoted; the official artifact and snapshot update remain pending post-integration.
- GitGuardian: the flagged password fixture is now a documented synthetic test value; the earlier commit remains in PR history and the dashboard incident state was not changed.
- Context gates: `validate_context.py --strict`, `--strict --examples`, and CSD doctor passed. Python unittest ran 113 cases but 2 failed and 5 errored because pinned `oasdiff` was unavailable; the repository installer verified its pinned amd64 binary, which cannot execute on this aarch64 host (`Exec format error`).
- Push: implementation commit `6ef6f8f` was pushed to existing PR #82; the PR remains open and unmerged. Checks for that head: `context`, `application` (including lint, analyse, and Pest), and coverage passed. Contract generation was deterministic, but the committed snapshot freshness check failed because the official snapshot does not yet contain these routes. GitGuardian also failed on its finding in historical commit `7d34e03`; remediation in the current tree does not remove that occurrence from the scanned PR history.

## Authorized remediation — BUG-39, 2026-10-13

This follow-up is tracked separately as [BUG-39](../BUG-39/work-item.json) because FEAT-13 is already closed. The user explicitly authorized local deterministic snapshot generation and a leased rewrite of the existing PR #82 branch; the scope and compensating controls are recorded in [EXC-0002](../../exceptions/EXC-0002.json). A history scan found the fixture in `db5c8c1` and `7d34e03`; both are being rewritten to use `fake-profile-password-123`. PR #82 was verified open and unmerged at `4544d4f3f44d72d47dc49474af1e01e9d9c1142c` before remediation. See BUG-39 verification for final results.
