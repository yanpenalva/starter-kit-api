# Plan

## Execution

1. Preserve the existing route, limiter, API-message, controller, session-driver, and module-documentation changes.
2. Close focused test gaps for revocation usability, reset success and failure, suspension through HTTP, cross-user bulk revocation, and the session/user key-type match.
3. Check `.env.example` and Compose for only the database session-driver requirement; keep credential values absent.
4. Run Pint, Larastan, Pest, and strict context validation. Use the repository's SQLite profile for local feedback and attempt the canonical PostgreSQL 18 profile only if its runtime is available without installing dependencies.
5. Review the complete diff, then commit and push this branch only if the required gates pass. Do not create a PR or merge.

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
- User authorized commit and push after gates, and explicitly prohibited PR creation and merge.
- Roadmap delivery authorization: `EXC-0001`.
