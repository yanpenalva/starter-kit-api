# Plan — FEAT-16

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | core writer (este root) | none | Migration `*_000002_permission_tables`, enums do catálogo, seeders idempotentes, Policies/Gates, `UserHasRoles` (guard web) | 2 | security,testing |
| S2 | independent oracle reviewer | S1 | Preflight/verificação da fatia; sem PASS fabricado | 3 | security |

## Preflight proposal (para o revisor; nada executado ainda)

- Arquivos: `database/migrations/*_000002_permission_tables.php`, `app/Enums/Permissions.php`+`Roles.php`, `database/seeders/RbacCatalogueSeeder.php`, `app/Policies/*`, `bootstrap` providers, composer.json/lock (sanctum já presente; `laravel-permission:^8.3.0` pós-preflight), `tests/Feature/RbacCatalogueTest.php`.
- Comandos: composer require no container PHP 8.5; `composer test` PG isolado; `composer lint/analyse`; gates CSD.
- Rollback: remoção de arquivos + entrada do lock; migrações reversíveis (down dropSchema das tabelas Spatie).
- Execução: após preflight PASS, dono único (core writer); lane async em worktree próprio não edita estes arquivos.
