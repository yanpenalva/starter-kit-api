# Plan — FEAT-19

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | core writer (este root) | none | Migration `*_000003_activity_log`, activity helper transacional, eventos aprovados, testes de rollback/privacidade | 2 | security,testing |
| S2 | independent oracle reviewer | S1 | Preflight/verificação; sem PASS fabricado | 3 | security |

## Preflight proposal (para o revisor; nada executado ainda)

- Arquivos: `database/migrations/*_000003_activity_log.php`, `config/activitylog.php` (5.1.1, buffer off, retenção 90), `app/Support/Audit.php` (helper transacional fail-closed com correlation_id), `tests/Feature/AuditWriteTest.php`.
- Comandos: composer require no container PHP 8.5; suite PG isolado; lint/analyse; gates CSD.
- Rollback: remoção de arquivos/lock; migration down drop da tabela de log.
- Coordenação de schema: `*_000002` (FEAT-16) e `*_000003` (FEAT-19) são do core writer; `*_000004_failed_jobs` exclusivo da lane async — nenhuma edição cruzada; ordem final integrada pelo pai.
