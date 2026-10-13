# ADR 0011 — RBAC, auditoria e roles customizadas

- Estado: aceito.
- Substitui parcialmente: ADR 0004 (regras de autorização), ADR 0005 (estado e allowlists de auditoria) e ADR 0007 (administração de roles).
- Reafirma: catálogo fechado de permissões do ADR 0008.
- Data: 2026-10-12. Owner: yanpenalva.
- Evidência: Slice 8 foi integrada pela PR #86 (custom-role detail/rename); Slice 7 (4266fe8) e Slice 11 (2765b36) seguem como evidência de branches, não como comportamento integrado neste main. A PR #79 integrou o grant ceiling na atribuição de roles; a PR #83 integrou o ciclo de vida de sessões, coberto pelo ADR 0010.

## Contexto

Os ADRs originais definiram menor privilégio, trilha Activitylog e role admin
protegida. A PR #79 integrou o grant ceiling; a PR #86 integrou detalhe e rename de
roles customizadas. Os códigos de auditoria para mutações de usuários e a recarga
do ator contra corridas de privilégio pertencem às slices 7 e 11 e não estão
presentes no main resultante destas merges.

## Decisão

- Permissões continuam sendo definidas pelo enum e provisionadas pelo seeder. Não
  existe CRUD HTTP para criar definições de permissão.
- Desde a PR #79, um ator não-admin só pode atribuir roles cujas permissões estejam
  todas no conjunto efetivo de permissões do próprio ator. O conjunto inteiro é
  avaliado; incluir uma role acima do teto nega a atribuição.
- Role admin é imutável. Roles reservadas não podem ser renomeadas; roles
  customizadas têm operações explícitas de detalhe e rename. Atribuições existentes
  persistem depois de uma renomeação; não se remove role enquanto estiver atribuída.
- No main após a PR #86, mutações privilegiadas entram em `PrivilegeTransaction::run`;
  no PostgreSQL o lock advisory transacional `727423` as serializa. `RenameRole`
  bloqueia a role alvo e reaplica a Policy com o ator recebido, sem recarregá-lo.
  A Slice 11 propõe `runWithActor`, que recarrega e bloqueia o ator antes da Policy
  para as ações que o utilizam; essa proteção ainda não está integrada neste main.
  A gravação de auditoria fica na mesma transação e sua falha reverte a operação.
- A auditoria integrada no main após a PR #86 usa `privileged.change` e
  `cli.bootstrap`, com ações `role.store`, `role.update_permissions`,
  `role.destroy` e `role.rename`. As ações `user.store`, `user.update`,
  `user.destroy`, `user.status`, `user.assign_roles` e `admin.bootstrap` pertencem
  à ampliação das slices 7/11 e ainda não constam no allowlist atual do main.
  O feed integrado filtra eventos, subject type, período e causer; ele não oferece
  filtro por código de ação. Não se deve afirmar que o allowlist de ações governa
  esse filtro até que o contrato e a implementação correspondentes sejam integrados.
- O envelope de propriedades permite apenas `correlation_id`, `actor_type`,
  `actor_id` e `action`, com valores validados por tipo. Descrição livre e
  propriedades não aprovadas não são registradas nem expostas pelo feed.
- Eventos são retidos por 90 dias e limpos diariamente pelo agendamento da aplicação.
  Activitylog não é armazenamento inviolável; retenção de backups e owner operacional
  continuam NOT FOUND.

## Consequências e verificação

Tentativas de alterar a role admin, renomear roles reservadas ou filtrar ações fora
da allowlist são negadas. A Slice 11 adiciona testes de autoridade obsoleta e
concorrência no PostgreSQL, mas essa proteção ainda não está integrada neste main.
Na implementação atual de `RenameRole`, a Policy é reaplicada com o ator recebido,
que não é recarregado dentro da transação. A PR #86 cobre os detalhes da role,
rename, atribuições preservadas e proteção de roles reservadas; o trabalho da Slice
11 continua separado. Os testes da branch da Slice 11 também cobrem rollback de
auditoria e proteção do último administrador.

Este registro descreve o contrato das slices citadas. A integração de cada branch e
a eventual aceitação em main permanecem separadas.
