# Progressive quality gates

Canonical commands, thresholds and evidence live in [.context/project/quality.md](.context/project/quality.md). Run application commands inside the PHP 8.5 image; do not validate using host PHP 8.4.

## Initial scaffold

- Composer strict validation, installation from `composer.lock`, platform requirements and dependency audit.
- Pint read-only formatting check; Larastan level 10 across application, bootstrap, routes and tests, without baseline/blanket ignores.
- PHPUnit against isolated PostgreSQL: boot, `/api/v1` JSON, `/up`, route boundaries and database identity/isolation.
- Compose validation, image build, healthy startup and HTTP smoke checks from a clean source copy with its own project/volume names.
- CSD strict, examples adaptation, Python regression suite and pinned CSD doctor.

Application CI runs PHP 8.5 and PostgreSQL 18, independently of production and legacy data. No coverage percentage is claimed yet: the scaffold has no business logic; changed-behavior coverage expectations will be reviewed per feature. Automated cognitive/cyclomatic measurements are not configured. CSD structural/code-review limits remain applicable; absence of a metric tool is not a waiver.

## Measured coverage gate (merged pgsql + sqlite, min 80%)

- `composer test:coverage` measures the union of two PHPUnit runs — the canonical PostgreSQL leg (`phpunit.xml`, acceptance gate, zero failures, every test) and the SQLite leg (`phpunit.sqlite.xml`, `Feature` suite in `:memory:`, excluding per test file only the cases that assert pgsql/Compose environment behaviour) — merges both Clover reports with `scripts/merge-coverage.php` and enforces an 80% statement floor through `scripts/coverage-floor.php`.
- Two runs are required because driver branches are mutually exclusive: `app/Support/Filters/*` uses `ilike` on PostgreSQL and `like`/`json_extract` on SQLite, and `EmailLookup`/`LoginController` branch on `getDriverName()`.
- Nothing is excluded from the report — no `@codeCoverageIgnore`, no `<exclude>` inside `<source>`, no ignore lists. The SQLite leg's `<exclude>` entries drop environment-dependent *test files* from that run, never app code from the report. An uncovered line needs a test or a recorded human decision, never a filter.
- The merged report proves every line ran in at least one leg; it does not prove that each driver branch runs in every environment, so the claimed percentage is the one CI measures. Evidence: FEAT-23 `verification.md`.

## Add with the owning feature

- Identity/RBAC/CRUD: validation, authorization, account states, CPF privacy/uniqueness, CSRF/session and negative access cases.
- Audit: permitted events/diffs, transaction rollback, read permission and approved retention.
- OpenAPI issue #11: export, syntax checks and compatibility checks.
- Frontend integration: isolated smoke/E2E and published contract artifact.
- Production delivery: image readiness, deploy/rollback and approved operations checks.

No simulated or unavailable check is reported as passing. GitHub workflow configuration is reviewed locally; remote CI execution requires an actual PR/push and is not claimed by this delivery.

## Wave 0 documentation slices (2026-10-03)

- Progressive gate definitions with implemented-vs-planned state live in FEAT-4; the existing CI is preserved verbatim and is not recreated by documentation. The initial CI stage remains recognized under FEAT-24.
- The initial threat model (assets, boundaries, negative-acceptance matrix) lives in FEAT-22; planned tests accompany their owning features and are consolidated in wave 7.
- The approved private OpenAPI transport policy (producer/consumer rules, fail-closed artifact verification, credential-free frontend CI) lives in [api.md](.context/project/api.md); no producer workflow exists yet.
