# ADR 0008 — Catálogo de permissões fechado

- Estado: aceito
- Data: 2026-10-07

Permissões continuam definidas pelo enum `Permission` e provisionadas pelo seeder RBAC; a API administrativa não cria nem remove definições de permissão. A API pode administrar usuários e roles, incluindo atribuir permissões existentes às roles. Uma nova capacidade de autorização exige alteração revisada no código e atualização do catálogo/seeder, preservando um contrato estável para rotas, policies e testes.
