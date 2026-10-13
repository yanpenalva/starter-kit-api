# Plan — BUG-40 Corrigir gates de auditoria e contrato na PR #85

## Approved approach

Fazer merge de `origin/main` na branch já associada à PR #85 antes de editar. Revisar o artifact oficial da `main`, gerar o candidato do snapshot na revisão da PR e alinhar a expectativa do teste a `role.rename`, já permitido. O teste falhou no run remoto, fornecendo a reprodução antes da alteração.

## Confirmed touchpoints

- Files/components/services: `tests/Feature/Admin/AuditDistinctActionsTest.php`, `contracts/openapi.json` (candidato gerado da revisão da PR), `.context/work/BUG-40/*`.
- Interfaces and data: sem nova mudança de API, esquema ou persistência.
- Dependencies: `vendor` permanece symlink existente; nenhum `composer install`.

## Execution order

1. Fazer merge de `origin/main` na branch alvo antes da validação; integra #87 (`9f3ee25`, merge `f1c36b7`), #84 (`0955e1e`, merge `6ba1a69`), #82 (`3c76812`, merge `b50e244`) e #90 (`21fa573`, merge `f8960af`).
2. Revisar o artifact do produtor OpenAPI oficial em `main`; gerar o snapshot candidato desta revisão duas vezes com `LC_ALL=C` e comparar bytes, conforme `.context/project/api.md`.
3. Acrescentar `Audit::ACTION_ROLE_RENAME` à expectativa da allowlist e rodar Pest focado.
4. Rodar `composer lint`, inspecionar diff e validar contexto atualizado.
5. Commitar e fazer push somente para `origin/feat/slice-7-audit-distinct-action-codes`; confirmar PR #85 continua aberta e não mesclada.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | yanpenalva | none | Merge da `main`, artifact oficial revisado e candidato OpenAPI da PR identificado | 1 | core,project |
| S2 | yanpenalva | S1 | Teste da allowlist atualizado e Pest focado verde | 2 | project,testing,security |
| S3 | yanpenalva | S2 | Lint, revisão do diff e CI da PR #85 | 3 | project,testing |

## Test plan

- Baseline observada: Pest falha no teste da allowlist no run #192.
- Após a correção: `vendor/bin/pest --configuration=/tmp/codex-api-g7-phpunit-worktree.xml` (SQLite local; configuração temporária por causa do symlink `vendor`).
- Contrato candidato: `LC_ALL=C TZ=UTC php artisan scramble:export --path=/tmp/api-g7-branch-openapi-{1,2}.json`; comparar os dois arquivos e revisar o diff com o artifact oficial.
- Formatação: `composer lint`.
- Contexto: `python3 scripts/validate_context.py --strict`.
- CI remoto: jobs application, coverage (mínimo 85%) e contract na PR #85.

## Risks and rollback

- Risk: integração da `main` inclui alterações de contrato prévias e esta PR adiciona o filtro de consulta `action`.
- Mitigation: preservar as alterações upstream, revisar o artifact oficial, gerar o candidato com a implementação desta PR e validar determinismo/freshness no gate remoto.
- Rollback: reverter o commit de correção na branch da PR; o merge da `main` permanece como integração solicitada.

## Required approvals

- Autorização explícita da sessão: merge de `main` nesta branch e push somente para PR #85; sem merge da PR.
- EXC-0001 cobre o fluxo de entrega do roadmap; branch `feat/slice-7-audit-distinct-action-codes`, base `main`, remote `origin`.
