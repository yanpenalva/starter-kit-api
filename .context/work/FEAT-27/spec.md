# Specification — FEAT-27 — Slice 12

## Request

- Source: user instruction for Slice 12, 2026-10-12; API #27 continuous documentation scope.
- Requester: yanpenalva.

## Objective

Keep current API decisions and the OpenAPI release procedure discoverable and accurate, and give maintainers an executable recipe for one vertical slice.

## Scope

- Add successor ADRs that record already approved/as-built decisions for session authentication, privilege/audit behavior, and OpenAPI contract production/release.
- Update the ADR index and identify the historical scope of superseded statements without erasing their record.
- Add one canonical contract-release guide, a detailed artifact-provenance recipe, and one vertical-slice recipe; link all three from onboarding.
- Correct stale statements in .context/project/api.md about session-only authentication, Scramble, artifact publication, and compatibility CI.
- Add a focused documentation synchronization check for the contract producer workflow.

## Out of scope

- Frontend changes, API behavior changes, workflow configuration changes, PR creation, merge, deployment, or production release.
- Editing or generating `contracts/openapi.json` as part of this documentation-only slice. For later API contract changes, the candidate snapshot belongs in the same PR because Quality compares it with the generated contract; the official producer remains main-only and does not commit the snapshot.
- Introducing new architecture decisions beyond documenting the decisions and behavior already authorized in this roadmap.

## Evidence and context

- Repository evidence: current workflows, docs, ADRs, scripts, and pushed slice branches 6–11.
- Relevant project context: API contract, release workflow, code quality and security policies.
- Unknowns (NOT FOUND): independent reader review for this slice; no production or external artifact operation is part of scope.

## Rules and contracts

- Authentication remains Sanctum session/cookie and CSRF only; no bearer, personal access, JWT, or API tokens.
- Official contract publication is dispatched on main; producer validates deterministic output and publishes a commit-SHA artifact, while Quality validates the committed snapshot and compatibility.
- Slice branches are isolated from one another and based on main. Successor ADRs must distinguish design decisions from integration/deployment state.
- Use repository context routing, TDD, SQLite-safe local Pest where relevant, Pint, PHPStan, and strict context validation.

## Tests and acceptance criteria

- A focused Python test fails before the release guide exists and passes after implementation; it detects drift between the guide and workflow trigger, main guard, Scramble pin, deterministic export, and 90-day artifact retention.
- ADR index links every added successor; predecessor documents retain their history and point to successors where applicable.
- All three guides are linked from onboarding and accurately describe repository commands and release boundaries.
- .context/project/api.md no longer claims that active Scramble generation, producer publication, or compatibility CI are unimplemented.
- Required local validation passes: strict context with examples, Python tests, composer lint, composer analyse, and relevant SQLite-safe Pest. Any unavailable/irrelevant gate is recorded.
- Branch feat/slice-12-contract-release-docs is committed and pushed to origin; no PR or merge is created.

## Open decisions

- None within the authorized scope. The contract snapshot must remain unchanged.
