# Plan — FEAT-18 role detail and rename slice

## Approved approach

Use the existing RoleResource, Form Request, Policy, Action, PrivilegeTransaction, and audit patterns. Add a single-role query and a bounded rename operation; reserve built-in role names and block all admin mutations.

## Follow-up correction within the accepted scope

- Revalidate `RolePolicy::updatePermissions` against the locked, persisted role inside `UpdateRolePermissions::run()` before syncing permissions.
- In `RenameRole::run()`, check for another role with the same name and guard after locking the target. Translate a role-name unique-index race during save into the existing `name` validation error contract (HTTP 422).
- Pass the `role.rename` action code through the slice 7 role-audit helper and retain it in the approved audit envelope.
- Add a transaction-boundary policy test, a direct-action admin mutation test, a direct-action duplicate-name test, and an HTTP test that injects a competing role after the transactional recheck but before the database update.

## Execution order

1. Add focused HTTP tests and observe the missing routes fail.
2. Implement detail, rename, uniqueness validation, policy checks, audit, and permission-cache invalidation.
3. Update the API and architecture contract text.
4. Run focused Pest, Pint, PHPStan, and context validation.
5. Review diff, commit, and push only this slice branch.
6. For the follow-up correction, add the regression tests first and observe them fail before changing either action; then run the requested lint, analysis, Pest, and strict context gates before commit/push.

## Subtasks and waves

| Subtask | Owner | Dependencies | Acceptance evidence | Wave | Context |
| --- | --- | --- | --- | --- | --- |
| S1 | Codex | none | HTTP tests pass; custom permission assignment remains intact | 1 | project,architecture,security,testing |
| S2 verify and deliver | Codex | S1 | Lint, analysis, Pest, strict context validation, diff review | 2 | core,testing |

## Risks and rollback

- Risk: direct action callers could mutate the built-in admin role, and concurrent renames could leak a database uniqueness error as HTTP 500.
- Mitigation: authorize against the locked role in the transaction; recheck uniqueness there and translate the role table's unique-index race into HTTP 422; invalidate permission cache after commit.
- Rollback: revert this branch commit; no migration or data rewrite is introduced.

## Delivery

`feat/slice-8-custom-role-renaming` → `main` on `origin`. No PR or merge per the user's explicit instruction.
