# Product Discovery — FEAT-17

## Problem

- A criação administrativa aceita hoje usuário sem perfil; o cadastro público também deixa a conta sem vínculo no catálogo.
- O solicitante substituiu a regra anterior: contas internas precisam nascer com perfil e registros públicos recebem um perfil padrão de menor privilégio já aprovado.

## Evidence

- `StoreUserRequest` declara `roles` como opcional e `UsersAdminHttpTest` cobre criação sem perfil e com lista vazia.
- `RegisterController` cria o usuário sem atribuir perfil. `Role::User` existe em `RbacCatalogueSeeder`, é aprovado em FEAT-6 e recebe zero permissões.
- `AssignUserRolesRequest` já exige lista não vazia (`required|array|min:1`); o PUT não será alterado.
- Branch `feat/be-user-create-with-roles` está em `origin`, sem alterações rastreadas locais antes deste trabalho; `vendor/` não rastreado será preservado.
- Issue GitHub relacionada: [#17 — Implementar CRUD administrativo, busca, filtros e paginação](https://github.com/yanpenalva/starter-kit-api/issues/17), cujo escopo cobre criação administrativa e gestão de papéis. Ela está fechada; esta correção de branch não a reabre nem altera o issue.

## Success

Testes HTTP SQLite provam 422 sem criação para perfil ausente/vazio no POST administrativo e persistência de `user` no cadastro público. Gates locais solicitados passam.
