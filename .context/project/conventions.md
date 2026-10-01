# Engineering Conventions

- Conventional Commits; small, reviewable PRs targeting `main`.
- Each implementation task corresponds to a GitHub Issue. Record its URL in the CSD work item's `discovery.md` (product) or `triage.md` (support); do not add fields absent from the canonical JSON schema; use `FEAT-<issue-number>` for product work and `BUG-<issue-number>`, `INC-<issue-number>` or `HOTFIX-<issue-number>` for the applicable support path. Track/type come from intake, not the prefix alone.
- Link the work-item path in the PR and link the PR in its verification/release evidence. Use `Closes #<issue-number>` only for the corresponding issue. Work IDs are unique within this repository.
- Onboarding creates no work item. Its real evidence is kept in `project/onboarding.md`.
- Record material architecture decisions in `docs/adr/` through reviewed ADRs; no ADRs exist yet. Revisit project context after those decisions and the first scaffold.
- Preserve `.context/` as the sole normative context. Agent adapters only route to it.
- Deployment and versioning remain independent between API and frontend.
- Naming beyond these conventions, review ownership and detailed compatibility policy: `NOT FOUND`.
