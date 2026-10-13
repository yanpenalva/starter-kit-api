# Release — FEAT-17 correction

## Readiness

- Focused SQLite HTTP tests: 74 passed, 456 assertions; missing and empty admin roles reject without account creation, public registration assigns `user`, and empty PUT remains 422.
- Pint (`composer lint`), Larastan level 10 (`composer analyse`), Python tooling (117 tests), strict context validation, CSD doctor, and independent review passed.
- No catalog changes, migration, or OpenAPI snapshot change. `Role::User` is already seeded and approved with no permissions.
- Docker daemon was unavailable, so PostgreSQL 18/Compose integration was not run locally.
- Production release/deploy: not requested; none performed.

## Git finalization

- Requested delivery branch: `feat/be-user-create-with-roles`; remote: `origin` (`https://github.com/yanpenalva/starter-kit-api.git`).
- Commit: `ab0736c554b905b39d41b802831c6a36d30c5e12` — `fix(users): require profiles on account creation`.
- Push succeeded without force. `git ls-remote` confirmed the remote branch points to the same SHA.
- No PR, merge, or deploy is in scope.
- Rollback: revert the new Conventional Commit. No migration or data backfill is involved.
