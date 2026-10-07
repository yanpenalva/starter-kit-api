# Preflight — FEAT-25

## Scope and plan check

- Request maps to readiness route/tests, production image and Compose release profile, immutable image deploy/rollback, reusable auth smoke workflow and short operational docs.
- Contract snapshot constraint is explicit: keep `contracts/openapi.json` unchanged while asserting the new route in generated-snapshot and scaffold tests.
- Local Docker build/run is excluded by the confirmed absence of a daemon; CI contains the image build/push path.
- No production deploy will be attempted without configured target/secrets and human approval.

## Validation boundary

- Repository and specification agree on the branch, scope and operational constraints.
- Automated independent preflight was unavailable; this record is the implementation agent's checklist, not an independent approval.
- PHP 8.5 checks and image build remain CI gates before merge/release, as recorded in `verification.md`.
