# Specification — FEAT-17

## Objective

Fazer toda conta criada pelos dois fluxos HTTP nascer com perfil: o POST administrativo deve receber uma lista não vazia; o registro público deve receber `user` por padrão.

## Scope and rules

- `POST /api/v1/users`: `roles` é obrigatório, array com ao menos um papel existente e distinto; ausência ou vazio retorna 422 antes da criação da conta.
- `/api/v1/register`: atribuir `Role::User` na transação de cadastro. O catálogo já o sanciona como perfil de menor privilégio e o seeder não lhe concede permissões; não criar papel novo nem promover permissões.
- Dados `roles`/`active` enviados pelo público continuam sem autoridade para escolher papel ou estado. A conta continua pendente/desativada até verificação.
- PUT de atribuição permanece como está: lista não vazia obrigatória; vazio não remove todos os perfis.
- Atualizar documentação de arquitetura próxima ao contrato. `contracts/openapi.json` permanece fora desta branch conforme regra de produção do artefato em `main`.

## Security and compatibility

A validação administrativa e a autorização `users.assign-roles` permanecem na fronteira existente. O perfil público `user` não concede permissões. Nenhuma migration, papel, permissão, rota ou forma de autenticação será adicionada.

## Acceptance

- Red/green focado em SQLite para ausência/vazio no POST administrativo, sem linha em `users`, e role `user` no cadastro público.
- Teste confirma que o cliente público não consegue escolher `admin`.
- Teste de contrato confirma que PUT vazio continua 422.
- Lint, Larastan nível 10, testes focados SQLite e validação CSD strict terminam com exit 0.
- Commit e push do resultado validado para `origin/feat/be-user-create-with-roles`; sem merge/deploy.
