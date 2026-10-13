# Plan — FEAT-17

## Approach

Reusar `Role::User` e a transação que já cria a conta pública. Tornar `roles` obrigatório e não vazio em `StoreUserRequest`; atribuir `Role::User` no controller público. Não tocar no request de PUT.

## Subtasks and waves

| Subtask | Scope | Depends on | Acceptance | Wave |
| --- | --- | --- | --- | --- |
| FEAT-17-REGRESSION | Ajustar testes HTTP administrativo e público; observar falhas em SQLite | none | Ausência/vazio falham; cadastro público espera perfil básico | 1 |
| FEAT-17-IMPLEMENT | Ajustar validação e atribuição dentro da transação; atualizar documentação | FEAT-17-REGRESSION | Regressões passam; sem alteração no PUT/catalog | 2 |
| FEAT-17-VERIFY | Rodar testes focados, Pint, Larastan 10, validação CSD; revisar diff | FEAT-17-IMPLEMENT | Gates exit 0 e diff limitado | 3 |
| FEAT-17-DELIVERY | Commit e push para branch/remote autorizados | FEAT-17-VERIFY | SHA local e remoto conferidos | 4 |

## Risk and rollback

Risco médio: atribuição pública toca autorização, mas o papel existente não concede permissões. Testes provam a role atribuída e a tentativa pública de enviar `admin` não altera o resultado. Reversão por `git revert` do commit; nenhuma migração ou operação de dados.

## Delivery

- Branch: `feat/be-user-create-with-roles`; remote: `origin`; target de integração convencional: `main`.
- Commit e push solicitados explicitamente nesta conversa. Issue #17 cobre este escopo, está fechada e não será reaberta; PR, merge e deploy fora do pedido.
