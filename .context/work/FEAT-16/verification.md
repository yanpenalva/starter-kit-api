# Verification — FEAT-16 (primeira fatia, wave 2)

## Evidence (executed 2026-10-03, real)

- `spatie/laravel-permission ^8.3.0` instalado via composer no container PHP 8.5 (composer.json/lock, dono único).
- Migration coordenada `2026_10_03_000002_create_permission_tables.php` (schema oficial 8.3, teams desabilitado por config).
- Catálogo: enums `App\Enums\Permission` (8 aprovados) e `App\Enums\Role` (admin/user); `RbacCatalogueSeeder` firstOrCreate idempotente com asserts de integridade (8/2, guard web) e proibição de atribuição a contas; `User` com `HasRoles` + `guard_name=web`.
- Policies de fundação: `UserPolicy` (view/update/readCpf/setStatus sem self-deactivation/assignRoles sem self-elevation e last-admin safe), `RolePolicy` (admin role imutável).
- Cache: `forgetCachedPermissions()` pós-commit (`DB::afterCommit`) + `unsetRelation('roles')`.

## Commands

| Command | Result |
| --- | --- |
| composer require (PHP 8.5 container) | installed, lock updated |
| `composer test` (`-p starter-wave2-core`) | 33 passed / 163 assertions |
| `composer lint` / `composer analyse` | PASS 46 files / No errors |

## Limits

Nenhum endpoint público; user auto-assign só wave 4; exclusão/CRUD extra wave 5; issue #16 aberta.

## Re-run delta — B1/B2 (2026-10-03)

Gate de elegibilidade nos policies + B2 self-assignment; matriz negativa unverified/deactivated para as 7 habilidades e regressão self-assign direto. Pest re-executado: **36 passed, 185 assertions**; lint PASS 46 files; analyse No errors.

**Checkpoint onda 2 (2026-10-04):** catálogo/seeders/policies base integrados via PR #33 (`main` `4f7c74d`); issue [#16](https://github.com/yanpenalva/starter-kit-api/issues/16) permanece aberta (evolução na onda 5). Escopo progressivo preservado.

**Checkpoint wave A (2026-10-06, branch `feat/wave-a-rbac-contract`):** matriz revisada e registrada em [ADR 0007](../../../docs/adr/0007-matriz-rbac.md). Delta de código: `UserPolicy::setStatus` ganha o parâmetro de transição e nega desativar o último admin **ativo**; `User::otherAdminsRemain` conta apenas admins ativos; seeder verifica o **conjunto exato** (não só contagem) e sincroniza a role `user` com zero permissões; gates executáveis das 8 habilidades no AppServiceProvider. Contrato wave B preparado (Requests/Resources/Filters/paginação, sem rotas/CRUD). Testes novos: `RbacAbilityMatrixTest`, `AdminContractTest`. Ambiente de execução do agente sem PHP/Docker daemon/PostgreSQL/Redis — Pest/Pint/Larastan NÃO executados nesta fatia (NOT FOUND); execução pendente no container/CI do projeto.
