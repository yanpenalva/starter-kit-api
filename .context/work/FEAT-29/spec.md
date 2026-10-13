# Specification — FEAT-29 — W2 backend

## Request

- Source: user instruction for W2 backend, 2026-10-13; implementation scope from `/opt/data/work/FE_ADJUSTMENTS.md` § W2.
- Requester: user in this session.
- Recorded product decisions: case-insensitive substring search across actor name plus description/action already present in the envelope; no new PII; retain `logs.view`; driver-specific SQLite `like` and PostgreSQL `ilike`; use Action where the resource requires it. Update the candidate `contracts/openapi.json` via `LC_ALL=C php artisan scramble:export --path=contracts/openapi.json`; official artifact remains produced on `main`.

## Objective

Implement searchable audit results on the backend and preserve regression evidence for the existing multi-role assignment/profile behavior.

## Scope

- Validate and pass the optional audit `search` parameter into the existing filter.
- Search case-insensitively by substring in the current actor name, the event value serialized as `description`, and the existing action code.
- Add focused HTTP tests for one-letter and mixed-case matching, filter composition, authorization and unchanged response shape/PII boundary.
- Preserve and verify the two existing uncommitted multi-role test changes.
- Generate and review the candidate OpenAPI snapshot from this branch.
- Record verification, review, and push evidence for `feat/be-adj-w2-multirole` to `origin`.

## Out of scope

- Frontend implementation or mock updates; production release; creation of roles during user POST; changes to role policy or the audit envelope; changes to the official main-only artifact workflow.

## Evidence and context

- Relevant implementation: `IndexActivitiesRequest`, `ActivityFilters`, `ActivityLogController`, `ActivityResource`, `AuditFeedTest`, `FiltersTest`.
- Existing role behavior: `AssignUserRoles` uses `syncRoles`; user/profile resources serialize multiple roles and permission union. The existing test changes cover these behaviors.
- Unknown: GitHub Issue ID for this W2 implementation is NOT FOUND.

## Rules and contracts

- Search is an allowlisted, validated query parameter and is applied under existing `logs.view` authorization.
- The actor's name participates only in query matching; never serialize it or new personal data in audit results.
- Keep request values parameterized and escape SQL LIKE wildcard characters.
- Existing `action` remains a valid structured filter; no channel filter is introduced.
- Run focused SQLite tests and local `composer lint` / `composer analyse` with PHP 8.5, `APP_ENV=testing`, `MAIL_MAILER=array`. Full `composer test` remains a CI PostgreSQL 18 gate.

## Tests and acceptance criteria

- A new focused test fails before the filter implementation and passes after it.
- A single-letter term matches an actor name, an event/description value and an action, case-insensitively; search combines with the action filter.
- The feed still requires `logs.view`, and responses contain no actor name/email/CPF added by search.
- Preserved role tests pass for multi-role assignment, replacement and permission union.
- Candidate OpenAPI includes the `search` query parameter and its diff is reviewed; the official artifact remains main-only.
- Local SQLite tests, lint, analysis and strict context validation exit 0. PostgreSQL 18 full-suite CI remains pending.

## Open decisions

- None. Product choices and local gates were specified by the user.
