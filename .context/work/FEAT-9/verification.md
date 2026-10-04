# Verification — FEAT-9

## Evidence (executed 2026-10-03, all real)

- Migrations/Model/Factory/Rule: `database/migrations/2026_10_03_000001_create_users_table.php` (users + password_reset_tokens + partial unique index `users_cpf_unique ... where cpf is not null`), `app/Models/User.php` (fillable/hidden/casts), `app/Rules/Cpf.php` (normalizing check-digit rule; null/empty pass as optional), `database/factories/UserFactory.php` (states unverified/deactivated/withCpf; deterministic valid-CPF generator).
- Guarded destructive ops: every schema test asserts `starter_api_test` before running; `RefreshDatabase` targets the isolated test PostgreSQL only; dev database asserted absent by ScaffoldTest.
- Tests (`docker compose --profile test run --rm test`): **15 passed, 63 assertions** — including duplicate CPF rejected by the database (23505-class QueryException on `users_cpf_unique`) and the required **concurrency duplicate-insert** test (second PDO connection with `lock_timeout='500ms'` blocked/failed against the pending unique index entry, message referencing `users_cpf_unique`).
- CPF: leading-zero vector `00000000191` stored verbatim; masked input `529.982.247-25` normalized by rule; repeated-digits and bad-checksum vectors rejected; multiple NULL CPFs valid.
- Sessions truth: compose `SESSION_DRIVER=file` (dev) / `array` (test) preserved; no sessions table created.

## Commands

| Command | Result |
| --- | --- |
| `docker compose --profile test run --rm test` | 15 passed / 63 assertions |
| `composer analyse` (Larastan 10, no baseline) | No errors |
| `composer lint` (Pint) | PASS 12 files |

## Limits

- No RBAC/audit/seeders/admin (wave 2+); no endpoints; no destructive dev-data command. Issue #9 stays open (progressive).

## Re-review delta (2026-10-03) — Oracle conditional PASS dispositions

- F1 (FEAT-10): fixture agora exige ambiente testing|local E flag-file; regressão real com APP_ENV=production + flag presente → 404 (POST /testing/csrf-echo) e /up 200; smoke local intacto. Sem deployment real — apenas config simulada de produção sobre PG de teste e servidor efêmero com guards existentes.
- F2 (FEAT-9): normalização digits-only antes do state (`withCpf('529.982.247-25')` → `52998224725`); zeros à esquerda preservados; geração opcional/null mantida; regressão adicionada.
- F3 (FEAT-9): `cpf` em `$hidden`; serialização (toArray + jsonSerialize) sem password/remember_token/cpf.
- F4 (FEAT-10, opcional): rationale CORS no spec; nenhuma mudança de comportamento.
- Contadores atualizados após re-run (ver comandos abaixo); nenhum confirmatory PASS afirmado antes do re-check do revisor.

## Re-run delta — fixes F1–F4 implementadas (2026-10-03)

Comandos re-executados após as correções: `composer validate --strict` (válido); `composer check-platform-reqs` (success, PHP 8.5.11); `composer audit` (sem advisories); `composer lint` (PASS, 28 arquivos); `composer analyse` (Larastan 10, no errors); `docker compose --profile test run --rm test` → **17 passed, 74 assertions** (inclui regressões novas: fixture 404 em APP_ENV=production com flag presente, normalização `529.982.247-25`→`52998224725`, serialização sem cpf); smoke HTTP real (csrf-cookie/419/204, CORS, throttle 429) dentro da suite; CSD strict `--examples` exit 0; unittest 7 OK; doctor healthy 1.2.1; `git diff --check` clean. Conflito de estabilidade do throttle resolvido com cache `file` + limpeza guardada de `storage/framework/cache/data` antes da janela (servers efêmeros `php -S` de processo único, sem orfãos).

## Fatia 2 — wave 2 (schema RBAC/audit, 2026-10-03)

Executada após a integração da fatia 1: migrations coordenadas `_000002_permission_tables` (Spatie 8.3) e `_000003_activity_log` (Activitylog 5.1.1) + seeders idempotentes, cobertas pelas suítes de [FEAT-16](../FEAT-16/verification.md) e [FEAT-19](../FEAT-19/verification.md) (33 passed na fatia; 38 passed no total da wave-2 core). `_000004_failed_jobs` permanece da lane async. Histórico da fatia 1 acima preservado.

**Checkpoint onda 2 (2026-10-04):** fatias de schema RBAC/auditoria + seeders do catálogo integradas via PR #33 (`main` `4f7c74d`, CI SUCCESS); issue [#9](https://github.com/yanpenalva/starter-kit-api/issues/9) permanece aberta (progressiva). Pendência de gate da onda 2 registrada: seed **default** idempotente (`migrate:fresh --seed` 2×) sem `DatabaseSeeder.php` em `main` @ `44aa89a` — correção em lane separada (seeders/DatabaseSeeder + teste exclusivo).

