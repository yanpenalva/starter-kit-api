# Product Discovery — FEAT-25 Wave C3 API release readiness and deployment

## Problem

- User/operator: API deployment operator.
- Situation: The request requires shipping readiness checks, production image build, release/rollback flow and post-deploy verification.
- Pain or opportunity: Operators need a repeatable release path and an endpoint that reports database, Redis and recent queue failures.

## Evidence

- Observation, research or data: User supplied Wave C3 acceptance requirements; current repository already has `/up`, async queue/scheduler and session auth routes.
- Confidence and gaps: Scope is explicit. Production environment URL, host, registry target details and operational owner were NOT FOUND.

## Hypothesis

If we add readiness, immutable image releases and CI deploy/smoke automation, then API operators will be able to deploy and verify releases with a documented rollback path because each release identifies a fixed image and exercises the authenticated session lifecycle.

## Success

- Primary metric: Required checks and auth smoke complete successfully for an approved deployment.
- Baseline: No readiness endpoint or release workflow currently exists.
- Target: A tested readiness endpoint and CI workflow with an immutable image tag and reusable post-deploy smoke.
- Observation period: Per release; production observation duration is NOT FOUND.

## Constraints and alternatives

- Constraints: No local Docker daemon; Docker build/run is CI-only. Preserve OpenAPI snapshot. No secrets in repository. `REGRAS.md` absent.
- Alternatives considered: Keep existing dev Compose stack unchanged and add a `release` profile; use authenticated HTTP smoke instead of browser automation.
- Why now: User requested the C3 release wave on the current branch.
