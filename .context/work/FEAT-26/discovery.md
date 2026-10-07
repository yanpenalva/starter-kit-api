# Discovery

- Track/type: product feature with a related reproducible audit-feed bug.
- Request evidence: user task “Bloco 3 — BE médio/alto” dated 2026-10-07.
- Operator need: administrators require user/role creation and deletion under existing authorization policy; audit entries must be searchable by the responsible actor and must not expose PII.
- Affected audience: verified, active administrators with the new manage permissions; audit readers with `logs.view`.
- Constraints: Sanctum session authentication; permission names remain code-owned enum/seeder values; SQLite-first tests and PostgreSQL 18 CI; no bearer tokens; no feature-branch OpenAPI snapshot generation.
- Success signal: CRUD enforcement and invariants pass focused HTTP tests; audit filter finds writer-created entries; alternate stored PII does not appear in feed output.
- Product owner approval and external issue identifier: NOT FOUND in the request context; scope is the explicit user request.
