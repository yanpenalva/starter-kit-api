# Product Discovery — FEAT-18 role detail and rename

## Problem

API operators can create and list roles and update permissions, but cannot retrieve one role's details or rename a custom role.

## Evidence

- Repository: `routes/api/roles.php` exposes list, create, delete, and permission replacement only.
- Request: yanpenalva explicitly requested custom role rename/detail with the `admin` role immutable on 2026-10-12.
- Gap: no single-role detail route or rename request/action exists.

## Hypothesis and success

If authorized operators can inspect and rename custom roles, they can maintain role names without rebuilding permission assignments. Acceptance signal: SQLite feature tests cover authorized detail, rename, duplicate names, preserved permissions, audit, and built-in role protections.

## Constraints

- Sanctum session/cookie authentication remains in force.
- No frontend work, merge, PR, or role-permission matrix changes.
- Issue: [API #18](https://github.com/yanpenalva/starter-kit-api/issues/18).
