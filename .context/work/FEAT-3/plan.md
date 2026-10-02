# Plan — FEAT-3

1. Derive the minimum contract from accepted ADRs and API #13; use the bounded legacy inventory only to avoid repeating known risks.
2. Record framework-neutral conventions in `.context/project/api.md` and a draft OpenAPI 3.1 document in `.context/project/openapi.yaml`.
3. Review schema consistency, route names, status codes, session/CSRF boundaries, and sensitive-field exclusion.
4. Defer Scramble installation and generated publication to API #11, when real endpoint code exists.
5. Ask for human review of the public contract before implementing dependent authentication endpoints.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | Codex | none | HTTP conventions and initial OpenAPI draft | 1 | project,architecture |
| S2 | yanpenalva | S1 | Human review approves the full public contract before API #13 merge | 2 | architecture,security |
| S3 | API #11 | S2 | Scramble-generated and frontend-consumable OpenAPI artifact | 3 | architecture,testing |

## Data, migration, rollback, and operational risk

Documentation only. No database or runtime changes. Changes are reversible; an accepted external contract will require compatibility discipline after clients consume it. Production CORS, domains, cookie lifetime, and readiness signals remain unresolved.
