# Delivery and Deployment Context

- Each task maps to a GitHub Issue; implementation links Issue → CSD work item → commit/PR per `conventions.md`.
- Small changes, Conventional Commits and real implementation/test/validation evidence are required.
- Human authority remains required for material architecture decisions, risk and production. Routine Git delivery and conditional merges within this roadmap have prior human authorization recorded below; never force push or bypass repository rules.
- Project Status: start `In progress`; independent review/PR readiness `In review`; `Done` only after integration in the target branch, passing applicable gates and full acceptance.
- Deployment, rollback procedure, release approver, production infrastructure and CI commands: `NOT FOUND`.
- Onboarding Issue: https://github.com/yanpenalva/starter-kit-api/issues/5; branch: `chore/issue-5-initialize-csd`.
- User authorized this session's CSD branch, commit and branch push after validation. API PR creation targeting main is also authorized.
- The earlier session authorization above described onboarding delivery. The later roadmap authorization below governs the approved Starter Kit execution; unrelated work defaults to `confirm_each`.
- No application scaffold, Laravel package installation, migrations, authentication implementation, Docker Compose, frontend app implementation, source-kit edits or monolith edits belong to onboarding.

## Standing authorization for the roadmap

yanpenalva explicitly authorized autonomous board updates, commit/push, optional PRs, independent agent review and conditional automatic integration for the Starter Kit roadmap on 2026-10-02. [Scope and human approval](../work/FEAT-6/approval.md) govern API/frontend delivery; no repeated approval per routine commit/PR/merge is necessary within that scope.

Use direct integration only for low-impact documentation/tooling when branch rules permit and applicable review/gates pass; use PRs for application code, migrations, authentication, authorization, audit, runtime/CI/deploy configuration or changes requiring PRs. All routes preserve traceability and checks on the exact delivered revision. Approval by an independent agent is not a GitHub approving review when it uses the author's account.

Inspect current rules and identities before selecting a delivery route. Automatic merge requires required checks/reviews satisfied; do not use administrative bypass or weaken rules. Production targets, secrets, infrastructure and irreversible operations retain their concrete human decision gates.

The configured PR command only creates a PR. Before invoking it, the delivery agent supplies CSD_PR_TITLE and CSD_PR_BODY_FILE with a reviewed title/body; write actual newlines to a temporary body file and safely quote arguments. Verify gh installation/authentication and the intended repo/branch first. Merge is a separate conditional operation under the recorded prior approval, never hidden inside the PR-creation command.

[EXC-0001](../exceptions/EXC-0001.json) (approved 2026-10-03 UTC by yanpenalva, valid until 2026-12-31 or earlier termination on roadmap completion/revocation) formalizes this standing authorization under core.review-release clauses 11/12/14: reuse of the validated commit/push authorization for recorded delivery branches targeting `main` across roadmap work items, and conditional execution of previously human-authorized merges, bounded to yanpenalva/starter-kit-api and yanpenalva/starter-kit-frontend (authorization mirrored into the frontend). Each affected work item MUST reference EXC-0001 in `policy_exceptions` and record its delivery branch, target branch and remote before Git finalization. "Scope change" means leaving that recorded, approved delivery plan of the work item — not the routine creation of a new in-roadmap work item with its own recorded branch and `main` target, which remains covered by the existing roadmap approval; this exception grants no scope authority beyond that approval. An unapproved scope, remote or risk change invalidates automatic authorization until human re-approval as the core requires.
