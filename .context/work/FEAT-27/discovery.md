# Discovery — FEAT-27 — Slice 12

## Problem

- User/operator: API maintainers implementing and releasing vertical slices.
- Situation: the onboarding page summarizes OpenAPI publication, while canonical API context still labels Scramble generation, artifact publication and compatibility CI as unimplemented.
- Pain or opportunity: stale status makes the contract release procedure and the boundary between a feature branch and the official snapshot unclear.

## Evidence

- The API roadmap tracks API #27 as continuous documentation/onboarding work and calls for current contracts and reproducible commands.
- The OpenAPI producer is present at .github/workflows/openapi.yml. It is manually dispatched, guarded to main, performs two exports, and uploads an artifact retained for 90 days.
- The Quality workflow checks snapshot freshness and compatibility. The API context still contains earlier statements that these controls do not exist.
- Several accepted ADRs retain implementation-status language from the initial scaffold.

Confidence: high for the repository evidence. A clean-checkout read-through by another maintainer remains a later acceptance signal.

## Hypothesis

If API maintainers get current successor ADRs, one canonical contract release guide, and a concise vertical-slice recipe, they can implement and release API slices without following stale transport or snapshot instructions.

## Success

- The API documentation describes every producer and contract gate present in the workflows and points to one canonical release guide.
- The vertical recipe identifies scope, tests, local/CI gates, and contract update boundaries.
- Strict context validation passes and relevant PHP/Python checks are recorded.
- Baseline: no current successor ADRs for this delivery set; release facts are summarized in onboarding but contradicted by .context/project/api.md.
- Target: all acceptance criteria in spec.md pass on feat/slice-12-contract-release-docs.

## Constraints and alternatives

- Constraints: work from the refreshed origin/main; commit and push this slice branch; do not create a PR or merge; do not change frontend files; never locally regenerate or change contracts/openapi.json.
- Alternatives: edit .github/workflows/openapi.yml itself. Rejected for this slice because the existing producer matches the intended manual main-only flow; the missing work is accurate, canonical guidance and successor decision records.
