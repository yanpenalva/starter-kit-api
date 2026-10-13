# Plan — BUG-39

## Approved approach

1. Record the scoped authorization and verify PR #82 is open, unmerged, targets `main`, and uses the expected branch/SHA.
2. Export the contract twice through `scripts/contract_export.py` under `LC_ALL=C` and `TZ=UTC`; compare both files with `cmp`, then commit `contracts/openapi.json`.
3. Run interactive rebase with merge preservation and edit the two commits containing the fixture: `db5c8c1261f88078f4d48a492d97b900205e3354` and `7d34e0373ebbbfe16568acaf96321d569a069875`. Replace the flagged value with `fake-profile-password-123` in both.
4. Run lint, ScaffoldTest, strict context validation, a history-wide search for the old value, and final diff review.
5. Push only the existing PR #82 branch with `--force-with-lease` tied to the observed remote SHA; verify its new head and open/unmerged state.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | `yanpenalva` | none | PR state verified; two outputs compare; snapshot commit recorded | 1 | project,testing |
| S2 | `yanpenalva` | S1 | both historical occurrences replaced; merge topology preserved; original value absent from branch history | 2 | security |
| S3 | `yanpenalva` | S2 | requested gates pass; explicit lease succeeds; PR remains open | 3 | testing,release |

## Risks and rollback

- Risk: the approved force-push changes the commit IDs on PR #82. The explicit lease prevents overwriting any intervening remote update.
- If the lease fails, stop without retrying; inspect the new remote state before any further action.
- Do not merge or open another PR.

## Required approvals

- Explicit user authorization, scoped in [EXC-0002](../../exceptions/EXC-0002.json).
