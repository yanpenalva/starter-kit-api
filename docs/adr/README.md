# Decisões arquiteturais incrementais

Origem: [API #1](https://github.com/yanpenalva/starter-kit-api/issues/1). Data: 2026-10-01.

A fonte normativa permanece em [`.context/`](../../.context/INDEX.md). As decisões arquiteturais foram aceitas por yanpenalva, conforme [registro de aprovação](../../.context/work/FEAT-1/approval.md) e decisões posteriores registradas nesta pasta. Aprovação arquitetural não comprova implementação.

| ADR | Tema | Estado | Aplicação |
| --- | --- | --- | --- |
| [0001](0001-api-spa-independentes.md) | Fronteiras e estrutura Laravel | Aceito | Scaffold |
| [0002](0002-autenticacao-spa.md) | Sanctum e sessão SPA | Aceito | Autenticação |
| [0003](0003-persistencia-contratos.md) | PostgreSQL, dependências e OpenAPI | Aceito | Scaffold/primeiro contrato |
| [0004](0004-identidade-rbac.md) | Cadastro, verificação, ativação, CPF e RBAC | Aceito (parcialmente substituído pelo [0009](0009-ativacao-por-verificacao-email.md)) | Feature de identidade |
| [0005](0005-auditoria.md) | Activitylog v5 e retenção de 90 dias | Aceito | Primeira mutação auditável |
| 0006 (histórico) | Legado | Incorporado nos ADRs [0001](0001-api-spa-independentes.md), [0002](0002-autenticacao-spa.md) e [0004](0004-identidade-rbac.md) | Registro original em FEAT-1 |
| [0007](0007-matriz-rbac.md) | Matriz RBAC revisada, regras protegidas e contrato de administração | Aceito | Wave A (RBAC) / wave B (CRUD users/roles) |
| [0008](0008-catalogo-permissoes.md) | Catálogo fechado de permissões, sem CRUD HTTP | Aceito | CRUD administrativo |
| [0009](0009-ativacao-por-verificacao-email.md) | Jornada registro-acesso e ativação condicionada por motivo de bloqueio | Aceito (substitui parcialmente o 0004) | Autenticação / Ativação |

Owner e aprovador arquitetural: yanpenalva. Owner operacional: NOT FOUND. Preparação técnica: Codex. Work item: [FEAT-1](../../.context/work/FEAT-1/work-item.json).

## Aprovação e evolução

Evidência: respostas humanas às perguntas de revisão e instrução explícita de implementar o plano “Consolidar as decisões dos ADRs”, em 2026-10-01. Decisões futuras substituem ADRs explicitamente, preservando o histórico. Compatibilidade dos pacotes precisa de resolução real no scaffold; documentação não substitui lockfile ou testes.

Cadastro público ativa acesso básico após verificação de email, sem promoção administrativa. O primeiro administrador é promovido por comando restrito. CPF é opcional, validado e único; consulta pelo titular e gestão autorizada. Auditoria usa Activitylog v5, acesso específico e retenção de 90 dias, sem CPF/credenciais; falha de gravação reverte alterações privilegiadas. LDAP fica excluído e textos/regras derivam dos novos requisitos.

Domínios, infraestrutura, versões efetivamente resolvidas, nomes de permissões, primeira feature e gerador TypeScript serão definidos nas entregas correspondentes. Retenção em backups e responsabilidades operacionais serão especificadas antes da coleta em produção. Essas pendências de implementação não reabrem as escolhas arquiteturais aceitas nem bloqueiam o scaffold básico.
