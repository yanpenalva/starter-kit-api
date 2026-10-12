# ADR 0011 — RBAC, auditoria e roles customizadas

- Estado: aceito.
- Substitui parcialmente: ADR 0004 (regras de autorização), ADR 0005 (estado e allowlists de auditoria) e ADR 0007 (administração de roles).
- Reafirma: catálogo fechado de permissões do ADR 0008.
- Data: 2026-10-12. Owner: yanpenalva.
- Evidência: branches feat/slice-7-audit-distinct-action-codes (4266fe8), feat/slice-8-custom-role-renaming (8bb3faf) e feat/slice-11-privilege-races (2765b36). Entregas pushadas, ainda não integradas por este fluxo.

## Contexto

Os ADRs originais definiram menor privilégio, trilha Activitylog e role admin
protegida, mas antecedem os códigos de auditoria distintos, operações de detalhes e
rename de roles customizadas, e a revalidação do ator contra corridas de privilégio.

## Decisão

- Permissões continuam sendo definidas pelo enum e provisionadas pelo seeder. Não
  existe CRUD HTTP para criar definições de permissão.
- Role admin é imutável. Roles reservadas não podem ser renomeadas; roles
  customizadas têm operações explícitas de detalhe e rename. Atribuições existentes
  persistem depois de uma renomeação; não se remove role enquanto estiver atribuída.
- Cada mutação privilegiada entra em PrivilegeTransaction, relê o ator sob o lock,
  e aplica novamente a Policy antes de alterar estado. No PostgreSQL, o lock
  advisory transacional `727423` serializa essas mutações; a gravação da auditoria
  fica no mesmo commit e sua falha reverte a operação.
- A auditoria usa as categorias `privileged.change` e `cli.bootstrap`; os códigos
  de ação permitidos são `user.store`, `user.update`, `user.destroy`, `user.status`,
  `user.assign_roles`, `role.store`, `role.update_permissions`, `role.destroy` e
  `admin.bootstrap`. O mesmo catálogo governa escrita e filtros do feed.
- O envelope de propriedades permite apenas `correlation_id`, `actor_type`,
  `actor_id` e `action`, com valores validados por tipo. Descrição livre e
  propriedades não aprovadas não são registradas nem expostas pelo feed.
- Eventos são retidos por 90 dias e limpos diariamente pelo agendamento da aplicação.
  Activitylog não é armazenamento inviolável; retenção de backups e owner operacional
  continuam NOT FOUND.

## Consequências e verificação

Uma tentativa baseada em autoridade obsoleta deve ser negada dentro da transação.
Tentativas de alterar a role admin, renomear roles reservadas ou filtrar ações fora
da allowlist são negadas. Testes cobrem rollback da mutação se o audit falhar,
proteções do último administrador e concorrência na base PostgreSQL do CI.

Este registro descreve o contrato das slices citadas. A integração de cada branch e
a eventual aceitação em main permanecem separadas.
