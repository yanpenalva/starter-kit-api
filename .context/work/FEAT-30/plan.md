# Plan — FEAT-30 — Ferramentas Telescope e debug para API

## Approved approach

Use Composer-supported Laravel packages, generated Telescope/Pulse scaffolding and Debugbar's actual Fruitcake provider namespace; make small provider/config changes.

## Confirmed touchpoints

- Files: composer.json/lock, bootstrap/providers.php, AppServiceProvider, TelescopeServiceProvider, telescope/debugbar/Pulse configs, migrations, PHPUnit configs, .env.example.
- Dependencies: package discovery; Telescope DB migration required before persisted use.

## Execution order

1. Confirm package presence/constraints and Composer dry-runs.
2. Install compatible requested packages.
3. Publish package assets and configure runtime controls and gates.
4. Run lint, analysis and available SQLite tests; inspect diff.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| FEAT-30-PACKAGES | yanpenalva | none | Resolved installed versions | 1 | core,project,architecture |
| FEAT-30-CONFIG | yanpenalva | FEAT-30-PACKAGES | Config/providers match requested gates | 2 | security,project |
| FEAT-30-VERIFY | yanpenalva | FEAT-30-CONFIG | Lint, analysis, tests and diff recorded | 3 | testing,release |

## Test plan

Run composer lint/analyse and the SQLite PHPUnit config; attempt composer test if local services permit.

## Risks and rollback

- Risk: Telescope and Pulse storage require database migrations; non-local access could expose telemetry if gate/filter is wrong.
- Mitigation: role-based gate, restricted filters, sensitive request masking; migrations are committed for explicit deployment.
- Rollback: revert provider/config/dependency/migration changes.

## Required approvals

- User explicitly authorized changes and prohibited commit/PR. Production migration/deployment is not in scope.
