# Specification — BUG-41 Serializar mapas vazios de nomes de papéis como objeto

## Request

- Source: solicitação explícita nesta sessão.
- Requester: yanpenalva.
- Date: 2026-10-13.

## Objective

Emitir `{}` para `role_display_names` sem entradas nos endpoints de perfil e usuários.

## Scope

- Converter o mapa do resource para objeto nos dois resources.
- Cobrir o perfil não verificado e o resource de usuário sem papéis.

## Out of scope

- Alterar o contrato OpenAPI, gerar snapshot, modificar outros campos ou tratar qualquer outro achado.

## Evidence and context

- Repository evidence: `contracts/openapi.json` declara `role_display_names` como objeto; os dois resources serializam mapas vazios.
- Product or incident evidence: defeito reproduzido pelo teste do perfil.
- Relevant project context: Sanctum session-only; testes via helper do projeto.
- Unknowns (`NOT FOUND`): nenhum requisito de mudança contratual.

## Rules and contracts

- Business or operational rules: mapa preenchido segue como objeto nome→nome de exibição.
- Interfaces, data and permissions: contrato OpenAPI existente não muda.
- Security and privacy impact: none.

## Tests and acceptance criteria

- JSON de perfil não verificado contém objeto vazio.
- `UserResource` sem papéis mantém objeto vazio.
- Gates lint, analyse e testes SQLite do slice passam.

## Open decisions

- Nenhuma.
