# Specification — FEAT-30 — Ferramentas Telescope e debug para API

## Request

- Source: user request in this session.
- Requester: user.
- Date: 2026-10-14.

## Objective

Add requested Laravel development/diagnostics tools with runtime data access appropriately restricted.

## Scope

- Install Telescope/Tinker/Pulse as runtime packages, Debugbar/Laradumps as dev packages.
- Keep Guzzle transitively usable without a redundant direct requirement.
- Publish Telescope migration/config and Debugbar config.
- Add a Telescope filter, sensitive request masking and admin-only non-local gate; expose Pulse to verified, active admins with the Admin role.
- Disable Debugbar outside local, prevent destructive DB commands in production, fake Sleep in tests, and configure model strictness only outside production.

## Out of scope

- Horizon, Vite/frontend wiring, commits and PRs.

## Evidence and context

- Repository evidence: Laravel 13.34.0/PHP 8.5.11; Guzzle 8.2.0 transitively installed; Tinker absent and framework-suggested.
- Relevant project context: Laravel API, admin role `Role::Admin`, .env and configured quality commands.
- Unknowns: GitHub issue NOT FOUND; production migration/deployment procedure NOT FOUND.

## Rules and contracts

- Debugbar enabled only in local, config default false.
- Outside local Telescope keeps reportable/failed/monitored entries only, masks token/cookie/CSRF data, and `viewTelescope` requires admin.
- `Model::shouldBeStrict(! app()->isProduction())` controls all strict model behavior; automatic relationship eager loading remains enabled.
- Pulse uses database storage by default, falls back to the application's default DB connection when `PULSE_DB_CONNECTION` is unset, and ships its default recorders enabled. Its config and migration are published; run the migration before enabling Pulse in a deployed environment.
- Telescope already excludes queue worker/listener and Horizon artisan commands; per-job suppression, if needed later, should use `Telescope::withoutRecording()` inside that job.
- No bearer auth changes.

## Tests and acceptance criteria

- Composer resolves all requested packages on PHP 8.5/Laravel 13.
- `composer lint`, `composer analyse`, and SQLite suite are attempted and outcomes recorded.

## Open decisions

- None; direct instructions specify behavior.
