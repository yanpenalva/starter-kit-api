# 🏛️ Decisões arquiteturais incrementais

Origem: [API #1](https://github.com/yanpenalva/starter-kit-api/issues/1). Data: 2026-10-01.

A fonte normativa permanece em [`.context/`](../../.context/INDEX.md). As decisões arquiteturais foram aceitas por yanpenalva, conforme registro de aprovação e decisões posteriores registradas nesta pasta. Aprovação arquitetural não comprova implementação.

| ADR | Tema | Estado | Aplicação |
| --- | --- | --- | --- |
| [0001](0001-api-spa-independentes.md) | Fronteiras e estrutura Laravel | Aceito | Scaffold |
| [0002](0002-autenticacao-spa.md) | Sanctum e sessão SPA | Aceito; permissão histórica para bearer tokens superada por [0010](0010-autenticacao-http-somente-sessao.md) | Autenticação |
| [0003](0003-persistencia-contratos.md) | PostgreSQL, dependências e OpenAPI | Aceito; release e compatibilidade esclarecidos por [0012](0012-contrato-openapi-e-release.md) | Scaffold e contrato |
| [0004](0004-identidade-rbac.md) | Cadastro, verificação, ativação, CPF e RBAC | Aceito; ativação detalhada em [0009](0009-ativacao-por-verificacao-email.md), sessão/perfil em [0010](0010-autenticacao-http-somente-sessao.md) e mutações em [0011](0011-rbac-auditoria-e-roles-customizadas.md) | Identidade e RBAC |
| [0005](0005-auditoria.md) | Activitylog v5 e retenção de 90 dias | Aceito; [0011](0011-rbac-auditoria-e-roles-customizadas.md) registra o allowlist integrado, a auditoria transacional de usuário e os códigos de ação ainda branch-only | Auditoria e feed |
| 0006 (histórico) | Legado | Incorporado nos ADRs [0001](0001-api-spa-independentes.md), [0002](0002-autenticacao-spa.md) e [0004](0004-identidade-rbac.md) | Registro original em FEAT-1 |
| [0007](0007-matriz-rbac.md) | Matriz RBAC revisada, regras protegidas e contrato de administração | Aceito; detalhe atual em [0011](0011-rbac-auditoria-e-roles-customizadas.md) | RBAC e administração |
| [0008](0008-catalogo-permissoes.md) | Catálogo fechado de permissões, sem CRUD HTTP | Aceito | CRUD administrativo |
| [0009](0009-ativacao-por-verificacao-email.md) | Ativação condicionada por motivo de bloqueio | Aceito; substitui parcialmente o 0004 | Autenticação e ativação |
| [0010](0010-autenticacao-http-somente-sessao.md) | Autenticação HTTP exclusivamente por sessão | Sucessor parcial de 0002 e 0004 | Login, MFA e perfil de sessão |
| [0011](0011-rbac-auditoria-e-roles-customizadas.md) | RBAC, auditoria e roles customizadas | Sucessor parcial de 0004, 0005 e 0007; reafirma 0008 | Mutações privilegiadas |
| [0012](0012-contrato-openapi-e-release.md) | Contrato OpenAPI e release verificável | Sucessor parcial de 0003 | Publicação e compatibilidade do contrato |
| [0013](0013-padroes-e-escala-de-codigo.md) | Padrões de código e escala sem reescrita | Proposto | Actions, filtros, afterCommit, recusas e gatilhos de escala |

Owner e aprovador arquitetural: yanpenalva. Owner operacional: NOT FOUND. Preparação técnica: Codex. Work item: FEAT-1.

## Aprovação e evolução

Evidência: respostas humanas às perguntas de revisão e instrução explícita de implementar o plano “Consolidar as decisões dos ADRs”, em 2026-10-01. Decisões futuras substituem ADRs explicitamente, preservando o histórico. Compatibilidade dos pacotes precisa de resolução real no scaffold; documentação não substitui lockfile ou testes.

Cadastro público ativa acesso básico após verificação de email, sem promoção administrativa. O primeiro administrador é promovido por comando restrito. CPF é opcional, validado e único; consulta pelo titular e gestão autorizada. Auditoria usa Activitylog v5, acesso específico e retenção de 90 dias, sem CPF/credenciais; falha de gravação reverte alterações privilegiadas. LDAP fica excluído e textos/regras derivam dos novos requisitos.

Os ADRs sucessores registram decisões já autorizadas e comportamento das fatias; eles não afirmam que branches foram integradas ou implantadas. Decisões futuras substituem ou complementam ADRs explicitamente, preservando o histórico. Retenção em backups e responsabilidades operacionais serão especificadas antes da coleta em produção. Essas pendências não reabrem as escolhas arquiteturais aceitas.
