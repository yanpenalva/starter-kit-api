# Plan — BUG-39 Corrigir gates de auditoria e contrato na PR #85

## Approved approach

Fazer merge de `origin/main` na branch já associada à PR #85 antes de editar; manter o snapshot `contracts/openapi.json` do commit oficial de #87; ajustar somente a lista esperada para incluir `role.rename`. O teste falhou no run remoto, fornecendo a reprodução antes da alteração.

## Confirmed touchpoints

- Files/components/services: `tests/Feature/Admin/AuditDistinctActionsTest.php`, `contracts/openapi.json` (integração do snapshot de `main`), `.context/work/BUG-39/*`.
- Interfaces and data: sem nova mudança de API, esquema ou persistência.
- Dependencies: `vendor` permanece symlink existente; nenhum `composer install`.

## Execution order

1. Fazer merge de `origin/main` (`9f3ee25`, #87) na branch alvo e resolver apenas conflitos de integração necessários.
2. Conferir snapshot oficial e determinismo registrado pelo produtor de #87.
3. Acrescentar `Audit::ACTION_ROLE_RENAME` à expectativa da allowlist e rodar Pest focado.
4. Rodar `composer lint`, inspecionar diff e validar contexto atualizado.
5. Commitar e fazer push somente para `origin/feat/slice-7-audit-distinct-action-codes`; confirmar PR #85 continua aberta e não mesclada.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | yanpenalva | none | Merge da `main` e snapshot oficial integrado | 1 | core,project |
| S2 | yanpenalva | S1 | Teste da allowlist atualizado e Pest focado verde | 2 | project,testing,security |
| S3 | yanpenalva | S2 | Lint, revisão do diff e CI da PR #85 | 3 | project,testing |

## Test plan

- Baseline observada: Pest falha no teste da allowlist no run #192.
- Após a correção: `vendor/bin/pest tests/Feature/Admin/AuditDistinctActionsTest.php tests/Feature/Admin/CustomRoleManagementTest.php`.
- Formatação: `composer lint`.
- Contexto: `python3 scripts/validate_context.py --strict`.
- CI remoto: jobs application, coverage (mínimo 85%) e contract na PR #85.

## Risks and rollback

- Risk: integração da `main` inclui código e snapshot de mudanças previamente integradas por #87 e #89.
- Mitigation: preservar as alterações upstream, limitar a correção local ao teste de allowlist, validar o diff e manter o snapshot do produtor.
- Rollback: reverter o commit de correção na branch da PR; o merge da `main` permanece como integração solicitada.

## Required approvals

- Autorização explícita da sessão: merge de `main` nesta branch e push somente para PR #85; sem merge da PR.
- EXC-0001 cobre o fluxo de entrega do roadmap; branch `feat/slice-7-audit-distinct-action-codes`, base `main`, remote `origin`.
