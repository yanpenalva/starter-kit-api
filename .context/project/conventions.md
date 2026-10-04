# Engineering Conventions

- Conventional Commits; small, reviewable changes targeting `main`, delivered by commit or PR according to [delivery authorization](delivery.md).
- Each implementation task corresponds to a GitHub Issue. Record its URL in the CSD work item's `discovery.md` (product) or `triage.md` (support); do not add fields absent from the canonical JSON schema; use `FEAT-<issue-number>` for product work and `BUG-<issue-number>`, `INC-<issue-number>` or `HOTFIX-<issue-number>` for the applicable support path. Track/type come from intake, not the prefix alone.
- Link the work item and issue in commit/PR evidence. When a PR exists, link it in verification/release and use `Closes #<issue-number>` only for the corresponding fully completed issue. Direct integration closes the issue explicitly after integration, passing gates and full acceptance. Work IDs are unique within this repository.
- Onboarding creates no work item. Its real evidence is kept in `project/onboarding.md`.
- Record material architecture decisions in `docs/adr/` through reviewed ADRs; [initial ADRs](../../docs/adr/README.md) for API #1 were accepted by yanpenalva on 2026-10-01; [approval](../work/FEAT-1/approval.md) is recorded. Canonical context reflects those decisions; runtime implementation remains pending. Revisit after the first scaffold.
- Preserve `.context/` as the sole normative context. Agent adapters only route to it.
- Deployment and versioning remain independent between API and frontend.
- API HTTP conventions and the initial OpenAPI draft are in [api.md](api.md) and [openapi.yaml](openapi.yaml); review their contract before API #13, and add Scramble generation/publication under API #11.
- Language and naming convention: code identifiers (variables, properties, methods, classes) in en-US; human-facing text (status, error messages, request validation, general user responses) in pt-BR.
- Code comments: remove obsolete or purely narrative comments that merely restate readable code; preserve PHPStan/Larastan static analysis annotations (@var, @param, @return, generics) and essential tool/legal directives.
- Machine interfaces: preserve machine error codes, API JSON field keys, HTTP routes and vendor code unchanged.
- Review ownership and detailed compatibility policy: `NOT FOUND`.
