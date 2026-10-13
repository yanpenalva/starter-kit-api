# Reproduction — BUG-39

## Observed behavior

GitGuardian reports incident `38141019` (Generic Password) in `tests/Feature/Auth/ProfileTest.php:372` at commit `7d34e0373ebbbfe16568acaf96321d569a069875`. The current branch already uses the documented test-only value `fake-profile-password-123`, but the flagged value is also present in its ancestor `db5c8c1261f88078f4d48a492d97b900205e3354`, so both commits remain in the PR history until rewritten.

## Expected behavior

- The edited historical commit and the final PR branch history contain only the documented synthetic fixture for the password-change test.
- The regenerated OpenAPI snapshot matches two byte-identical local exports.
- PR #82 remains open, unmerged, and pointed at `feat/slice-4-self-service-profile` after the leased push.

## Steps and evidence

1. Inspect the two reachable commits containing the flagged fixture and identify it without printing it into logs.
2. Edit both commits during interactive rebase; preserve merge topology and use `fake-profile-password-123` as documented in FEAT-13 slice 4.
3. Search all commits reachable from the rewritten branch for the original value and require no matches.
4. Verify PR #82 is still open and unmerged before pushing.

## Scope

- Affected files: `tests/Feature/Auth/ProfileTest.php`, `contracts/openapi.json`, and this work item's evidence.
- No API behavior or database schema change.
- No other branch, PR, merge, or GitGuardian dashboard action is in scope.
