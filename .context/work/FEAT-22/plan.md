# Plan — FEAT-22

Threat model inicial documental a partir da revisão Oracle advisory, ADRs aceitos e runtime real do scaffold. Implementação de controles e testes é das issues donas; esta fatia não cria testes nem runtime.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | fixer (OpenCode) | none | Matriz inicial com ativos, fronteiras e estados honestos | 1 | security,architecture |
| S2 | oracle (revisor independente) | S1 | Revisão de segurança da matriz; revisão do pai pendente por design | 2 | security |
| S3 | executor + reviewer (por feature) | S2 | Controles e testes negativos entregues pelas issues donas em cada wave | 3 | security,testing |
| S4 | executor + security-privacy-reviewer | S3 | Testes de abuso reais na wave 7 e consolidação para eventual fechamento | 4 | security,testing |

## Data, migration, rollback, and operational risk

Somente documentação; reversão pela remoção dos arquivos. Risco principal: matriz lida como controle implementado — mitigado pela coluna de estado e pelo mapeamento issue/wave. A matriz evolui por fatia em cada wave; nenhuma decisão aceita é reaberta.
