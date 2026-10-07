# Agent Instructions

Read [.context/INDEX.md](.context/INDEX.md) and follow its canonical onboarding or daily-work route; `.context/` is the sole normative source.
Project commands and validation adaptations: [.context/project/quality.md](.context/project/quality.md). Git scope and delivery: [.context/project/delivery.md](.context/project/delivery.md).
Developer setup: [docs/onboarding.md](docs/onboarding.md). Project-scoped CSD skill: `.agents/skills/csd/SKILL.md` (Codex: `$csd`).

## Repository conventions

- Use TDD: add/adjust a focused test, observe failure, implement, then run relevant gates.
- Prefer SQLite-first for fast local feature tests; CI and the canonical Compose `test` profile exercise PostgreSQL 18.
- Do not use PostgreSQL `ilike` outside an explicit driver guard; tests must cover supported database behavior.
- Authentication is Sanctum session/cookie-only. Do not add bearer tokens or personal access tokens.
- HTTP tests authenticate through the shared `ActsAsSanctumUser` helper; preserve CSRF/session semantics.
- Keep API contract changes intentional and compatible; update tests and documentation with behavior changes.
- `contracts/openapi.json` is generated only by the OpenAPI producer on `main`; review its artifact and version the snapshot update.
- Never commit secrets, local `.env` files, generated credentials, or commented-out code (including examples).
- Keep examples executable and aligned with current Compose services and scripts; avoid speculative commands.
- Run `composer test`, `composer lint`, and `composer analyse` as appropriate for changed application behavior.
- Run `python3 scripts/validate_context.py --strict` for context/documentation changes.
- CI quality workflows and `.context/` policies govern when local shortcuts differ.
