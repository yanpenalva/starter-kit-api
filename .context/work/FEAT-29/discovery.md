# Product Discovery — FEAT-29 — W2 backend

## Problem

- User/operator: operador administrativo autorizado a consultar a auditoria.
- Situation: o feed permite filtros estruturados, mas ignora `search`; buscar por letra não acompanha o comportamento esperado pelo frontend.
- Pain or opportunity: procurar eventos por nome do ator, descrição/evento e ação sem ampliar o envelope nem coletar PII.

## Evidence

- Observation, research or data: o FormRequest não valida `search`; `ActivityFilters` filtra evento, ação, sujeito, período e causer. `ActivityResource` retorna evento, descrição derivada do evento, ação permitida e IDs, mas não retorna nome/email/CPF. A branch contém alterações preservadas em dois testes de múltiplos perfis.
- Confidence and gaps: alta; revisão de código e testes. O Issue GitHub associado: NOT FOUND; o executor não tem autorização para criar um issue externo.

## Hypothesis

If we implement a case-insensitive substring filter over the actor's current name and the event/action already present in the envelope, then authorized audit operators can find entries while the response remains PII-minimal because names are used only inside the query.

## Success

- Primary metric: focused integration tests prove matching by one-letter terms, case-insensitively, for actor name, description/event and action, with no new PII in the response.
- Baseline: API ignores `search` and returns unfiltered audit results.
- Target: all focused SQLite tests and local lint/static-analysis gates pass; the same database-agnostic test cases are exercised by the PostgreSQL 18 CI gate.
- Observation period: local verification plus required CI for the pushed revision; production observation is outside this task.

## Constraints and alternatives

- Constraints: preserve `logs.view`, Sanctum session/cookie auth, the existing envelope, the action allowlist, and SQLite/PostgreSQL behavior. The official OpenAPI artifact is produced on `main`; this branch receives only the candidate snapshot.
- Alternatives considered: mock-only search, exposing actor names in the resource, or adding identity data to audit properties; all conflict with the approved decision.
- Why now: the approved W2 backend work requires API-backed audit search and evidence that multiple roles persist and refresh correctly.
