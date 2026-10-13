# Specification — BUG-39 Corrigir gates de auditoria e contrato na PR #85

## Request

- Source: solicitação explícita nesta sessão para diagnosticar e corrigir os três gates da PR #85, integrar `main` primeiro, validar Pest e lint e enviar somente à branch existente.
- Requester: yanpenalva
- Date: 2026-10-13

## Objective

Deixar a PR #85 compatível com a `main` atual e corrigir as falhas reproduzíveis de aplicação, cobertura e snapshot OpenAPI.

## Scope

- Integrar `origin/main` (atualizada pelo PR #87) em `feat/slice-7-audit-distinct-action-codes`.
- Alinhar o teste da allowlist à ação `role.rename` já permitida.
- Preservar o snapshot OpenAPI oficial e determinístico trazido de `main` pelo workflow produtor.
- Rodar Pest relevante, lint e validar novamente os gates da PR.
- Entregar somente na branch existente da PR #85.

## Out of scope

- Criar outra branch ou PR, fazer merge de PR #85, exportar/atualizar `contracts/openapi.json` localmente, ou alterar comportamento de produção sem evidência necessária.

## Evidence and context

- Repository evidence: `Audit::allowedActions()` inclui `ACTION_ROLE_RENAME`; a expectativa do teste não.
- Product or incident evidence: Quality run `38331058231` falhou em application, coverage e freshness do snapshot; contexto passou.
- Relevant project context: `.context/project/quality.md`, `.context/project/delivery.md`; `contracts/openapi.json` só é produzido pelo workflow oficial em `main`.
- Unknowns (`NOT FOUND`): resultado dos gates no novo SHA antes do push.

## Rules and contracts

- Business or operational rules: ação de auditoria permitida permanece coberta pelo teste exato da allowlist.
- Interfaces, data and permissions: sem mudança intencional de API, esquema ou permissões nesta correção.
- Security and privacy impact: relevante ao contrato de auditoria; não enfraquecer a allowlist nem incluir dados sensíveis.

## Tests and acceptance criteria

- A branch inclui `origin/main` atual (`9f3ee25`, merge de #87).
- O Pest focado de auditoria/roles passa.
- `composer lint` passa.
- Snapshot é o artefato oficial de `main`; os gates remotos application, coverage e contract passam na PR #85.

## Open decisions

- Nenhuma. A autorização explícita limita o push à branch da PR #85 e proíbe merge.
