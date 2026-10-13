# Plan — FEAT-29 — W2 backend

## Approved approach

Reuse the existing audit FormRequest/filter, querying the actor's current name and the event/action values already serialized. Keep the existing resource and authorization unchanged. Use focused TDD and the supported driver-specific case-insensitive LIKE operator.

## Execution order

1. Run and preserve the two existing multi-role test changes using the isolated SQLite executor.
2. Add the audit HTTP search test, run it before implementation and record the expected failure.
3. Add `search` validation and filtering, then rerun focused audit/filter/multi-role tests.
4. Generate the OpenAPI candidate on this branch and review the snapshot diff and API response tests.
5. Run PHP 8.5 lint, PHPStan L10, focused SQLite tests, and strict context validation.
6. Review the exact diff, create small Conventional Commits, and push `feat/be-adj-w2-multirole` with upstream `origin`.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| FEAT-29-MULTIROLE | yanpenalva | none | Preserved HTTP/profile tests pass without changing role policy | 1 | core,project,testing |
| FEAT-29-AUDIT-SEARCH | yanpenalva | none | Red/green HTTP tests for name/event/action and combination | 1 | core,project,architecture,security,testing |
| FEAT-29-CONTRACT | yanpenalva | FEAT-29-AUDIT-SEARCH | Candidate diff shows the request parameter and no unrelated schema changes | 2 | project,release,testing |
| FEAT-29-DELIVERY | yanpenalva | FEAT-29-CONTRACT, FEAT-29-MULTIROLE | Gates and reviewed diff recorded; branch pushed | 3 | core,release,testing |

## Risks and rollback

- Risk: an identity search could reveal new data or leak through the serialized resource. Mitigation: restrict matching to existing actor relation and assert the response remains unchanged.
- Risk: SQLite/PostgreSQL operator drift. Mitigation: select `like`/`ilike` by active driver and run identical focused tests on SQLite locally and PostgreSQL in CI.
- Rollback: revert the search/filter/schema snapshot commit; no migration or stored-data change is required.

## Delivery

- Branch: `feat/be-adj-w2-multirole`; target: `main`; remote: `origin`.
- Authorization: user explicitly requested small commits and `git push -u origin feat/be-adj-w2-multirole`; roadmap delivery authorization is recorded in `EXC-0001`.
- Issue: NOT FOUND. No external issue will be created without explicit authorization.
- Full PostgreSQL 18 suite and PR integration are pending after push.

## Required approvals

- User supplied the scope, product decisions, local gate requirements, commit and push authorization in this turn.
- No production release, merge, or artifact publication is requested.
