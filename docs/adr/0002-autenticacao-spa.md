# ADR 0002 — Sessão Sanctum para a SPA

- Estado: aceito como registro histórico; a permissão para tokens Bearer está parcialmente substituída pelo [ADR 0010](0010-autenticacao-http-somente-sessao.md).
- Data: 2026-10-01. Owner e aprovador: yanpenalva. Evidência: revisão nesta conversa e solicitação explícita de implementar o plano “Consolidar as decisões dos ADRs”; registro em FEAT-1.

## Contexto e evidência

A [issue #1](https://github.com/yanpenalva/starter-kit-api/issues/1) prevê cookies, sessão e CSRF entre subdomínios do mesmo domínio registrável. A [documentação Sanctum](https://laravel.com/framework/docs/sanctum#spa-authentication), consultada em 2026-10-01, descreve autenticação SPA por sessão, configuração stateful e credenciais CORS.

## Decisão

Decisão histórica: integrações aprovadas poderiam usar tokens Bearer. Essa permissão
foi substituída; o fluxo HTTP atual é somente Sanctum por sessão/cookie e CSRF,
conforme o ADR 0010.

Usar Sanctum stateful na SPA própria. Fluxo previsto: obter `/sanctum/csrf-cookie`, enviar login com credenciais e cabeçalho CSRF e consumir rotas protegidas por `auth:sanctum`. O cliente envia cookies e `X-XSRF-TOKEN`. Na decisão original, tokens Bearer ficavam restritos a integrações específicas aprovadas.

Usar HTTPS em produção, cookie de sessão Secure/HttpOnly e configuração explícita de SameSite e domínio. A cookie CSRF legível pelo cliente não é a cookie de sessão HttpOnly. Autorizar somente origens conhecidas com credenciais; não usar origem wildcard. Configurar hosts stateful com portas de desenvolvimento quando aplicável. Login regenera sessão; logout invalida sessão e renova CSRF. Rate limits precisam ser definidos no item de autenticação.

## Alternativas

Bearer persistido pela SPA altera a superfície de exposição e contraria a intenção registrada. SPA em domínio registrável diferente exige nova decisão arquitetural.

## Impactos, pendências e verificação

Domínios reais, TLS, store de sessão, duração e configuração CORS final: NOT FOUND. Bloqueiam a publicação da autenticação, não o scaffold básico. Verificar login/logout, sessão expirada, rejeição de origem não permitida e mutação sem CSRF. Cookies não substituem Policies/RBAC. Nenhuma rota ou autenticação foi implementada nesta entrega.

## Incorporação do ADR 0006

LDAP excluído da arquitetura atual; autenticação local via Sanctum. Uma necessidade futura exige novo requisito e ADR sucessor. Decisão aprovada nesta conversa; ADR 0006 incorporado em 0001, 0002 e 0004.
