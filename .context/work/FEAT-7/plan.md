# Plan — FEAT-7

## Approved approach

Plano explícito aprovado por yanpenalva nesta conversa. Implementação integrada a FEAT-7/8/24, execução single-agent.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | Codex | none | Scaffold oficial e lockfile | 1 | project,architecture |
| S2 | Codex | S1 | Compose e banco teste isolado | 2 | project,security |
| S3 | Codex | S2 | Gates, CI e smoke tests | 3 | testing,security |
| S4 | Codex | S3 | Contexto/documentação e verificação | 4 | project,testing |

Rollback: reverter arquivos desta entrega, sem apagar volumes ou dados. Não alterar legado. Aprovação humana do plano obtida; Git e produção não autorizados.
