# Release — BUG-39

## Target and approval

- Target: existing PR #82, `feat/slice-4-self-service-profile` → `main`, remote `origin`.
- Approval: explicit user authorization for the deterministic local contract snapshot commit and rewrite/force-push with lease. No other PR and no merge are authorized.
- Production deployment: none; the PR remains subject to normal review and CI.

## Readiness

- OpenAPI export, lint, ScaffoldTest (SQLite scope), strict context validation, and history grep evidence are recorded in [verification.md](verification.md).
- The original fixture is absent from every commit reachable from the rewritten local branch.
- The force push succeeded with `--force-with-lease` tied to the expected remote SHA `4544d4f3f44d72d47dc49474af1e01e9d9c1142c`.

## Post-push check

Post-force-push `git ls-remote` confirmed both `refs/heads/feat/slice-4-self-service-profile` and `refs/pull/82/head` at `c143715d9096853bb8e70799e5d7478bdc596a9c`. A fresh GitHub API read confirmed PR #82 open and unmerged, with base `main`. This release record is delivered afterward as a documentation-only normal fast-forward commit to the same branch; it does not change application code or the snapshot. No deployment smoke check applies before merge.

## Observation and rollback

- Production observation window and operational owner: NOT FOUND; no production change is part of this task.
- Stop without retry if the PR state or remote SHA differs from the preflight, or if the lease fails.
- If the resulting PR head is not the reviewed local HEAD, stop and request owner direction. No merge or dashboard incident action is authorized.
