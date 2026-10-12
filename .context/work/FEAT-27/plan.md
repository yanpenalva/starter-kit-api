# Plan — FEAT-27 — Slice 12

## Approved approach

Describe the already implemented workflow in a canonical release guide and detailed provenance recipe, document current decisions in successor ADRs, and remove contradictory status text from canonical API context. Keep the existing workflow and snapshot untouched.

## Confirmed touchpoints

- Files/components/services: docs/adr, docs/onboarding.md, docs/contract-release.md, docs/openapi-contract-recipe.md, docs/vertical-slice-recipe.md, .context/project/api.md, tests/tools.
- Interfaces and data: OpenAPI release artifact and versioned snapshot process; no runtime or schema change.
- Dependencies: existing workflow and quality gate; no new dependency.

## Execution order

1. Add a focused test for contract-guide alignment and observe its baseline failure.
2. Write the release/provenance guides, vertical-slice recipe, successor ADRs, ADR index updates, and API context corrections.
3. Run the focused Python check, strict context validation, PHP gates, and relevant SQLite-safe Pest.
4. Review every changed file against the spec, record verification and limitations, then commit and push this branch.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| FEAT-27-DOCS | yanpenalva | none | Current ADRs and linked release/vertical-slice guides | 1 | core,project,architecture,security,release |
| FEAT-27-VERIFY | yanpenalva | FEAT-27-DOCS | Python/context/PHP/Pest results and diff review | 2 | core,project,testing |

Implementation and integration owner: Codex for this slice. Independent reader review: NOT FOUND in this execution; record as a limitation and leave issue-level acceptance open.

## Test plan

- Python unittest for contract-release documentation facts checked against .github/workflows/openapi.yml and onboarding discoverability.
- python3 scripts/validate_context.py --strict --examples.
- python3 -m unittest discover -s tests -v.
- composer lint; composer analyse; SQLite-safe relevant Pest subset.

## Risks and rollback

- Risk: overstating an unintegrated slice as deployed behavior, or recording stale producer behavior.
- Mitigation: identify source branch/commit evidence in ADRs and separate decision state from integration status; test release instructions against the workflow file.
- Rollback: revert this documentation/test commit; no database, runtime or artifact state changes.

## Delivery

- Branch: `feat/slice-12-contract-release-docs`, based on `origin/main` `98cf64147080c03e17c14d664ac6ac08231fcb02`.
- Target: `main`; remote: `origin`; authorization: EXC-0001.
- Commit and push the validated branch. Do not open a PR, merge, deploy, or change the frontend.

## Required approvals

- User explicitly authorized this slice, branch, commit and push, and prohibited PR/merge.
- No production release or architectural scope expansion is requested.
