# Plan — FEAT-6

Construir um único roadmap em project/, ligado pelo README e pelo overview. O catálogo completo deriva da consulta do quadro, e a sequência é confrontada com dependências das issues e invariantes dos ADRs. Alternativa de copiar o roteiro entre repos é evitada por risco de divergência; cada repo conserva seus próprios work items e planos.

Refinamento de S2 solicitado pelo usuário: substituir o prompt limitado a um ciclo por missão de projeto completo, com funções definidas para os subagentes, uma wave ativa por vez e gates/checkpoints para avanço e retomada. Reclassificação desnecessária: permanece planejamento documental, sem instalação, código ou execução remota.

Último refinamento de S2: materializar autorização prévia de quadro/Git/integração, configurar CSD multi-agent/automatic e definir rotas com/sem PR. Consultar documentação oficial do GitHub sobre aprovação própria e auto-merge. Não alterar políticas/schemas para permitir bypass; representar a decisão humana já dada e manter gates sobre o último SHA. A mudança de configuração amplia a entrega documental anterior, sem executar features, ações remotas ou instalação.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | Codex | none | Consulta paginada completa dos 47 itens e verificação das dependências críticas | 1 | project,architecture |
| S2 | Codex | S1 | Roadmap com ondas, complexidade, paralelismo, gates e catálogo | 2 | architecture,security |
| S3 | Codex | S2 | Validação estrita, testes Python, diagnóstico CSD e consistência documental | 3 | testing |
| S4 | yanpenalva/revisor independente | S3 | Revisão da sequência antes de distribuir entregas; decisões materiais em seus work items | 4 | project,architecture |
| S5 | fixer (OpenCode) | S3, S4 | Política de transporte privado do OpenAPI em api.md + referências em FEAT-6 e QUALITY_GATES | 5 | project,security |
| S6 | fixer (OpenCode) | S5 | Slices iniciais FEAT-4/FEAT-22 e reconhecimento aditivo FEAT-24; roadmap atualizado | 6 | testing,security |
| S7 | oracle + pai | S6 | Revisão independente dos slices; gate da wave 0 antes de qualquer wave 1 | 7 | security,testing |

## Data, migration, rollback, and operational risk

Somente documentação. Sem banco, instalação ou alteração Git/remota. Reversão por remoção do arquivo e links, mantendo aprovações anteriores. Principal risco é outro modelo confundir estimativa com estado executado: roadmap diferencia propostas, fotografia do quadro e requisitos aprovados. Dependências que exigem decomposição devem ser registradas antes de código, sem mudar o quadro nesta etapa.
