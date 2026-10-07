# Specification — FEAT-25 Wave C3 API release readiness and deployment

## Request

- Source: User request on branch `feat/wave-c3-release`.
- Requester: User.
- Date: 2026-10-06.
- Linked Issue: NOT FOUND in repository context or supplied request.

## Objective

Provide operational readiness, immutable image releases, repeatable deploy/rollback instructions and CI smoke validation for the API.

## Scope

- Public `GET /api/v1/health`, checking database, Redis and recent `failed_jobs`.
- Multi-stage PHP 8.5 production Dockerfile, `.dockerignore`, Compose app/worker/scheduler/PostgreSQL/Redis services.
- Immutable `api-<date>-<sha>` image tags, manual and tag-triggered deployment workflow, reusable login/profile/logout smoke job.
- Short deployment and rollback guide.
- Extend OpenAPI snapshot/scaffold tests without modifying `contracts/openapi.json`.

## Out of scope

- Production deployment execution and infrastructure-specific secrets/URLs, all NOT FOUND.
- Scout and telemetry.
- Docker image build/run locally; no daemon is available. No local-only daemon gate may be introduced.

## Evidence and context

- Existing Laravel 13/PHP 8.5 API, database queue, auth flow and snapshot tests confirmed by repository inspection.
- `REGRAS.md`: NOT FOUND. Canonical `.context` policies govern.
- Production operational owner/approver and deploy target: NOT FOUND.

## Rules and contracts

- Preserve `contracts/openapi.json`; API snapshot producer runs after merge on main.
- Runtime/test defaults remain SQLite-first where applicable.
- No secrets in repository; deploy environment configuration must be external.
- No commented-out code.

## Tests and acceptance criteria

- Pest tests readiness success and isolated failed checks (database, Redis, recent failed job).
- Route is public and new route appears in API contract assertions.
- PHP Pint, PHPStan, SQLite test suite pass.
- Docker and Compose YAML are reviewed statically; YAML parsed without Docker.
- CI workflows provide build/push and smoke path that runs on CI, with no daemon-dependent local gate.

## Open decisions

- Production image registry, environment URL and deployment mechanism: NOT FOUND; workflows use explicit external configuration and do not invent a provider.
