# Plan — FEAT-9

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | API integrator (único escritor) | none | Migration + Model + Factory do User conforme spec; testes de constraint verdes no PG isolado | 1 | security,testing |
| S2 | independent oracle reviewer | S1 | Preflight/verificação da fatia antes da implementação; sem PASS fabricado | 2 | security |

## Preflight proposal (concreto, para o revisor; nada executado ainda)

- Arquivos: `database/migrations/xxxx_create_users_table.php` (extensão da padrão), `xxxx_create_password_reset_tokens_table.php`, `app/models/User.php`, `database/factories/UserFactory.php`, `app/Rules/Cpf.php` (regra de normalização/checksum), `tests/Feature/UsersSchemaTest.php`.
- Comandos: `docker compose --profile test run --rm test` (Pest no PG isolado); `docker compose run --rm --no-deps api composer lint` e `composer analyse`; gates CSD (strict `--examples`, unittest, doctor).
- Rollback: remoção dos arquivos novos + `migrate:fresh` no banco de teste isolado; nenhum dado de dev tocado; branch de entrega descartável.
- Aceite: ver spec.md. Unknown material para o oracle: formato final do índice único parcial (índice parcial `WHERE cpf IS NOT NULL` vs. unique constraint composto) — proposta: índice único parcial, decisão reversível.

## Correção de preflight (Oracle PASS, 2026-10-03)

- Índice único parcial `WHERE cpf IS NOT NULL` **aprovado**; teste de **concorrência de insert duplicado é obrigatório**: segunda conexão PDO com `lock_timeout` curto deve aguardar/ser rejeitada na entrada do índice único (55P03) ou violar a constraint (23505) — prova de serialização no banco.
- Verificação de nome do banco (`starter_api_test`) por asserção antes de qualquer operação destrutiva; banco dev `starter_api_dev` ausente/inalterado.

## Fatia 2 — wave 2: schema RBAC/audit e seed (planejamento, 2026-10-03)

Segunda fatia sob a [decisão da wave 2](../FEAT-6/approval.md): coordenar e executar as migrations de catálogo e auditoria (`*_000002_permission_tables` — Spatie 8.3 schema oficial; `*_000003_activity_log` — Activitylog 5.1.1 schema oficial, donos: core writer), mantendo `*_000004_failed_jobs` intocado (lane async) e a ordem final integrada pelo pai. Detalhes por issue: [FEAT-16](../FEAT-16/spec.md), [FEAT-19](../FEAT-19/spec.md), [FEAT-12](../FEAT-12/spec.md), [FEAT-21](../FEAT-21/spec.md). Nenhuma tabela speculativa de sessions; nenhuma promoção real.

## Fatia 3 — wave 2: seed default idempotente (consolidação, 2026-10-04)

Critério de gate da onda 2 ("seed idempotente no caminho default", issue #9) sem `DatabaseSeeder.php` em `main` @ `44aa89a` (evidência explorer). Correção produzida pela **lane seed separada** (`fix/wave-2-default-seeder`, commit fonte `2445e44eb51a24510538f300fb030d1130669c0f`: `DatabaseSeeder.php` + `DefaultDatabaseSeederTest.php`, somente esses 2 arquivos) e consolidada nesta branch via cherry-pick `-x` (registro da fonte preservado no commit `2399fde`). Branch de entrega: `docs/wave-2-gate` → `main` (remote `origin`), EXC-0001; sem alteração de código além do pick; revisão independente em curso.

**Follow-up da correção de revisão (2026-10-04):** guardas de isolamento endurecidos pela lane seed em `f796ed32d5366d59c1686b69b9c5713491bd5b78` (1 arquivo de teste) — cherry-pick `-x` como `5aa7380`. Mesma branch/alvo/EXC-0001; codeeq provada; ver [verification](verification.md).
