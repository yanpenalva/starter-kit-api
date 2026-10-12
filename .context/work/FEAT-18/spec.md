# Specification — FEAT-18 role detail and rename slice

## Request

- Source: explicit user request in this session, 2026-10-12.
- Parent issue: [API #18](https://github.com/yanpenalva/starter-kit-api/issues/18).

## Objective

Let authorized API operators read one role and rename custom roles while protecting built-in role names and the full `admin` role.

## Scope

- `GET /api/v1/roles/{id}` requires `roles.view` and returns the existing role resource.
- `PATCH /api/v1/roles/{id}` requires `roles.manage`, validates a unique name up to 255 characters, preserves permission assignments, and writes an audit record in the privilege transaction.
- Built-in names cannot be renamed. The `admin` role also cannot be deleted or have its permission set changed.
- Update API/architecture documentation and feature coverage.

## Out of scope

- Frontend, role permission redesign, production deployment, PR/merge, and generated OpenAPI snapshot (owned by slice 9 producer flow).

## Rules and contracts

- Authentication stays Sanctum session/cookie-only.
- Role detail requires `roles.view`; rename/delete require `roles.manage` and an eligible actor.
- RoleResource remains the public representation; rename returns the same resource and does not alter permissions.
- Audit remains fail-closed and stores only the existing minimal envelope.

## Acceptance

- Feature tests prove detail authorization, rename authorization, uniqueness validation, permission preservation, audit, built-in name protection, and admin immutability.
- `composer lint`, `composer analyse`, relevant Pest tests, and strict context validation pass.
- Branch is based on updated `origin/main`, then committed and pushed without opening a PR or merging.

## Unknowns

- API #18's complete CRUD acceptance remains open beyond this slice.
- PostgreSQL 18/Redis CI evidence is NOT FOUND locally; CI owns those services.
