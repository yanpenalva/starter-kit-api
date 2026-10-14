# Delivery and Deployment Context

- Each task maps to a GitHub Issue; implementation links Issue → CSD work item → commit/PR per `conventions.md`.
- Small changes, Conventional Commits and real implementation/test/validation evidence are required.
- Human authority remains required for material architecture decisions, risk and production. Routine Git delivery and conditional merges require prior human authorization; never force push or bypass repository rules.
- Project Status: start `In progress`; independent review/PR readiness `In review`; `Done` only after integration in the target branch, passing applicable gates and full acceptance.
- Deployment, rollback procedure, release approver, production infrastructure and CI commands: `NOT FOUND`.

## Delivery Authorization and Routes

Use direct integration only for low-impact documentation/tooling when branch rules permit and applicable review/gates pass; use PRs for application code, migrations, authentication, authorization, audit, runtime/CI/deploy configuration or changes requiring PRs. All routes preserve traceability and checks on the exact delivered revision. Approval by an independent agent is not a GitHub approving review when it uses the author's account.

Inspect current rules and identities before selecting a delivery route. Automatic merge requires required checks/reviews satisfied; do not use administrative bypass or weaken rules. Production targets, secrets, infrastructure and irreversible operations retain their concrete human decision gates.

The configured PR command only creates a PR. Before invoking it, the delivery agent supplies CSD_PR_TITLE and CSD_PR_BODY_FILE with a reviewed title/body; write actual newlines to a temporary body file and safely quote arguments. Verify gh installation/authentication and the intended repo/branch first. Merge is a separate conditional operation under recorded prior approval, never hidden inside the PR-creation command.
