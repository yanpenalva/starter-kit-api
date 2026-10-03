# Plan — FEAT-4

Definição documental dos gates, sem tocar CI existente. Alternativa de implementar gates já nesta fatia é evitada: a proposta de segurança é advisory e aguarda revisão independente antes de qualquer mudança de workflow.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | fixer (OpenCode) | none | Fatia documental com gates implementados vs. planejados e dona issue/wave por linha | 1 | project,testing |
| S2 | oracle (revisor independente) | S1 | Revisão da proposta de segurança/qualidade; revisão do pai pendente por design nesta fatia | 2 | security,testing |
| S3 | executor + reviewer | S2 | Gates adicionais implementados de forma aditiva, somente após revisão; CI existente intocado | 3 | testing,security |
| S4 | executor | S3 | Consolidação de evidências de gates na wave 7 do roadmap | 4 | testing |

## Data, migration, rollback, and operational risk

Somente documentação; sem banco, runtime, lockfile ou workflow. Reversão pela remoção dos arquivos da fatia. Risco principal: executor futuro confundir gate planejado com implementado — mitigado pelo rótulo de estado obrigatório e pelo mapeamento issue/wave. Nenhuma pendência desta fatia bloqueia o scaffold existente.
