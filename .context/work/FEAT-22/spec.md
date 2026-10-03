# Specification — FEAT-22

## Scope

Fatia documental inicial do threat model da API: ativos, fronteiras e matriz de ameaças com rótulo planejado vs. implementado. Cada controle planejado aponta para a issue/wave dona e para o teste negativo de aceitação correspondente. Execução dos testes de abuso e fechamento da issue permanecem na wave 7.

## Assets

Sessão e CSRF; senha de login; links de email/verificação/reset; estado da conta (não verificado, ativo, desativado); CPF; RBAC e auditoria (retenção 90 dias); payloads de fila (PII); artefato OpenAPI e credenciais de CI.

## Boundaries

Origem SPA (CORS credenciado exato) → API (nginx/FPM) → PostgreSQL isolado (dev e teste separados); Redis e Mailpit apenas como serviços do profile assíncrono, sem worker/integração; CLI de bootstrap operado pelo humano no mesmo banco com gravação de auditoria atômica; CI somente leitura dos repositórios.

## Threat matrix

Estado: `planned` salvo marcação explícita. Implementados somente: health sem vazamento (teste atual existe) e audit de dependências (observado no CI). Nenhuma prova manual de timing uniforme é alegada; nenhum teste negativo futuro é alegado como passando.

| Ameaça | Fonte/mitigação planejada | Teste negativo de aceitação planejado | Issue/wave | Dono (função) | Estado |
| --- | --- | --- | --- | --- | --- |
| Brute force em login | Throttle 429 com Retry-After | Assert 429 e Retry-After após o limite | #10/#13; waves 1/3 | executor + reviewer | planned |
| Fixação de sessão | Regeneração no login; sessão antiga inválida no logout | Cookie antigo rejeitado após login/logout | #13; wave 3 | executor + reviewer | planned |
| Não verificado com acesso além do mínimo | Perfil mínimo, roles vazias, negócio negado | Rota de negócio 403 antes da verificação | #13/#14; waves 3/4 | executor + reviewer | planned |
| Sessão de conta desativada | Desativação explícita bloqueia acesso protegido | 403 com sessão existente | #13; wave 3 | executor + reviewer | planned |
| CSRF em mutações | Token obrigatório; 419 inválido/ausente | Mutação sem token 419 | #13; wave 3 | executor + reviewer | planned |
| CORS/Host spoofing | Origens exatas credenciadas; sem confiança no Host | Origem não permitida rejeitada; Host falso ignorado | #10; wave 1 | executor + reviewer | planned |
| Falha de auditoria em operação privilegiada | Rollback atômico reverte a operação | Falha forçada de audit reverte bootstrap | #12/#16/#19; wave 2 | executor + reviewer | planned |
| Enumeração em login/reset | Respostas não enumeráveis (sem existência) | Mesma forma/status para conta existente ou não | #13/#15; waves 3/4 | executor + reviewer | planned |
| CPF indevido | Null permitido; normalização/validação/unicidade; concorrência; leitura por titular/permissão; sem PII em erros/logs | Inválido/duplicado 422; concorrência rejeitada; leitura não autorizada 403; payloads limpos | #9/#16; waves 1/2 | executor + reviewer | planned |
| Link de email/reset adulterado | URL final construída antes de assinar; expiração; replay negado | Link adulterado/expirado/reutilizado rejeitado | #14/#15; wave 4 | executor + reviewer | planned |
| Mass assignment no cadastro | Role/status não atribuíveis por input público | Payload com role/status ignorado/rejeitado | #14; wave 4 | executor + reviewer | planned |
| Abuso do bootstrap de admin | Conta escolhida+verificada; estrito; idempotente; concorrente-seguro; audit rollback | Não escolhida/não verificada rejeitada; dupla execução idempotente; falha de audit reverte | #12; wave 2 | executor + reviewer | planned |
| Verificação reativando desativada | Verificar email NUNCA reativa conta desativada | Conta verificada-porém-desativada segue 403 | #14; wave 4 | executor + reviewer | planned |
| Feed de auditoria sem permissão/allowlist | Leitura com permissão específica; allowlist inicial VAZIA; sem PII; sem evento/role inventados | Feed 403 sem permissão; saída aderente a allowlist vazia; campos fora da allowlist ausentes | #19; wave 6 | executor + reviewer | planned |
| Retenção de auditoria acima de 90 dias | Limpeza por fronteira + scheduler (futuro) | Eventos acima de 90 dias removidos na janela | #19; waves 6/7 | executor + reviewer | planned |
| Vazamento de PII/fila | afterCommit, redação, exclusão de segredos, isolamento (futuro) | Sem PII/segredo em payloads; falha de fila isolada | #20; wave 2 | executor + reviewer | planned |
| IDOR/escalação horizontal/vertical | Policies/Gates por recurso; identificadores não enumeráveis onde aplicável | Acesso a recurso de outro usuário 403/404 conforme contrato | #16/#17/#18; waves 2/5 | executor + reviewer | planned |
| Vazamento de infra no health | `/api/v1` e `/up` mínimos, sem conexão/host | Respostas sem detalhes de infraestrutura | #7/#23; base | executor + reviewer | implemented (teste atual existe) |
| Dependência vulnerável | `composer audit` no CI | Job falha com advisory | #7/#24; base | executor + reviewer | implemented (observado no CI) |

## Out of scope

- Executar testes de abuso (wave 7); implementar controles; reivindicar controle passando sem teste real; prova de timing uniforme; produção/backups.

## Acceptance

- Cada linha planejada tem dona issue/wave e teste negativo associado; estados honestos.
- ADRs e contrato citados; nada reabre decisões aceitas.
- Issue #22 permanece aberta até a wave 7 com testes de abuso reais; esta fatia não a fecha.
