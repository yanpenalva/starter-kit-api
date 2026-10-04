# Verification — FEAT-20

## Evidence

- Exact commands and checks: `docker build` (pin phpredis) + `phpversion('redis')`; `composer install` (sem mudança em composer.json/lock); `composer validate --strict`, `check-platform-reqs`, `audit`, `lint` (Pint), `analyse` (Larastan 10 sem baseline); `docker compose -p starter-wave2-async --env-file <primário/.env> --profile test run --rm test` (Pest real PG/Redis/Mailpit); smoke dev `--profile async` (worker/scheduler, `infra:probe`, `infra:smoke-mail`, peek Mailpit por HTTP API); default stack sem perfis (sem Redis/Mailpit) com `/up` 200 e `after_commit=false`; `validate_context.py --strict`/`--strict --examples`; `unittest discover`; `doctor --scope project`.
- Scope analyzed: Dockerfile; compose.yaml; config/queue.php + config/mail.php; migration 000004; app/Console/Commands (3 comandos dev guardados); app/Mail/SyntheticInfraMail; app/Jobs/InfraFailingJob; tests/Concern + 5 classes Feature; scripts/async_dev.sh; .env.example (3 chaves + nota MAILPIT_API_URL); routes/console.php (1 linha guardada consentida); .context/work/FEAT-20/*.
- Results and exit codes: histórico (worktree original, pré-perda): Pest **30 passed, 0 failed, 115 assertions**; Pint 40 files PASS; Larastan "No errors"; audit limpo; CSD ✔; smoke ✔. **Recuperação (root persistente): reexecução fresca registrada abaixo — evidência histórica NÃO substitui prova fresca.**
- Date and environment: 2026-10-03 (original) · 2026-10-03 (recuperação) · **2026-10-04 (pós-merge origin/main + correções F2–F6, esta seção)**. Docker 29.8.2 (build 7fc2dff), Compose v5.6.0, projeto `starter-wave2-async`, imagem `starter-wave2-async-api:local`, PHP 8.5.11, `.env` primário só `--env-file` runtime (nunca lido/impresso).
- Metrics and thresholds: Larastan 10 sem baseline; zero falhas; zero avisos; nenhuma porta pública nova; tmpfs PG teste preservado; base core (RBAC/audit) + async verdes no snapshot combinado.
- Limitations or `NOT APPLICABLE` rationale: produção NÃO testada (guardas provados com prod SIMULADO em container de teste; nenhum deploy). CI remoto não executado (push de branch de recuperação sem PR; PR = parent). Healthcheck worker/scheduler prova conectividade Redis, NÃO consumo de jobs (F4, dev-only). Pint é read-only (sem autofix).

## Recovery record (2026-10-03)

Worktree temporário original perdido sem commit de fonte; versão final F1–F4 reconstruída do contexto retido do implementador no root persistente. Gates frescos obrigatórios executados: ver seção "Fresh recovery gates".

## Fresh recovery gates (2026-10-03)

- Build: `docker build -t starter-wave2-async-api:local .` + `phpversion('redis')` — executado 2026-10-03: `bool(true)`, `6.3.0`, PHP 8.5.11 ✔
- Composer install no root persistente sem alterar composer.json/lock ✔
- Static: Pint/Larastan/validate/audit — registrados em execution.md ✔
- Pest (`--profile test`, projeto isolado): 30 passed, 115 assertions ✔
- CSD strict/--examples/unittest/doctor ✔

## Post-merge gates + correções F2–F6 (2026-10-04, comandos e resultados reais)

Contexto: merge `eef1206` (origin/main `4f7c74d`, PR #33 core) + correções F2/F3/F4/F5/F6. Commit das correções: `9c5f102` (ver nota de governança em execution.md — commit não-executado por este fixer, conteúdo conferido).

| Gate | Comando (resumo) | Resultado real |
| --- | --- | --- |
| Build | `docker build -t starter-wave2-async-api:local .` + `php -r` dentro da imagem | ✔ `bool(true)`, `6.3.0`, PHP `8.5.11` |
| Vendor | `docker compose -p starter-wave2-async run --rm --no-deps api composer install` (lock do main pós-merge) | ✔ instalado (spatie/* do core); `git status` composer.json/lock: limpo |
| validate | `composer validate --strict` | ✔ `./composer.json is valid` |
| platform | `composer check-platform-reqs` | ✔ 24/24 success (php 8.5.11, ext-pdo_pgsql etc.) |
| audit | `composer audit` | ✔ `No security vulnerability advisories found.` |
| Pint | `composer lint` | ✔ `PASS … 58 files` |
| Larastan | `composer analyse` (nível 10, sem baseline) | ✔ `[OK] No errors` (2 execuções consecutivas + 1 cold-cache `clear-result-cache` — todas limpas; 1ª execução pós-`composer install` retornou 1 erro não-reproduzível, cache de resultado obsoleto do vendor pré-merge; registrado por honestidade) |
| Pest | `docker compose -p starter-wave2-async --env-file <primário> --profile test run --rm test` | ✔ **53 passed, 0 failed, 248 assertions** (base core RBAC/audit + 13 async + 2 negativos F5 novos) |
| Guardas F5 | `vendor/bin/pest --filter=InfraProdEnvGuardTest` | ✔ 5 passed, 16 assertions — incl. `smoke mail … sends nothing` (inbox Mailpit antes==depois) e `fail job … dispatches nothing` (exit 0, `failed_jobs` antes==depois) |
| Compose | `docker compose -p starter-wave2-async --env-file <sintético> --profile test --profile async config --quiet` | ✔ válido; `restart: unless-stopped` presente em worker+scheduler; `MAILPIT_API_URL` pinado no serviço test |
| Smoke async | `ENV_FILE=<primário> sh scripts/async_dev.sh up` → `smoke` | ✔ 7 serviços healthy (`--wait`); probe ok; entrega `[infra-smoke] <uuid>` → `recipient@example.test` no Mailpit; `infra:fail-job` despachou no local (guarda permite) com 3 eventos de falha sintética nos logs do worker |
| Cleanup | `sh scripts/async_dev.sh cleanup` (down -v com `-p starter-wave2-async`) | ✔ só volumes/redes `starter-wave2-async_*` removidos; `starter-kit-api_postgres-dev` e lane core intactos (listagem pós-cleanup) |
| CSD | `validate_context.py --strict` / `--strict --examples` / `unittest discover` / `doctor --scope project` | ✔ passed / passed (warning sem suite de exemplos) / `OK (7 tests)` / `CSD is healthy (1.2.1)` |

Isolamento: todos os comandos Docker com `-p starter-wave2-async` e `API_DEV_IMAGE=starter-wave2-async-api:local`; nenhuma operação em containers/volumes/imagens do primário ou da lane core; nenhuma porta de host nova (18080/18025 apenas 127.0.0.1, liberadas no cleanup).

## Review

- Request and spec aligned: yes
- Plan followed: yes (F1–F4 + round 2 F2–F6)
- Scope drift: none (arquivos async + FEAT-20; docs-only follow-up limitado a README + FEAT-20)
- Security impact addressed: yes — sem segredos/PII; guardas de ambiente agora nos 3 comandos dev; default after_commit false; stack default sem Redis/Mailpit; warnings de destruição de volume no compose
- Regression risk: low — core-owned zero-diff vs main (verificado: `git diff origin/main` vazio nos arquivos core; única sobreposição `routes/console.php` = 11 linhas consentidas)
- Core policy evidence: code-quality/testing/delivery; EXC-0001 (branch `feat/wave-2-async-recovery` → `main`, remote `origin`, registrado em execution.md)
- Quality gate result: **re-review oracle round 2 em `ccee7f42214b91ba394d6ddcae37a0c10d596f6c`: PASS WITH CONDITIONS** — F2–F6 aplicadas, zero-diff core independente, gates 53/248 auditáveis; condição restante: proveniência de `9c5f102` (explorer) BLOQUEIA merge; gate parent; **nenhuma aprovação GitHub é alegada**
- Security/privacy result: nenhum dado sensível em logs/testes/saídas; `.env` nunca lido (só `--env-file`)
- AI-use controls and human review: Oracle PASS WITH CONDITIONS em `9c61e18` + re-review `PASS WITH CONDITIONS` em `ccee7f4`; docs-only pós-re-review sem re-execução de gates PHP (produção intocada); nenhuma autoaprovação
- Subtask/wave integration evidence: FEAT-20-IMPL + FEAT-20-RECOVERY + round 2 concluídos; integração BLOQUEADA por proveniência de `9c5f102`; PR/merge/quadro = parent

## Findings

- Location: `routes/console.php` (dono núcleo) / defaults async
- Problem: F1 registro global + comando sem guarda (revisão independente, round 1)
- Evidence: `InfraProdEnvGuardTest` e diffs desta frente
- Required correction: aplicada (registro guardado local/testing; no-op SUCCESS fora)
- Location round 2: `compose.yaml` (name/healthcheck/restart), `InfraSmokeMailCommand`, `InfraFailJobCommand`, `scripts/async_dev.sh`, `config/mail.php`, `.env.example`
- Problem: F2 foot-gun do project name; F5 comandos smoke sem guarda; F3 restart; F6 URL implícita; F4 limitação de healthcheck não documentada (revisão oracle)
- Evidence: seção "Post-merge gates + correções F2–F6" acima
- Required correction: aplicada a todas (F2 warning+guarda, F5 guardas+negativos, F3 unless-stopped dev-only, F6 pin+docs, F4 comentários)

## CI round — PR #34 (2026-10-04, SHA `4888e89`)
PR aberto: `https://github.com/yanpenalva/starter-kit-api/pull/34` (base `main` `4f7c74d`, head `feat/wave-2-async-recovery` @ `4888e898e4a1f863b2ae952a45d088df1bc53598`; `Refs #20`, issue aberta de propósito). **MERGE NÃO EXECUTADO** — checks não todos em sucesso:

- `context` — **pass** (16s): `https://github.com/yanpenalva/starter-kit-api/actions/runs/37209801797/job/111458491834`
- `application` — **fail** (57s): `https://github.com/yanpenalva/starter-kit-api/actions/runs/37209801797/job/111458491778`
- `GitGuardian Security Checks` (app externo, sem run do Actions) — **fail**: `https://dashboard.gitguardian.com`

Diagnóstico `application` (9 falhas, TODAS nos 5 testes de integração async desta frente; nenhuma suíte core afetada): `RedisException`/`ConnectionException` — o workflow `.github/workflows/quality.yml` (dono: core, não alterado nesta lane) provisiona somente o serviço `postgres-test`; não há serviços `redis`/`mailpit` no runner, e o ambiente do job não define `REDIS_HOST`/`MAILPIT_API_URL`. Falhas: AfterCommitDispatchTest ×3, InfraMailDeliveryTest ×2 (Mailpit inalcançável), InfraProdEnvGuardTest ×2 (checagem de chaves/URL no processo-pai), InfraQueueFailureTest ×1, InfraSchedulerTest ×1. Localmente os mesmos testes passam (53/248) porque a stack `--profile test` do compose provê redis-test/mailpit-test. **Correção exigida = mudança no workflow CI (escopo config/core, fora da autorização desta lane)** — decidir com parent: (a) adicionar serviços redis/mailpit efêmeros ao job `application`, ou (b) grupo/exclusão de testes async no CI. Nenhum gate inventado; nenhum arquivo alterado para contornar.

Diagnóstico `GitGuardian`: 1 finding, detalhes somente no dashboard (sem acesso desta sessão). Scan local gitleaks (imagem oficial) sobre o mesmo range `4f7c74d..4888e89`: **0 leaks**; todo o conteúdo é sintético (`.test`, UUIDs, sem credenciais). Requer inspeção parent no dashboard GG para classificar (provável falso positivo — ex.: UUIDs de evidência em docs).

Proteções: `main` sem rulesets e sem branch protection (API 404) — nenhum required check no GitHub; condição de merge do parent (checks todos verdes no SHA exato) aplicada mesmo assim e **não satisfeita**. Base `main` inalterada durante a rodada.

## CI fix round — workflow services (2026-10-04, escopo autorizado pelo parent)

- Change: `.github/workflows/quality.yml` apenas — serviços efêmeros `redis-test`/`mailpit-test` (pins digest idênticos ao compose), env da fatia `test` (`QUEUE_AFTER_COMMIT/REDIS_HOST/REDIS_PORT/REDIS_QUEUE/MAIL_HOST/MAIL_PORT/MAILPIT_API_URL`) e extensão `redis` no setup-php. Contexto job/gates inalterados; zero alteração em código/testes (53/53 rodam).
- Pré-validação local (simulação fiel do runner): containers efêmeros com os mesmos pins em rede host + env exato do job → Pest **53 passed, 0 failed, 248 assertions**; YAML parse OK.
- Run remoto do SHA final: registrado no retorno da tarefa e visível no PR #34 (checks do último commit). GitGuardian segue em investigação explorer independente (fora deste escopo).

## Encerramento e estado do gate onda 2 (2026-10-04, pós-merge)

- Fatia FEAT-20 integrada: `main` @ `44aa89a29dbda6a16164014c914cc3b411324186` (PR #34); CI `main` no merge **success** — context https://github.com/yanpenalva/starter-kit-api/actions/runs/37211538628/job/111463556099 · application https://github.com/yanpenalva/starter-kit-api/actions/runs/37211538628/job/111463556181 (run `37211538628`); 53 testes / 248 assertions sem exclusões. Merge sob autorização excepcional do owner com GitGuardian `Triggered` (incidente 37826025) — [approval.md](approval.md); nenhuma aprovação GitHub alegada; EXC-0001 **não** estendida.
- Re-review oracle: **PASS em `08009f7`** (todos os commits/conteúdo, incl. workflow CI).
- Work item: `completed`/`close` (esquema CSD). Issue #20 aberta — consumidores #14/#15 pendentes (futuro).
- **Gate onda 2: NÃO aceito ainda** — critério "seed idempotente no banco de teste" não demonstrado para o caminho **default** (`migrate:fresh --seed` 2×, issue #9): `main` @ `44aa89a` sem `DatabaseSeeder.php` (evidência explorer, confirmada nesta worktree). Correção pendente em lane separada; **onda 3 não liberada**. Critérios já provados da onda 2: base de dados/seeders do catálogo RBAC idempotentes (`RbacCatalogueTest::test_seeder_is_idempotent_and_matches_the_approved_catalogue`), bootstrap/RBAC com escrita de auditoria atômica e rollback (`PrivilegeFoundationTest`: bootstrap idempotente sem auditoria duplicada; falha de auditoria reverte mutação), infra async #20 e fatia #21 (correlação/redação) nos 53 testes de CI.

## Verdict

`COMPLETED — FATIA ENTREGUE E INTEGRADA` (work item fechado 2026-10-04). Histórico: round 1 oracle PASS WITH CONDITIONS em `9c61e18` (F2–F6); round 2 re-review PASS WITH CONDITIONS em `ccee7f4`; procedência de `9c5f102` resolvida pelo parent (sessão fixer antiga retomada `ses_efbddf033ffevWJI0m8ZqB6Z2U`, evidência explorer OpenCode log+DB; ocorrência preservada nos registros); re-review oracle **PASS em `08009f7`**; merge da PR #34 em `44aa89a` sob autorização excepcional do owner com GitGuardian `Triggered` (incidente 37826025, [approval.md](approval.md)). CI `main` success no merge (run `37211538628`). Issue [#20](https://github.com/yanpenalva/starter-kit-api/issues/20) permanece aberta (consumidores #14/#15). Gate onda 2 segue **pendente** do seed default idempotente (lane separada); onda 3 não liberada. **Nenhuma aprovação GitHub foi ou é alegada.**
