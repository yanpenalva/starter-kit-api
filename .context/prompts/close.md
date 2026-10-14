# Prompt Contract: Observe and Close

## Inputs

Release record, production evidence, declared signals and observation window.

## Produce

`outcome.md` with deployment result, smoke checks, observed signals, rollback/escalation decisions, residual risk and learning. Support incidents also require `postmortem.md` when applicable.

Before closure, produce the Git finalization record in `release.md`: validation evidence, changed-file summary and proposed Conventional Commit message. Resolve `git_finalization_mode` before the first Git action if it is still unresolved: reuse the user's stated preference; otherwise ask, with `confirm_each` as the safe fallback. An unresolved mode never authorizes anything. In `confirm_each` mode, ask separately before commit and push. In `automatic` mode, execute those two operations only for the authorized work item, branch and remote. Bind every delivery to the approved scope; a new branch/work item within the approved roadmap reuses the standing authorization, while a material scope/remote/risk change requires a new decision.

Choose the delivery route per [project delivery context](../project/delivery.md). For an approved route without PR, retain review/gates and record the integrated commit. When a PR is required and `pull_request.mode` is `manual`, draft its title/body; when `automatic`, run the configured PR-creation command after push and record the PR. That command only creates PRs. Conditional merge requires prior human authorization, subject to independent review, checks on the latest SHA and actual repository rules. Never forge an approving review, bypass protection, use force push or destructive reset/clean commands. Production authorization remains separate.

## Constraints

Do not close an item while required evidence, approvals or preventive actions remain unowned. A denied push is not a failed implementation; record it and provide the manual command without retrying automatically.
