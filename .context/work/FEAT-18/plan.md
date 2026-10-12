# Plan — FEAT-18 role detail and rename slice

## Approved approach

Use the existing RoleResource, Form Request, Policy, Action, PrivilegeTransaction, and audit patterns. Add a single-role query and a bounded rename operation; reserve built-in role names and block all admin mutations.

## Execution order

1. Add focused HTTP tests and observe the missing routes fail.
2. Implement detail, rename, uniqueness validation, policy checks, audit, and permission-cache invalidation.
3. Update the API and architecture contract text.
4. Run focused Pest, Pint, PHPStan, and context validation.
5. Review diff, commit, and push only this slice branch.

## Subtasks and waves

| Subtask | Owner | Dependencies | Acceptance evidence | Wave | Context |
| --- | --- | --- | --- | --- | --- |
| S1 | Codex | none | HTTP tests pass; custom permission assignment remains intact | 1 | project,architecture,security,testing |
| S2 verify and deliver | Codex | S1 | Lint, analysis, Pest, strict context validation, diff review | 2 | core,testing |

## Risks and rollback

- Risk: a role rename could stale Spatie's permission cache or accidentally mutate the built-in admin role.
- Mitigation: lock and reauthorize the role in the action, invalidate permission cache after commit, and cover built-in role cases.
- Rollback: revert this branch commit; no migration or data rewrite is introduced.

## Delivery

`feat/slice-8-custom-role-renaming` → `main` on `origin`. No PR or merge per the user's explicit instruction.
