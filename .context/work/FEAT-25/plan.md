# Plan — FEAT-25 Wave C3 API release readiness and deployment

## Approved approach

Extend Laravel health behavior through a dedicated API readiness endpoint while keeping the existing `/up`; build production image from Composer dependencies and application source; use Compose profiles suitable for ephemeral local tests; implement GitHub workflows for build/push and authenticated HTTP smoke checks.

## Confirmed touchpoints

- `routes/api.php`, health controller/service, Pest tests, `OpenApiSnapshotTest` and `ScaffoldTest`.
- `Dockerfile`, `.dockerignore`, `compose.yaml`.
- `.github/workflows/` deploy and reusable smoke workflows.
- `docs/` short deploy/rollback instructions.
- Keep `contracts/openapi.json` unchanged.

## Execution order

1. Add readiness endpoint and focused tests/contract assertions.
2. Add production image, ignore rules and Compose services.
3. Add reusable smoke and deploy workflows.
4. Document ephemeral local workflow and immutable tag rollback.
5. Run SQLite-first tests, Pint, PHPStan and available YAML/context checks; manually inspect Docker artifacts.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| FEAT-25-A Readiness | Main agent | None | Pest readiness and route assertions | 1 | project,architecture,security,testing |
| FEAT-25-B Image/Compose | Main agent | None | YAML parsing and manual Dockerfile review | 1 | project,architecture,release |
| FEAT-25-C Workflows/docs | Main agent | FEAT-25-A Readiness, FEAT-25-B Image/Compose | Workflow static validation and operational guide | 2 | release,security,testing |

Integration owner: main agent. No subagent handoff.

## Test plan

- Pest readiness tests under SQLite, using mocks/fakes for isolated dependency failures.
- Existing feature suite under SQLite.
- Composer Pint and PHPStan commands from project quality configuration.
- Python YAML parsing if available; otherwise Ruby/PHP parser or CI syntax checks.
- GitHub workflow static checks if installed; manual review of build/push and smoke dependencies.

## Risks and rollback

- Risk: Deployment target/provider is not identified; avoid provider-specific assumptions and make required configuration explicit.
- Mitigation: Build and smoke workflow only perform release/push/deploy when their documented inputs/secrets are provided.
- Rollback: Restore previous immutable image tag in deployment environment and restart app/worker/scheduler services.

## Required approvals

- Production release/deployment remains a human gate; no live deployment authorized by this work request.
