# Plan — FEAT-12

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | core writer (este root) | none | Comando CLI com regras completas + testes (feliz/negativos/idempotência/rollback/default-deny) | 2 | security,testing |
| S2 | independent oracle reviewer | S1 | Preflight/verificação; sem PASS fabricado | 3 | security |

## Preflight proposal (para o revisor; nada executado ainda)

- Arquivos: `app/Console/Commands/PromoteAdmin.php`, `app/Actions/BootstrapAdmin.php` (transação+audit+lock), `tests/Feature/BootstrapAdminTest.php`.
- Comandos: suite PG isolado (`composer test` com `-p starter-wave2-core` se lane async concorrente); lint/analyse; gates CSD.
- Rollback: remoção dos arquivos (comando/action não persistem estado próprio).
- Prod default-deny: exige `--confirm-production` + `APP_ENV=production`; sem essa flag, recusa em produção e executa normalmente fora dele (para testes com PG isolado).
