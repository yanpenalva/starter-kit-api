# ADR 0010 — Autenticação HTTP exclusivamente por sessão

- Estado: aceito.
- Substitui parcialmente: ADR 0002 (tokens de integração) e ADR 0004 (autoridade do perfil).
- Data: 2026-10-12. Owner: yanpenalva.
- Evidência: solicitação desta sequência de slices; branches feat/slice-6-admin-mfa-optional (677a8ec) e feat/slice-10-session-profile-authority (37e1ddf). As branches estão entregues e pushadas, mas este ADR não declara integração ou deploy.

## Contexto

O ADR 0002 permitia tokens Bearer para integrações aprovadas. A decisão atual do
produto e a implementação desta API são Sanctum por sessão, cookie e CSRF somente.
O perfil também precisava refletir mudanças persistidas na conta mesmo quando uma
sessão existente continuasse aberta.

## Decisão

- A autenticação HTTP usa Sanctum stateful, cookie de sessão e CSRF. A API não emite
  nem aceita Bearer, PAT, JWT ou access token de integração.
- Login regenera e estabelece a sessão; logout invalida a sessão atual. Nenhuma
  credencial de API separada pode substituir esse fluxo.
- A sessão identifica o usuário autenticado; ela não é a fonte da identidade,
  elegibilidade ou permissões. O endpoint de perfil recarrega o usuário e suas
  relações RBAC do banco antes de serializar o perfil.
- MFA administrativo opcional protege o fluxo de login por meio da mesma sessão.
  MFA não altera o transporte nem cria um segundo tipo de sessão autenticada.
- Regras de origem CORS, domínio/lifetime da sessão e TLS de produção continuam
  pendentes até a decisão de deploy.

## Consequências e verificação

Permissões revogadas, identidade atualizada e desativação persistida devem aparecer
na leitura do perfil da sessão existente. A autorização de cada mutação privilegiada
é revalidada dentro da transação conforme ADR 0011. Testes exercitam login, logout,
CSRF, MFA, perfil atualizado e ausência de autoridade baseada em IDs ou papéis
enviados pelo cliente.

O estado de integração continua sendo o registrado por cada work item e branch. Este
ADR registra a regra pretendida e entregue nas slices citadas; não afirma que os
commits já estejam em main ou produção.
