# ADR 0005 — Auditoria por eventos e diffs permitidos

- Estado: aceito; decisão arquitetural aprovada, ainda não implementada.
- Data: 2026-10-01. Owner e aprovador: yanpenalva. Evidência: revisão nesta conversa e solicitação explícita de implementar o plano “Consolidar as decisões dos ADRs”; registro em [FEAT-1](../../.context/work/FEAT-1/approval.md).

## Contexto e evidência

A [issue #1](https://github.com/yanpenalva/starter-kit-api/issues/1) compara Activitylog v5 com Auditing v14 e proíbe adoção simultânea por padrão. A política canônica de [segurança e privacidade](../../.context/policies/core/security-privacy.md) limita dados em logs.

| Critério | Activitylog v5 | Auditing v14 |
| --- | --- | --- |
| Foco documentado | Eventos de aplicação e mudanças de modelos | Histórico de alterações em modelos Eloquent |
| Evidência versionada consultada | 5.0.0: PHP ^8.4; Illuminate ^12.0 ou ^13.0 | v14.0.0: PHP ^8.2; Illuminate ^11.0 ou ^12.0 |
| Aderência ao plano Laravel 13 | Manifesto inclui 13; resolução real ainda pendente | Tag consultada não inclui 13; avaliar release concreta antes de considerar |

Fontes consultadas em 2026-10-01: [Activitylog introdução](https://spatie.be/docs/laravel-activitylog/v5/introduction), [manifesto 5.0.0](https://raw.githubusercontent.com/spatie/laravel-activitylog/5.0.0/composer.json), [Auditing introdução](https://laravel-auditing.com/guide/introduction), [manifesto v14.0.0](https://raw.githubusercontent.com/owen-it/laravel-auditing/v14.0.0/composer.json). A evidência dessas tags não afirma incompatibilidade de todas as releases v14 futuras.

## Decisão

Adotar Activitylog v5 como única trilha inicial, com resolução Composer no scaffold antes da instalação. Registrar eventos de ativação/desativação, concessão/revogação de privilégios, bootstrap administrativo e mutações relevantes da primeira feature. Não auditar todo modelo ou todo request automaticamente.

Usar envelope mínimo: identificador de evento, instante UTC, ação, referência interna do ator/alvo, resultado e correlation ID gerado pelo servidor. Referências de identidade têm classificação pendente; minimizar e restringir acesso, sem exportá-las para logs públicos. Diffs usam allowlist explícita por evento; inicialmente vazia até aprovação dos atributos. Proibir senhas, hashes, tokens, cookies, CSRF, CPF, email, payload integral e texto livre. Não coletar IP/user-agent por padrão.

Reter eventos por 90 dias, prazo aprovado nesta conversa, sem afirmar obrigação legal. Classificação, responsabilidades operacionais e retenção/exclusão em backups serão especificadas antes da coleta em produção. O pacote oferece [limpeza programável](https://spatie.be/docs/laravel-activitylog/v5/basic-usage/cleaning-up-the-log); agendamento, prazo e acesso operacional precisam ser configurados e testados.

Para mutações privilegiadas, gravar evento no mesmo commit transacional da operação; se falhar a gravação, reverter a mutação. Negar edição/remoção da trilha pela API; leitura exige permissão específica de consulta à auditoria, e limpeza automática após 90 dias fica restrita ao processo operacional. Activitylog não constitui por si só armazenamento inviolável.

## Alternativas e impactos

Auditing cabe melhor quando o requisito central é reconstruir histórico de modelos; retomar a decisão se essa necessidade surgir. Usar ambos duplicaria captura e retenção. O scaffold vazio pode anteceder a instalação; a auditoria precisa estar implementada antes das mutações privilegiadas.

Testar allowlist/redação, rollback por falha de auditoria, acesso à trilha e limpeza após o prazo. Owner operacional, requisitos regulatórios, backups e atributos permitidos: NOT FOUND; definir antes da coleta correspondente. Ferramenta, retenção de eventos, acesso e rollback por falha estão aprovados. Esta entrega não instala o pacote nem inicia coleta. Reverter a alteração documental preserva a aprovação; excluir trilha real fora da limpeza aprovada exige decisão humana específica.
