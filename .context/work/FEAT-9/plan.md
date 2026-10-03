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
