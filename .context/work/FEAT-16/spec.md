# Specification — FEAT-16 (primeira fatia, wave 2)

Pacote aprovado por yanpenalva (registro: [FEAT-6 approval.md](../FEAT-6/approval.md), seção "Decisão posterior"): `spatie/laravel-permission` **8.3.0** (PHP ^8.3, Laravel 12/13). Guard: `web` — config oficial do pacote SEM `defaults.guard`; `UserHasRoles` aplicado ao User com `guard_name => web`.

## Catálogo (exato, aprovado)

- Permissões (8): `users.view`, `users.update`, `users.set-status`, `users.assign-roles`, `users.read-cpf`, `roles.view`, `roles.update-permissions`, `logs.view`.
- Roles: `admin` (todas as 8), `user` (nenhuma administrativa; atribuição básica auto somente na wave 4 após verificação).
- Regras protegidas: admin não editável/removível como role; último administrador preservado (`last-admin safe`); sem autoelevação/autodesativação administrativa; exclusão de usuários e permissões extras de CRUD adiadas para a wave 5 (não criadas).

## Regras de pacote/cache

- Cache de permissões: `forgetCachedPermissions()` manual após mutations de catálogo; relações de User recarregadas (`unsetRelation`) após mutation de privilégio e somente após commit da transação; idempotente e seguro a re-execução; seeders manuais via `db:seed` (flush explícito).
- Schema: migration coordenada `*_000002_permission_tables` (schema oficial Spatie 8.3: teams não usado; names/guard_name únicos) — dono: FEAT-16; `*_000004_failed_jobs` é da lane async (não editar).
- Sem PAT, sem login/registro/CRUD, sem seeder de admin real/promoção real.

## Entrega

Branch `feat/wave-2-identity-audit`, alvo `main`, remote `origin`; [EXC-0001](../../exceptions/EXC-0001.json). Testes: factories de roles/permissions verificadas, idempotência de seeders, policies (last-admin/self-elevate negativos), PG isolado.
