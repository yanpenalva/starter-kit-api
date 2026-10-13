# Release — BUG-39

## Target and approval

- Target: existing PR #82, `feat/slice-4-self-service-profile` → `main`, remote `origin`.
- Approval: explicit user authorization for the deterministic local contract snapshot commit and rewrite/force-push with lease. No other PR and no merge are authorized.
- Production deployment: none; the PR remains subject to normal review and CI.

## Readiness

- OpenAPI export, lint, ScaffoldTest (SQLite scope), strict context validation, and history grep evidence are recorded in [verification.md](verification.md).
- The original fixture is absent from every commit reachable from the rewritten local branch.
- The push has not yet been attempted. Expected remote branch SHA for the lease: `4544d4f3f44d72d47dc49474af1e01e9d9c1142c`.

## Post-push check

After the leased push, verify through `git ls-remote` and PR #82 metadata that the branch points to the pushed HEAD, the PR remains open and unmerged, and the base remains `main`. No deployment smoke check applies before merge.

## Observation and rollback

- Production observation window and operational owner: NOT FOUND; no production change is part of this task.
- Stop without retry if the PR state or remote SHA differs from the preflight, or if the lease fails.
- If the resulting PR head is not the reviewed local HEAD, stop and request owner direction. No merge or dashboard incident action is authorized.
