# Engineering Conventions

- Conventional Commits; small, reviewable PRs targeting `main`.
- Each implementation task corresponds to a GitHub Issue. Record its URL in the CSD work item's `discovery.md` (product) or `triage.md` (support); do not add fields absent from the canonical JSON schema; use `FEAT-<issue-number>` for product work and `BUG-<issue-number>`, `INC-<issue-number>` or `HOTFIX-<issue-number>` for the applicable support path. Track/type come from intake, not the prefix alone.
- Link the work-item path in the PR and link the PR in its verification/release evidence. Use `Closes #<issue-number>` only for the corresponding issue. Work IDs are unique within this repository.
- Onboarding creates no work item. Its real evidence is kept in `project/onboarding.md`.
- Record material architecture decisions in `docs/adr/` through reviewed ADRs; [initial ADRs](../../docs/adr/README.md) for API #1 were accepted by yanpenalva on 2026-10-01; [approval](../work/FEAT-1/approval.md) is recorded. Canonical context reflects those decisions; runtime implementation remains pending. Revisit after the first scaffold.
- Preserve `.context/` as the sole normative context. Agent adapters only route to it.
- Deployment and versioning remain independent between API and frontend.
- API HTTP conventions and the initial OpenAPI draft are in [api.md](api.md) and [openapi.yaml](openapi.yaml); review their contract before API #13, and add Scramble generation/publication under API #11.
- Naming beyond these conventions, review ownership and detailed compatibility policy: `NOT FOUND`.