**Fatia 3 — seed default (2026-10-04, consolidação):** commit fonte `2445e44eb51a24510538f300fb030d1130669c0f` (lane `fix/wave-2-default-seeder`) trazido por cherry-pick `-x` como `2399fde` nesta branch `docs/wave-2-gate` → `main` (EXC-0001). **Atribuição**: executor real = lane seed (`starter-kit-api-wave2-seed-worktree`), via orquestrador; este consolidador NÃO reexecutou os gates — números abaixo são o relato do executor, e a CI remota provará o estado combinado. Relato do executor (PHP 8.5, PG 18 isolado): Pest **55 passed / 266 assertions**; Pint **60 files**; Larastan 10 sem erros; `migrate:fresh --seed` **2× standalone** — catálogo **8 permissions / 2 roles / 0 users / 0 assignments**. Igualdade de código provada: `git diff 2445e44 HEAD` nos 2 arquivos = vazio. Gate da onda 2: gap implementado/validado localmente, **promoção do gate aguarda integração + CI final + merge do parent; onda 3 NÃO liberada**.

**Re-review da fonte (cavecrew, 2026-10-04): FAIL — merge bloqueado.** Achados em `2445e44` (`DefaultDatabaseSeederTest`): (1) o env do processo-filho **mescla `DB_URL` herdada** do ambiente pai — `URL` pode sobrepor `DB_DATABASE` e o teste não Provaria o destino real; o guard por `config.database` é insuficiente; (2) exigências do revisor: **`DB_URL => null`** no env do filho + **`assertNull(config('database.pgsql.url'))`** no setup + guard **`TEST_TOKEN`** contra execução paralela. Correção pertence à **lane seed** (fonte); follow-up agendado pelo parent após o terminal desta lane. PR [#35](https://github.com/yanpenalva/starter-kit-api/pull/35) **NÃO será merged** nesta condição; nenhum review de aprovação alegado.

**Correção da fonte aplicada (2026-10-04, consolidação):** o FAIL acima está **superseded como pendência** (história preservada): a lane seed corrigiu em `f796ed32d5366d59c1686b69b9c5713491bd5b78` (apenas `DefaultDatabaseSeederTest.php`: **sentinela de remoção `DB_URL => false`** no env do filho — Symfony Process 8.1.7 descarta variáveis definidas como `false` —, `assertNull` de `database.pgsql.url` no setup, guard **`TEST_TOKEN`** contra runner paralelo — presença, inclusive vazia/`0`, recusa a execução — e **2 testes adversariais novos**) — trazido por cherry-pick `-x` como `5aa7380` nesta branch (codeeq provada: `git diff f796ed3 HEAD` = vazio). **Relato do executor da correção (fixer2, lane seed, via orquestrador; NÃO reexecutado por este consolidador):** Pest **57 passed / 293 assertions**; Pint **60 files**; Larastan 10 sem erros; `migrate:fresh --seed` 2× standalone — **8 permissions / 2 roles / 0 users / 0 assignments**, com precondições de guard verificadas. **Estado (da época)**: aguardando re-review independente e CI; merge bloqueado até reviewer PASS + parent; onda 3 não liberada; nenhuma aprovação alegada.

**Re-review independente (cav, via parent) — PASS (2026-10-04):** fonte `f796ed32d5366d59c1686b69b9c5713491bd5b78` com **codeeq provada** no consolidado (`5aa7380`); suíte **57 passed / 293 assertions** (53 da base + 4 do teste de seed, incl. negativas `DB_URL`/`TEST_TOKEN` com 0 falhas); **CI da PR #35 no head `aedac01` — context/application/GitGuardian todos success** (run [37214604864](https://github.com/yanpenalva/starter-kit-api/actions/runs/37214604864), jobs [application 111472456056](https://github.com/yanpenalva/starter-kit-api/actions/runs/37214604864/job/111472456056) e [context 111472456188](https://github.com/yanpenalva/starter-kit-api/actions/runs/37214604864/job/111472456188)). **Merge da PR #35 executado pelo parent (merge normal, EXC-0001; sem waiver de GitGuardian nesta PR — check passou por conta própria)**: `main` @ **`5604bf5e6fbbdb2c043780c82580f1cabf26293d`** (pais `44aa89a` + `aedac01`). **CI de `main` no SHA do merge — SUCCESS** (run [37215264091](https://github.com/yanpenalva/starter-kit-api/actions/runs/37215264091): [context 111474359982](https://github.com/yanpenalva/starter-kit-api/actions/runs/37215264091/job/111474359982), [application 111474360119](https://github.com/yanpenalva/starter-kit-api/actions/runs/37215264091/job/111474360119)). Artefato em `main`: seeder default cria apenas o catálogo — **8 permissions / 2 roles / 0 users / 0 assignments**, **nenhum admin/default credentials**; escopo de banco somente testing; total **57 testes**. **Gate da onda 2: PASS** (todos os critérios provados, incl. seed idempotente default). **Onda 3: PRONTA para execução — não iniciada**; issue [#9](https://github.com/yanpenalva/starter-kit-api/issues/9) permanece aberta (escopo progressivo; aceite final da issue = parent). Work item FEAT-9 permanece `active`.
