# Release — FEAT-17 correction

## Readiness

- Focused SQLite HTTP tests: 74 passed, 456 assertions; missing and empty admin roles reject without account creation, public registration assigns `user`, and empty PUT remains 422.
- Pint (`composer lint`), Larastan level 10 (`composer analyse`), Python tooling (117 tests), strict context validation, CSD doctor, and independent review passed.
- No catalog changes, migration, or OpenAPI snapshot change. `Role::User` is already seeded and approved with no permissions.
- Docker daemon was unavailable, so PostgreSQL 18/Compose integration was not run locally.
- Production release/deploy: not requested; none performed.

## Git finalization

- Requested delivery branch: `feat/be-user-create-with-roles`; remote: `origin` (`https://github.com/yanpenalva/starter-kit-api.git`).
- The user explicitly requested the Conventional Commit and branch push. Push evidence and SHA will be added after delivery.
- No PR, merge, or deploy is in scope.
- Rollback: revert the new Conventional Commit. No migration or data backfill is involved.
