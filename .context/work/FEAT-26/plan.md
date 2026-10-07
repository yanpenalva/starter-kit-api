# Plan

## Execution

1. Add focused HTTP regressions for missing manage permissions, validation, persistence, self/last-admin safeguards, in-use role deletion, writer-generated causer filtering, and alternate-path PII redaction.
2. Add `users.manage` / `roles.manage` to the enum catalogue; implement FormRequests, routes, policy decisions, transactional controller operations and audit records.
3. Share the writer's property allowlist with the resource and return only the approved event identifier in place of arbitrary description text.
4. Document that permission definitions have no HTTP CRUD and update the RBAC matrix/route contract.
5. Verify focused tests on SQLite, then Pint, clean-cache PHPStan, the PostgreSQL 18 suite, strict context validation and the applicable CSD checks.

## Subtasks and waves

| Subtask | Owner | Dependencies | Evidence | Wave |
| --- | --- | --- | --- | --- |
| A | yanpenalva | None | CRUD endpoints and HTTP tests | 1 |
| B | yanpenalva | None | Writer causer filter test and resource PII test | 1 |
| C | yanpenalva | A, B | ADR and RBAC endpoint contract | 2 |
| D | yanpenalva | A, B, C | Static checks, suite and review | 3 |

## Invariants and rollback

- User deletion follows the repository's hard-delete model (no `SoftDeletes` trait); mutations and audit writes share `PrivilegeTransaction`.
- PostgreSQL advisory lock protects the last-admin invariant; SQLite provides focused local feedback.
- No data migration/backfill is required. Existing audit rows remain as stored; newly written rows carry `causer_id`.
- If a code correction is needed before delivery, revert the branch commit (not an irreversible production action); no production deployment is part of this work.

## Delivery

- Branch: `feat/be-bloco3-rbac-audit`.
- Target: `main`; remote: `origin`.
- The user requested commit and push, no PR; PR creation is delegated to the orchestrator.
- Delivery authorization reference: `EXC-0001`.
