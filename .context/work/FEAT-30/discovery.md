# Product Discovery — FEAT-30 — Ferramentas Telescope e debug para API

## Problem

- User/operator: mantenedores da API.
- Situation: o Laravel base não inclui console interativo nem painel de telemetria/debug.
- Pain or opportunity: diagnósticos locais e observabilidade controlada em ambientes não locais.

## Evidence

- Observation: Laravel 13.34 sugere `laravel/tinker`; Guzzle 8.2 já está transitivo. O repo laravue usa Telescope 5.6+, Debugbar 4.x e Laradumps 5.x.
- Confidence and gaps: dependências e constraints conferidos no Composer; issue GitHub associada é NOT FOUND.

## Hypothesis

If these tools are installed with environment-aware controls, then maintainers can diagnose the API with less setup because Laravel packages provide native integrations.

## Success

- Primary metric: PHP lint, PHPStan and available SQLite tests pass; tools stay gated as specified.
- Baseline: none of Telescope/Tinker/Debugbar/Laradumps configured in this API.
- Target: all local gates pass; PostgreSQL/Redis suite remains a CI gate when unavailable.
- Observation period: local verification.

## Constraints and alternatives

- Constraints: Debugbar local only; Telescope filtered and admin-gated outside local; no Horizon.
- Alternatives considered: leave packages external to the project; rejected per explicit request.
- Why now: explicit developer tooling request.
