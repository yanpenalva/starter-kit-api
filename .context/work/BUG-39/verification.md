# Verification — BUG-39

Date: 2026-10-13. Branch: `feat/slice-4-self-service-profile` → `main`, remote `origin`, existing PR #82 only.

## Rewritten history and snapshot

- Original commit `db5c8c1261f88078f4d48a492d97b900205e3354` was rewritten as `f0ab37d`.
- Original flagged commit `7d34e0373ebbbfe16568acaf96321d569a069875` was rewritten as `b1759e9`.
- Rebase used `-i --rebase-merges`; merge topology is preserved. The feature code/test tree matches the prior PR head (`git diff --quiet origin/feat/slice-4-self-service-profile HEAD -- app tests` exited 0).
- Snapshot commit: `e2dadd0` (`docs(api): refresh self-service profile snapshot`). Exported file: 93,444 bytes, SHA-256 `0bb27eec2cf1634161856e8df7f47d6f4b445f02a00f2fa4305070bb89bf446f`; Scramble 0.13.47.
- A dynamic `git grep -F` search extracted the original fixture from the pre-rewrite commit and checked all 200 commits reachable from the rewritten branch. Result: no match; both original commit IDs are absent from the branch history.

## Gates

| Gate | Command/environment | Result |
| --- | --- | --- |
| Deterministic export | `source /opt/data/php/env.sh`; isolated validation copy; `python3 scripts/contract_export.py --output=...`; `cmp` both export passes and compare the result to the committed snapshot | PASS, exit 0; identical bytes and matching SHA-256 above |
| Lint | `source /opt/data/php/env.sh && composer lint` | PASS, Pint passed, exit 0 |
| ScaffoldTest | Isolated copy with a temporary local-path SQLite config; `php vendor/bin/pest --configuration phpunit.validation.xml tests/Feature/ScaffoldTest.php --filter='^(?!.*test_postgresql_connection_uses_an_isolated_test_database)' --compact` | PASS, 12 passed / 261 assertions, exit 0 |
| Context | `python3 scripts/validate_context.py --strict --examples` | PASS, exit 0; validator warns that no example suite is installed |
| Diff/history | `git diff --check`; dynamic `git grep -F` over `git rev-list HEAD` | PASS; no whitespace errors and no original fixture in reachable history |

The existing `vendor` symlink was preserved and no `composer install` ran. Dependencies were copied from that symlink into the temporary validation copy, where `composer dump-autoload --no-scripts` generated an isolated autoloader. The checked-in `phpunit.sqlite.xml` points to a different checkout, so only its absolute bootstrap/test paths were changed in a temporary config. Docker exists but `docker compose` is unavailable here; the PostgreSQL-only ScaffoldTest was excluded from the SQLite run. A full SQLite run fails only that PostgreSQL connection assertion (`sqlite` versus expected `pgsql`).

## Delivery state

`git push --force-with-lease=refs/heads/feat/slice-4-self-service-profile:4544d4f3f44d72d47dc49474af1e01e9d9c1142c origin HEAD:refs/heads/feat/slice-4-self-service-profile` succeeded. At the post-force check, the branch ref and `refs/pull/82/head` both pointed to `c143715d9096853bb8e70799e5d7478bdc596a9c`; a fresh GitHub API read confirmed PR #82 open, unmerged, and targeting `main`. This delivery record is carried in a documentation-only commit sent by normal fast-forward to the same branch. No merge or other PR was performed.

## PR #89 main synchronization

- User instruction: merge the fetched `origin/main` into this branch with a normal merge; do not rewrite history, create another PR or merge PR #82.
- Preflight: PR #82 was open and unmerged, based on `main`, with branch and PR refs at `ac8b82fee8d08e9567af2548e61a4d5609fa1808`. `origin/main` was `20a14f01bc70e4b9c84116d1de7dabdc6a8d29b0`.
- Merge commit: `21a9ed1f007ae46f55ae4faf9756bdd6dac6e165` (`merge: sync origin/main into self-service profile`). Resolved `routes/api/auth.php` and `tests/Feature/ScaffoldTest.php` by retaining both profile/password routes and active-session routes, with inventory and method assertions for both.
- Snapshot: the merge's `contracts/openapi.json` matched a two-pass `dedoc/scramble 0.13.47` export byte for byte; no regeneration or snapshot edit was needed. Export SHA-256 `1a68e9286b960cc906cbc05557aa139eb7c7772f2ec7dfdd06b466c72900ff4e`, 120,269 bytes.
- Lint: `source /opt/data/php/env.sh`, with the local PHP wrapper directories added to `PATH`, then `composer lint` — PASS, Pint passed.
- Secret and merge checks: `git grep` credential-pattern scan of tracked files and merge additions found no matches; conflict markers absent; `git diff --check` passed.
- `vendor` remains the original symlink to `/opt/data/work/starter-kit-api/vendor`; no `composer install` ran. The API test and analysis gates will be reported from CI on the pushed PR head.
