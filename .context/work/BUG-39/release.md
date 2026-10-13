# Release — BUG-39

## Target and approval

- Target: existing PR #82, `feat/slice-4-self-service-profile` → `main`, remote `origin`.
- Original BUG-39 approval: explicit user authorization for the deterministic local contract snapshot commit and rewrite/force-push with lease. That request authorized no other PR and no PR merge.
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
- If the resulting PR head is not the reviewed local HEAD, stop and request owner direction. No PR merge or dashboard incident action is authorized.

## Follow-up: sync `origin/main` after PR #89 (2026-10-13)

- The user explicitly authorized fetching and normally merging `origin/main` into this existing PR branch, resolving conflicts, and pushing only PR #82. The user explicitly prohibited rewriting history, opening another PR, and merging PR #82.
- Preflight confirmed PR #82 was open, unmerged, based on `main`, and at `ac8b82fee8d08e9567af2548e61a4d5609fa1808`; both the branch ref and `refs/pull/82/head` matched that SHA.
- `origin/main` was `20a14f01bc70e4b9c84116d1de7dabdc6a8d29b0` (merge of PR #89). The normal merge commit is recorded in [verification.md](verification.md).
- Local lint, deterministic snapshot comparison, credential-pattern grep and merge conflict checks passed. No Composer installation ran, and the existing `vendor` symlink was preserved.
- The normal push succeeded at `61a54db7db0b53beef8e8adc318412f1c3c1be5a`; the branch and `refs/pull/82/head` match. PR #82 remains open and unmerged. All five GitHub checks passed on that pushed SHA.
- `origin/main` then advanced fast-forward to `9f3ee254a651dbd8cf61eb0fc7d0b65e928a0fa7` (PR #87). A second normal merge into this same branch is ready for the authorized PR #82 push; no history was rewritten.
