# ADR 0007 — Matriz RBAC revisada e contrato de administração (wave A)

- Estado: aceito. Registra a revisão wave A da matriz aprovada em FEAT-16 e prepara o contrato de usuários/roles para a wave B.
- Data: 2026-10-06. Owner: yanpenalva. Evidência: execução da wave A na branch `feat/wave-a-rbac-contract`, sobre o catálogo já integrado (PR #33, `main` `4f7c74d`) e aprovado em FEAT-16.

## Contexto

O catálogo RBAC inicial (8 permissões, roles `admin`/`user`, guard `web`) foi aprovado no FEAT-16 e integrado. Esta revisão inclui as capacidades administrativas `users.manage` e `roles.manage`, necessárias para criar/remover contas e roles; as definições permanecem sob enum/seeder conforme ADR 0008.

## Matriz aprovada e extensões administrativas do Bloco 3

| Permissão | Habilidade | Proteções de objeto |
| --- | --- | --- |
| `users.manage` | Criar/remover contas | sem autoexclusão; preserva o último admin ativo |
| `users.view` | Listar/ver contas | nenhuma de objeto (gate) |
| `users.update` | Editar atributos básicos | nenhuma de objeto |
| `users.set-status` | Ativar/desativar conta | sem autodesativação; desativar admin exige outro admin ativo |
| `users.assign-roles` | Substituir roles do alvo | sem auto-atribuição; preserva o último admin ativo |
| `users.read-cpf` | Ler CPF do alvo | titular por perfil próprio; gestão só com a permissão |
| `roles.view` | Listar/ver roles | nenhuma de objeto (gate) |
| `roles.update-permissions` | Reescrever permissões de role | role `admin` imutável |
| `roles.manage` | Criar/remover roles | remoção negada quando a role está atribuída |
| `logs.view` | Listar trilha de auditoria | nenhuma de objeto (gate; consumidor wave 6) |

Roles: `admin` carrega todas as permissões do catálogo; `user` carrega nenhuma permissão administrativa (o seeder sincroniza vazio em toda execução).

## Regras protegidas (revisadas)

- Elegibilidade: todo ator administrativo precisa estar verificado e ativo (`email_verified_at` preenchido, `deactivated_at` nulo), independentemente de permissão/role.
- Último admin: remover a role `admin` ou desativar a conta de um admin é negado quando nenhum outro admin **ativo** permanece. Admin desativado não conta como em pé.
- Autoaplicação: ator não pode desativar a si mesmo nem atribuir roles a si mesmo.
- Role `admin` imutável: `roles.update-permissions` nunca reescreve o conjunto do admin.
- Limite de concessão: exceto o ator com role `admin`, a role de destino não pode receber permissões fora do conjunto efetivo de permissões do próprio ator.
- Mutação transacional: toda mutação de privilégio roda em `PrivilegeTransaction` (transação PostgreSQL + advisory lock `727423`), com trilha fail-closed via `App\Support\Audit`; falha de auditoria reverte a mutação.
- Seeder: `RbacCatalogueSeeder` é idempotente e verifica o **conjunto exato** (não apenas contagem) de permissões e roles; `user` sem permissões; nenhuma conta criada ou promovida.

## Fiação executável

- Gates (AppServiceProvider): cada permissão do catálogo vira um gate `can('<permissão>')` = verificado + ativo + permissão direta/por role. Gates decidem habilidades sem objeto (listagens); rotas wave B podem usar `middleware('can:users.view')`.
- Policies (`UserPolicy`, `RolePolicy`): proteções de objeto acima; a camada de mutação da wave B deve consultá-las **além** do gate, dentro da transação de privilégio.

## Contrato preparado para a wave B (sem rotas/CRUD nesta wave)

Esboço de superfície proposto (nomes definitivos na wave B):

| Método | Caminho | Habilidade | Request |
| --- | --- | --- | --- |
| POST | `/api/v1/users` | `users.manage` | `StoreUserRequest` |
| GET | `/api/v1/users` | `users.view` | `IndexUsersRequest` |
| DELETE | `/api/v1/users/{user}` | `users.manage` | — (hard delete; suspensão temporária usa `POST .../status`) |
| PATCH | `/api/v1/users/{user}` | `users.update` | `UpdateUserRequest` |
| POST | `/api/v1/users/{user}/status` | `users.set-status` | `SetUserStatusRequest` |
| PUT | `/api/v1/users/{user}/roles` | `users.assign-roles` | `AssignUserRolesRequest` |
| GET | `/api/v1/roles` | `roles.view` | `IndexRolesRequest` |
| POST | `/api/v1/roles` | `roles.manage` | `StoreRoleRequest` |
| DELETE | `/api/v1/roles/{role}` | `roles.manage` | — |
| PUT | `/api/v1/roles/{role}/permissions` | `roles.update-permissions` | `UpdateRolePermissionsRequest` |

- Requests (`app/Http/Requests/Users`, `app/Http/Requests/Roles`): `authorize()` aplica o gate da habilidade; validações definem a forma dos payloads (filtros `search`/`status`/`role`, paginação `page`/`per_page` limitada a 100, `roles.*`/`permissions.*` restritos ao catálogo).
- Resources (`app/Http/Resources/Users`, `app/Http/Resources/Roles`): `UserResource` (CPF apenas com `users.read-cpf` para o alvo), `RoleResource`.
- Filtros (`app/Support/Filters`): `UserFilters`, `RoleFilters` — chaves allowlist, `ilike` com escape, ignora chaves desconhecidas.
- Paginação: envelope padrão do Laravel para coleções de resources (`data` + `links` + `meta` com `current_page`/`last_page`/`per_page`/`total`).

## Consequências

Nenhum endpoint novo. A wave B implementa rotas/controllers/serviços sobre este contrato, reutilizando policies e `PrivilegeTransaction`; alterações no contrato passam por revisão própria.
