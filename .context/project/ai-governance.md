# AI Governance Context

The normative baseline is `.context/policies/core/ai-governance.md`.

- Human approval is required for material decisions, risk acceptance, policy exceptions, and production operations. The agent cannot infer production approval from routine Git delivery authorization.
- No secrets, credentials, or personal data in context or evidence.
- Execute in the current repository. The reference repository `starter-kit-laravue` is read-only; never modify it.
- Data classification is `internal` per `.context/config.json`. Minimized internal code and documentation may be shared with approved models and subagents. Excluded: `.env`, secrets, credentials, PII, and dumps.
- Organizational security owner, release approver, and reporting channel: `NOT FOUND`.
