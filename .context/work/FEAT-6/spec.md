# Specification — FEAT-6

## Scope

Criar roadmap canônico da API e SPA com estado observado, ondas por dependências, complexidades, frentes paralelas, limites de arquivos, gates e instrução reutilizável para outros modelos usarem CSD. Cobrir Marco A funcional/documentado e Marco B de produção. O último steering inclui configurar o CSD para delegação/revisão por agentes, Git e PR automáticos, e registrar autorização humana prévia para quadro e integração condicional.

## Out of scope

Implementação de features, instalação de pacotes, runtime, edição no frontend/legado, alteração do quadro/issues, aprovação de contratos pendentes, commit/push/PR e deploy nesta fatia solicitada.

## Acceptance

- Todos os 47 itens do quadro aparecem uma vez no catálogo, com link ao repo correto.
- Sequência separa escrita de auditoria obrigatória antes de mutações privilegiadas do feed posterior.
- Dependências RBAC/bootstrap decompostas sem mudar silenciosamente as issues.
- Testes, CI, OpenAPI e docs tratados como contínuos, com primeiro E2E no login.
- Complexidades são estimativas técnicas, não campos observados do quadro.
- Prompt de delegação delimita escopo, integração e autoridade; decisões aceitas não são reabertas.
- Prompt revisado para Oh My OpenCode seleciona autonomamente tarefas desbloqueadas, coordena agentes e preserva CSD canônico; considera a confirmação humana de fechamento de API #2/#3.
- Steering final: um orquestrador cobre o projeto inteiro; funções de subagentes definidas, paralelismo dentro da wave ativa e avanço automático entre waves após gates. Itens contínuos são aceitos por fatia, sem bloqueio artificial por trabalho de waves futuras.
- Validação estrita do contexto e testes Python executados; diagnóstico CSD registra resultado real.
- Quadro, commit/push, rotas com/sem PR e merge condicional são automatizados conforme autorização humana registrada; não simular autoaprovação nativa do autor nem contornar branch rules.

## Fatia de correção e entrega — 2026-10-03 (append-only)

Esta fatia formaliza EXC-0001, registra a classificação `internal` aprovada, corrige o estado do roadmap após o merge da PR #30 (API #2/#3 Done com fotografia histórica explicada) e persiste o kickoff da wave 0. O out-of-scope original permanece histórico e vigente por analogia: a correção não implementa features do roadmap, não instala harness, não faz commit/push/PR/remoto nesta passagem e não fecha silenciosamente a issue progressiva API #6 — ela segue aberta, com a transferência privada de OpenAPI pendente de SPA #1.

## Fatia wave 0 — política de transporte e slices documentais (2026-10-03, append-only)

Segunda fatia da wave 0, ainda sem runtime/CI/features:

- Política de transporte privado do OpenAPI aprovada ("Aprovar transporte") e registrada como seção canônica em [api.md](../../project/api.md); producer/consumer não existem.
- Slices iniciais criadas: [FEAT-4](../FEAT-4/spec.md) (definição de gates progressivos; primeiro slice) e [FEAT-22](../FEAT-22/spec.md) (threat model inicial); ambas phase verify/status active, issues abertas.
- FEAT-24: reconhecimento aditivo do CI inicial integrado (PRs #29/#30), sem recriação nem fechamento.
- Roadmap e QUALITY_GATES atualizados apenas o necessário; aceites de arquitetura SPA ("Aprovar pacote SPA") e transporte registrados sem reabrir decisões.
- Wave 0 permanece **incompleta** até revisão/integração destes documentos; nenhuma wave 1 inicia. Issue #6 segue progressiva e aberta; aceite inicial da política completa esta fatia, não a issue (publicação real de endpoint permanece até a wave 7).
