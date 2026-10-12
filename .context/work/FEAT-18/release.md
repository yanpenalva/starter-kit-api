# Release — FEAT-18 role detail and rename slice

## Readiness

- Focused role suite: 30 passed / 125 assertions on the local SQLite profile; full evidence is in [verification.md](verification.md).
- Pint, Larastan level 10, Composer validation/platform/audit, Python tests, strict context validation and CSD doctor passed.
- No schema or stored-data format changes; no production deployment requested or performed.
- PostgreSQL 18/Redis integration was not run locally. CI remains the integration gate.

## Git finalization

- Original slice commit: `8bb3faf` — `feat(roles): add custom role detail and rename`.
- Follow-up correction commit: `ffe3acf` — `fix(roles): guard permission updates and rename races`.
- Delivery branch: `feat/slice-8-custom-role-renaming`.
- Target branch: `main`.
- Remote: `origin` (`https://github.com/yanpenalva/starter-kit-api.git`).
- Push of `ffe3acf` to the delivery branch: success. The release-evidence update is also delivered on this branch.
- No PR or merge was opened or performed.
