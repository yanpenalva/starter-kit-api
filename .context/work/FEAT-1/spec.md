# Specification — FEAT-1

## Request and objective

Solicitante/owner: yanpenalva. Origem: https://github.com/yanpenalva/starter-kit-api/issues/1. Em 2026-10-01, o usuário revisou as escolhas e pediu explicitamente implementar o plano “Consolidar as decisões dos ADRs”. Evidência em approval.md.

## Scope

Aceitar os seis ADRs com registro de aprovação humana e sincronizar o contexto normativo. Cadastro público, ativação automática após email verificado, bootstrap restrito e CPF opcional/validado/único no perfil, acessível ao titular e gestão com permissão específica. Activitylog v5, retenção de eventos por 90 dias, consulta específica e rollback de alterações privilegiadas por falha de auditoria. Excluir CPF/credenciais da trilha. Excluir LDAP e criar textos/regras por novos requisitos.

## Out of scope

Scaffold, endpoints, migrations, instalações de aplicação, edição do legado, commit/push/PR, produção, atualização do quadro e encerramento da issue.

## Acceptance criteria

- Seis ADRs com contexto, decisão aceita, alternativas, impactos, owner, fonte e aprovação rastreável nesta conversa.
- Ativação administrativa substituída por automática após verificação, sem promoção ou reativação indevida.
- LDAP/CPF/textos do legado deixam de ser pendências; CPF recebe decisão própria no contrato de identidade.
- `.context/` permanece normativa e consistente com os ADRs; arquitetura aceita não equivale a runtime implementado.
- Validação estrita, variante examples, testes existentes e doctor executados com resultados registrados.

## Deferred implementation decisions

Domínios, infraestrutura, versões efetivamente resolvidas, nomes/matriz de permissões, primeira feature e gerador TypeScript. Retenção em backups, classificação e responsáveis operacionais serão especificados antes da coleta em produção; não reabrem o prazo de 90 dias aceito para eventos.
