# Review — BUG-40

## Inputs

- Request: fix the three failing gates in existing PR #85 after integrating latest `main`; push only to that PR.
- Spec: `.context/work/BUG-40/spec.md`
- Plan: `.context/work/BUG-40/plan.md`
- Diff: one-line assertion correction after main synchronization commits
- Verification: `.context/work/BUG-40/verification.md`
- Applicable policies: `.context/policies/core/code-quality.md`, `.context/policies/core/security-privacy.md`, `.context/project/delivery.md`
- Exceptions: `EXC-0001`

## Correct

- The added expected value matches the already allowlisted `role.rename` action.
- `main` was synchronized through `21fa573` (PR #90), and its official OpenAPI artifact was reviewed. The PR candidate was then generated twice from this branch and compared byte-for-byte; it adds the expected `action` query parameter absent from main's artifact.
- Merge conflict resolutions retained both explicit audit action codes from this branch and actor-policy revalidation from upstream.

## Problems and risks

- Priority: required contract gate
- Location: PR #85 remote checks
- Evidence: run #214 on `871e0b5` passed application and coverage, but failed snapshot freshness. Run #215 on `e601e99` passed application, coverage, deterministic export, snapshot freshness, structural validation and context; oasdiff failed because the base snapshot says `mfa_enabled: string` while the current `main` implementation/export says `boolean`.
- Impact: contract compatibility remains red due to this upstream API contract discrepancy. Silently changing runtime behavior or weakening the compatibility gate would be unsafe.
- Recommendation: get the API owner decision on how to handle the `mfa_enabled` type correction, then make only the corresponding change in PR #85 and re-run its gates.

## Baseline checklist

- [x] No new critical reliability or security issue.
- [x] No unexplained complexity or duplication regression.
- [x] Tests and actual command evidence are present.
- [x] Each material check records command, scope, exit code, date and limitations.
- [x] Compatibility, data and rollback risks are addressed.
- [x] Sensitive data and AI-use controls are addressed.
- [x] Subtask boundaries, wave dependencies and integration owner are evidenced.

## Verdict

`APPROVED WITH CONDITIONS` — producer self-review only; remote CI and independent PR review remain outside this local verdict.
