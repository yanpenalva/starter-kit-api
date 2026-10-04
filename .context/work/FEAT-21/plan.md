# Plan — FEAT-21

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | core writer (este root) | none | Correlação estruturada + redação verificadas nos fluxos privilegiados | 2 | security,testing |
| S2 | independent oracle reviewer | S1 | Preflight/verificação; sem PASS fabricado | 3 | security |

## Preflight proposal (para o revisor; nada executado ainda)

- Arquivos: `app/Support/Correlation.php` (geração/propagação), middleware ou contexto de log registrado no provider, `tests/Feature/LogRedactionTest.php`.
- Comandos: suite PG isolado; lint/analyse; gates CSD.
- Rollback: remoção dos arquivos; canais padrão preservados.
- Sem novas dependências/ferramentas; log channel stderr já configurado pelo compose.
