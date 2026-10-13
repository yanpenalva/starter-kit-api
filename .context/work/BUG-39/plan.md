# Plan — BUG-39

## Approved approach

Keep MFA session state in the existing session flow, add one middleware to all authenticated API route groups, hash recovery codes with the configured Laravel hasher, and consume each code inside a transaction while locking its user row. Keep plaintext codes only in the generation response.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | Codex | none | Targeted Pest failures on the reported behaviors | 1 | security,testing |
| S2 | Codex | S1 | Targeted Pest passes on SQLite; PostgreSQL race test is executable in CI | 2 | security,testing |
| S3 | Codex | S2 | Scaffold route inventory and OpenAPI export tests cover all MFA paths and byte-identical repeated exports | 3 | project,testing |
| S4 | Codex | S3 | Pint, analyse, focused Pest, strict context validation and clean delivery diff | 4 | project,testing |

All subtasks are sequential because they touch the same authentication contract. No subagents are assigned in this session.

## Data, compatibility and rollback

- The feature migration is on an unmerged branch. Recovery-code storage changes from reversible encryption to JSON-encoded one-way hashes before release; no released-data conversion is expected.
- Enrollment continues returning plaintext codes once. Confirmation no longer attempts to retrieve stored codes.
- Rollback is a revert of the branch commit before integration; no production data migration or release is part of this task.

## Delivery

- Branch: `feat/slice-6-admin-mfa-optional`
- Target: `main`
- Remote: `origin`
- Existing PR: [#84](https://github.com/yanpenalva/starter-kit-api/pull/84), targeting `main`.
- After the user-requested gates pass: commit and push only `feat/slice-6-admin-mfa-optional` to `origin`, updating PR #84. Do not create another PR or merge PR #84.
- Exception record: `EXC-0001`.
