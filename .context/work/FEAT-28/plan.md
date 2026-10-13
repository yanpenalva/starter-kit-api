# Plan — FEAT-28 — Slice 12

## Approved approach

Describe the already implemented workflow in a canonical release guide and detailed provenance recipe, document current decisions in successor ADRs, and remove contradictory status text from canonical API context. Do not change the workflow or API behavior. When syncing newer main, retain its snapshot update and verify deterministic regeneration without introducing an independent contract change.

## Confirmed touchpoints

- Files/components/services: docs/adr, docs/onboarding.md, docs/contract-release.md, docs/openapi-contract-recipe.md, docs/vertical-slice-recipe.md, .context/project/api.md, tests/tools.
- Interfaces and data: OpenAPI release artifact and versioned snapshot process; no runtime or schema change.
- Dependencies: existing workflow and quality gate; no new dependency.

## Execution order

1. Fetch the updated main and integrate it into this delivery branch without rewriting its published history.
2. Resolve documentation and work-item collisions while preserving the main records for merged slices.
3. Write the release/provenance guides, vertical-slice recipe, successor ADRs, ADR index updates, and accurate API context.
4. Run strict context validation, review the final diff, then push the branch to its existing PR.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| FEAT-28-DOCS | yanpenalva | none | Current ADRs and linked release/vertical-slice guides | 1 | core,project,architecture,security,release |
| FEAT-28-VERIFY | yanpenalva | FEAT-28-DOCS | Python/context/PHP/Pest results and diff review | 2 | core,project,testing |

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

- Branch: `feat/slice-12-contract-release-docs`; updated against `origin/main` at `9f3ee254a651dbd8cf61eb0fc7d0b65e928a0fa7` (PRs #89 and #87).
- Target: `main`; remote: `origin`; existing PR: #90; authorization: EXC-0001.
- Push the validated branch to PR #90. Do not create another PR, merge PR #90, deploy, or change the frontend.

## Required approvals

- The user authorized fetching and merging/rebasing `origin/main`, resolving documentation conflicts, validating strict context, and pushing only to existing PR #90. PR merge is prohibited.
- No production release or architectural scope expansion is requested.
