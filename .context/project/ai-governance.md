# AI Governance Context

The normative baseline is `.context/policies/core/ai-governance.md`.

- Historical onboarding snapshot (2026-10-02): harness observed at onboarding time was Codex; project-scoped CSD skill installed. Other active harnesses at that time: `NOT FOUND`. Superseded as "current" by the 2026-10-03 record below; kept as dated history, not overwritten.
- Models/accounts approved organization-wide, data classification and retention: `NOT FOUND`.
- Human approval is required for material decisions, risk acceptance, policy exceptions and production operations. The agent cannot infer production approval from Git delivery authorization.
- No secrets, credentials or personal data in context/evidence.
- This onboarding authorizes only CSD changes, branch commits and branch push; API PR opening is authorized. No merge, force push, application scaffold or production operation is authorized.
- Future tasks resolve Git finalization authorization from their own request. Current session authorization does not authorize future tasks.

- Later explicit user choice approves OpenCode/Oh My OpenCode for the Starter Kit roadmap. At that 2026-10-02 snapshot, Codex remained the observed harness; plugin installation and available native agent identities were not verified then. At execution startup, map configured logical roles to actual available agents without inventing capabilities or identities.
- The onboarding limits above are historical. [Standing roadmap authorization](../work/FEAT-6/approval.md) now covers board/issue delivery, automatic Git finalization and conditional integration for these two repositories. It does not grant blanket approval for undefined production operations or unrelated tasks.
- Execute in the current repository. The original `starter-kit-laravue` is read-only reference; never modify it or the CSD source checkout.
- Organizational security owner, release approver and reporting channel: `NOT FOUND`.
- 2026-10-03 (UTC; 2026-10-02 local America/Sao_Paulo) — the user explicitly authorized internal content ("Autorizar conteúdo interno"): `ai.data_classification` is now `internal` in `.context/config.json`. Minimized internal code/docs of both roadmap repositories may be shared with the models/subagents of the existing harness. Excluded: `.env`, secrets, credentials, PII and dumps. No new model, provider or account was created and no retention claim beyond existing tooling behavior is asserted. **Current, unambiguous state: the observed harness is OpenCode** with the specialist roles explorer/librarian/oracle/fixer/designer (not Codex); the observed model `openai/gpt-6.1-sol` ran only on the parent orchestrator — no specialist model IDs are invented and no Oh My OpenCode installation is claimed. OpenCode/Oh My OpenCode were already approved tool choices; nothing was installed in this step. The legacy repository remains a read-only static reference consulted with minimum context and no sensitive data extracted. This bullet records the current state; the dated Codex bullets above remain historical snapshots (onboarding vs. now distinguished).
