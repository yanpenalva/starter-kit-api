# Review — FEAT-28 — Slice 12

The implementation owner reviewed the full changed scope against the approved
specification and repository evidence:

- The ADR index contains one successor for session-only authentication, one for
  RBAC/audit/custom roles, and one for OpenAPI release. Historical ADRs remain
  records of their original decisions and point to successors where needed.
- Release guidance matches `.github/workflows/openapi.yml`, the `contract` job in
  `.github/workflows/quality.yml`, and the provenance helper/CLI behavior. It does
  not claim that the feature branch can publish or update the canonical snapshot.
- Onboarding links to release, provenance, and vertical-slice guidance. The
  vertical recipe preserves Sanctum session/CSRF, `ActsAsSanctumUser`, allowlisted
  filters, SQLite-first feedback, and the project quality gates.
- Canonical API and security context no longer labels implemented authentication,
  RBAC, audit, producer or compatibility gates as unimplemented.
- Post-merge review checked the contract guide and successor ADRs against the
  workflows and current main behavior after PRs #79, #83, and #86. It corrected
  stale branch/snapshot claims, documented the #79 role-grant ceiling and #83
  session lifecycle, and distinguished #86's integrated role rename from the
  actor-refresh and user-audit work still on slices 7/11.
- The Slice 12 work item is now FEAT-28 because FEAT-27 is the existing active
  session record from PR #83; the original API Issue #27 reference is preserved.
- No frontend, runtime source, workflow, dependency manifest, or OpenAPI snapshot
  changed.

The focused docs/Pest checks and static gates passed as recorded in
`verification.md`. An independent reader was not available in this execution; the
full Python suite is limited by the missing pinned `oasdiff` binary on this
`aarch64` host.

## Follow-up review — 2026-10-12

Addressed the three high findings:

- The release guide and its onboarding, ADR 0012, vertical-slice, and provenance
  references now distinguish the PR snapshot candidate from the main-only producer
  artifact published after integration.
- ADR 0011 now says that `runWithActor` reloads and locks the actor, while
  `RenameRole` locks the target role and uses the supplied actor without reloading.
- The audit action allowlist now includes `role.rename`.

The conflict resolutions and follow-up changes do not alter application behavior,
workflows, tests, or the OpenAPI snapshot. The merge commit also brings in the
already integrated main changes from PRs #79, #83, and #86.

## Latest main sync review — 2026-10-13

- `origin/main` now includes PR #89's privilege-mutation work and PR #87's OpenAPI
  envelope snapshot. The API context and successor ADRs distinguish the covered
  actor-refresh Actions from `RenameRole`, which still uses the supplied actor, and
  distinguish integrated user event logging from the Slice 7 action codes that
  remain branch-only.
- The current API context records the collection envelope and nullable fields from
  PR #87. Onboarding keeps one link to the canonical release/provenance guides and
  the vertical-slice recipe.
- The merge changes runtime and snapshot files only through the already integrated
  `origin/main` parent. The documentation branch introduces no API behavior change;
  its regenerated snapshot must match main byte for byte.
