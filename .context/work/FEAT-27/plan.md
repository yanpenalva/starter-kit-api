# Plan

## Execution

1. Preserve the existing route, limiter, API-message, controller, session-driver, and module-documentation changes.
2. Close focused test gaps for revocation usability, reset success and failure, suspension through HTTP, cross-user bulk revocation, and the session/user key-type match.
3. Check `.env.example` and Compose for only the database session-driver requirement; keep credential values absent.
4. Inventory all three session operations and their HTTP methods in `ScaffoldTest`; document session-cookie security, XSRF, boolean `is_current`, and error responses in the contract overlay and test.
5. Apply migrations to an isolated SQLite database, export twice with `LC_ALL=C` to temporary files, and compare them. Copy the generated output to `contracts/openapi.json`; do not hand-edit the snapshot. The user explicitly authorized this local export for the active-session snapshot, following slices 1/6/7.
6. Run `ScaffoldTest`, active-session Pest tests, lint, analysis, and strict context validation. Use SQLite for focused local feedback; use the canonical PostgreSQL 18 profile only when its runtime is available without installing dependencies.
7. Review the complete diff, commit and push only to the existing PR #83. Do not create another PR or merge PR #83.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave |
| --- | --- | --- | --- | --- |
| A | yanpenalva | None | Focused `ActiveSessionsTest` assertions | 1 |
| B | yanpenalva | None | Diff and contract inspection | 1 |
| C | yanpenalva | A, B | Exact command results and reviewed diff | 2 |

## Data and rollback

The existing `sessions` migration uses a string primary key for Laravel's session ID and `foreignId('user_id')` for a bigint-compatible owner key. No new migration or data backfill is planned. Reverting the branch commit restores the previous application behavior; no production deployment is part of this work.

## Delivery

- Branch: `feat/slice-5-active-sessions`.
- Target: `main`; remote: `origin`.
- User authorized updating existing PR #83 after gates, and explicitly prohibited creating another PR or merging #83.
- User explicitly authorized the local deterministic Scramble export for `contracts/openapi.json` in this work item.
- The `origin/main` baseline is merged into this branch; see the merge commit in `verification.md`.
- Roadmap delivery authorization: `EXC-0001`.
