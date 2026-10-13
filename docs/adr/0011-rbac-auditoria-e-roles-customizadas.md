# ADR 0011 — RBAC, auditoria e roles customizadas

- Estado: aceito.
- Substitui parcialmente: ADR 0004 (regras de autorização), ADR 0005 (estado e allowlists de auditoria) e ADR 0007 (administração de roles).
- Reafirma: catálogo fechado de permissões do ADR 0008.
- Data: 2026-10-12. Owner: yanpenalva.
- Evidência: PR #79 integrou o grant ceiling na atribuição de roles; PR #86 integrou detalhe e rename de roles customizadas; PR #89 integrou a recarga do ator para as Actions de mutação de usuário e para store, update de permissões e delete de roles. `RenameRole` ainda usa o ator recebido sem recarregá-lo. A Slice 7 (4266fe8), com códigos de auditoria distintos para ações de usuário, permanece evidência de branch, não comportamento integrado.

## Contexto

Os ADRs originais definiram menor privilégio, trilha Activitylog e role admin
protegida. As PRs #79 e #86 integraram o teto de concessão, detalhe de role e rename
de roles customizadas. A PR #89 integrou o recheck de autoridade atual para as
Actions de usuário e as Actions de store, update de permissões e delete de roles.
O rename continua fora dessa recarga de ator.

## Decisão

- Permissões continuam sendo definidas pelo enum e provisionadas pelo seeder. Não
  existe CRUD HTTP para criar definições de permissão.
- Um ator não-admin só pode atribuir roles cujas permissões estejam todas no
  conjunto efetivo de permissões do próprio ator. O conjunto inteiro é avaliado;
  incluir uma role acima do teto nega a atribuição.
- Role admin é imutável. Roles reservadas não podem ser renomeadas; roles
  customizadas têm operações explícitas de detalhe e rename. Atribuições existentes
  persistem depois de uma renomeação; não se remove role enquanto estiver atribuída.
- As Actions de usuário e as Actions de store, update de permissões e delete de
  roles usam `PrivilegeTransaction::runWithActor`: em PostgreSQL, depois do lock
  advisory compartilhado, limpam o cache de permissões, recarregam e bloqueiam o
  ator e então repetem a Policy. `RenameRole` ainda usa `PrivilegeTransaction::run`,
  bloqueia a role alvo e reaplica a Policy com o ator recebido, sem recarregá-lo.
- A auditoria usa `privileged.change` e `cli.bootstrap`; o allowlist de ações
  integrado contém `role.store`, `role.update_permissions`, `role.destroy` e
  `role.rename`. Mutação de usuário também grava evento `privileged.change`, mas
  ainda sem um código de ação por operação; os códigos distintos da Slice 7 não
  estão integrados. O feed filtra evento, subject type, período e causer, não código
  de ação.
- O envelope de propriedades permite apenas `correlation_id`, `actor_type`,
  `actor_id` e `action`, com valores validados por tipo. Descrição livre e
  propriedades não aprovadas não são registradas nem expostas pelo feed.
- Eventos são retidos por 90 dias e limpos diariamente pelo agendamento da aplicação.
  Activitylog não é armazenamento inviolável; retenção de backups e owner operacional
  continuam NOT FOUND.

## Consequências e verificação

Tentativas de alterar a role admin, renomear roles reservadas ou atribuir permissões
além do teto são negadas. A PR #89 adiciona regressões para autoridade obsoleta,
concorrência no PostgreSQL, rollback de auditoria e proteção do último administrador
nas Actions cobertas por `runWithActor`. `RenameRole` permanece uma exceção à
recarga do ator; as ações de auditoria por usuário da Slice 7 também permanecem
separadas.

Este registro descreve o contrato das slices citadas. A integração em `main` não
afirma deploy ou aceitação operacional em produção.
