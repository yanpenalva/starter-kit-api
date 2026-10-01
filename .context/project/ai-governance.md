# AI Governance Context

The normative baseline is `.context/policies/core/ai-governance.md`.

- Current harness: Codex; project-scoped CSD skill installed. Other active harnesses: `NOT FOUND`.
- Models/accounts approved organization-wide, data classification and retention: `NOT FOUND`.
- Human approval is required for material decisions, risk acceptance, policy exceptions and production operations. The agent cannot infer production approval from Git delivery authorization.
- No secrets, credentials or personal data in context/evidence.
- This onboarding authorizes only CSD changes, branch commits and branch push; API PR opening is authorized. No merge, force push, application scaffold or production operation is authorized.
- Future tasks resolve Git finalization authorization from their own request. Current session authorization does not authorize future tasks.
- Execute in the current repository. The original `starter-kit-laravue` is read-only reference; never modify it or the CSD source checkout.
- Organizational security owner, release approver and reporting channel: `NOT FOUND`.
