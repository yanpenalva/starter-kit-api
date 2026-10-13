# Preflight Validation — BUG-39

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
- A geração do contrato no CI é determinística, mas compara contra snapshot anterior. PR #87 já foi integrada à `main` e trouxe o snapshot oficial emitido após duas exportações byte-idênticas.
- A correção local fica restrita ao teste de allowlist. Merge e push estão explicitamente autorizados para a branch e PR informadas; merge da PR está proibido.

## Evidence record

- PR metadata lida: PR #85 aberta, head `203fc7a77d57c9a0418f7071c0017a40ba54f640`, base `main`.
- GitHub Actions run #192 / `38331058231`: application job `115080648821`, coverage job `115080648682`, contract job `115080648839` falharam; context passou.
- PR #87 reporta snapshot SHA-256 `88bcccf993dd74915da9db2dd1a4eaaba1f4354b616054fe8b74c70042a3fb66`, duas exportações idênticas e CI Quality #199 aprovado.
- Regras consultadas: `.context/project/quality.md`, `.context/project/delivery.md`, `.context/policies/core/code-quality.md`, `.context/policies/core/security-privacy.md`, `docs/onboarding.md`.

## Verdict

`READY`

Reviewer: self preflight (Codex; execução delegada não disponível nesta sessão)
Date: 2026-10-13
