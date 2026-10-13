# Preflight Validation — BUG-40

## Checks

- [x] Request and objective are clear.
- [x] Scope and out of scope agree.
- [x] Evidence supports proposed behavior.
- [x] Plan touchpoints exist or are explicitly approved as new.
- [x] Security and privacy impact is addressed.
- [x] Tests and acceptance criteria are executable.
- [x] Rollback and operational ownership are defined.
- [x] Required human approvals are recorded.
- [x] Applicable core policies were checked.
- [x] Quality commands, thresholds or approved exceptions are identified.
- [x] AI/data/tool permissions are addressed when an agent or external model is involved.
- [x] Subtasks, owners, dependencies and waves are explicit; parallel work has safe boundaries.

## Findings

- O run remoto reproduz a expectativa incompatível com `role.rename`; aplicação e cobertura falham pelo mesmo teste. O gate de cobertura não informa uma queda abaixo de 85%, pois Pest já encerra em falha.
- PRs #82 e #90 avançaram `main` durante o trabalho. O workflow OpenAPI em `21fa573` publicou artifact após duas exportações idênticas; o artifact registra alterações do MFA presentes na `main`.
- Quality #214 confirmou exportação determinística, aplicação e coverage verdes na revisão `871e0b5`, mas freshness falhou porque o snapshot de `main` não inclui o filtro `action` desta PR. `.context/project/api.md` autoriza gerar um candidato local na revisão da PR; após integração, o produtor oficial em `main` valida/publica o artifact correspondente.
- A correção abrange o teste de allowlist e o snapshot candidato OpenAPI. Merge e push estão explicitamente autorizados para a branch e PR informadas; merge da PR está proibido.

## Evidence record

- PR metadata lida: PR #85 aberta, head `203fc7a77d57c9a0418f7071c0017a40ba54f640`, base `main`.
- GitHub Actions run #192 / `38331058231`: application job `115080648821`, coverage job `115080648682`, contract job `115080648839` falharam; context passou.
- PR #87 reporta snapshot SHA-256 `88bcccf993dd74915da9db2dd1a4eaaba1f4354b616054fe8b74c70042a3fb66`, duas exportações idênticas e CI Quality #199 aprovado.
- Regras consultadas: `.context/project/quality.md`, `.context/project/delivery.md`, `.context/policies/core/code-quality.md`, `.context/policies/core/security-privacy.md`, `docs/onboarding.md`.

## Verdict

`READY`

Reviewer: self preflight (Codex; execução delegada não disponível nesta sessão)
Date: 2026-10-13
