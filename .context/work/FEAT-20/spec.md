# Spec — FEAT-20 (API #20)

## Objetivo

Integrar de verdade Redis (filas) e Mailpit (SMTP local) no starter-kit-api, com worker, scheduler de desenvolvimento, despacho `afterCommit`, retry/backoff determinístico e falhas observáveis — isolando dev/teste sem tocar identidade/RBAC/auditoria nem o checkout primário. **Pós-revisão: defaults seguros fora do async (F2/F3) e guardas de ambiente (F1) são parte da especificação.**

## Escopo (dono: frente async/operação)

1. **Docker/Compose**
   - `Dockerfile`: phpredis pin `6.3.0`; sem Predis (nenhuma dependência Composer nova).
   - `compose.yaml`: `-p starter-wave2-async` + `API_DEV_IMAGE` parametrizável (default do core inalterado); `worker`/`scheduler` (perfil async, dev, limitados, healthchecks reais); `redis-test`/`mailpit-test` na rede `testing` interna sem portas públicas; anchor env SEM dependência Redis/Mailpit (`MAIL_MAILER: log`, `QUEUE_CONNECTION: sync`).
2. **Config**
   - `config/queue.php`: `after_commit` da conexão redis = `env('QUEUE_AFTER_COMMIT', false)` — default global FALSE; `true` explícito somente na fatia async.
   - `config/mail.php`: `infra_smoke.recipient` (`.test` apenas) + `mailpit_api_url`.
   - `.env.example`: somente chaves aprovadas (`QUEUE_AFTER_COMMIT=false`, `REDIS_QUEUE=default`, `MAIL_MAILER=log`).
   - `routes/console.php`: UMA linha guardada (`local/testing`) com `Schedule::command('infra:probe')->everyMinute()` — consentimento explícito do parent; core writer não edita o arquivo.
3. **Banco** — migration reservada `2026_10_03_000004_create_failed_jobs_table.php` (schema padrão), sem seeds.
4. **Comandos dev** — `infra:probe` (guarda de ambiente: SUCCESS sem Redis fora de local/testing), `infra:smoke-mail`, `infra:fail-job`; mailable/job sintéticos (`.test`, sem PII).
5. **Testes reais** (projeto `starter-wave2-async`, PG tmpfs `starter_api_test`): afterCommit rollback 0 / commit 1 (fila redis REAL, sem fakes); entrega real worker→Mailpit (metadados); retry/backoff→`failed_jobs`; scheduler com probe; **3 guardas negativas de prod simulado**. Base 17 permanece verde.

## Fora de escopo

Horizon/Telescope/Pulse; pacotes novos; notificações #14/#15; endpoints admin; deploy/produção; provedor de segredos; cron de produção; composer/User/bootstrap/providers/logging/migrations 000002/000003; checkout primário (`.env` só via `--env-file` runtime).

## Aceite

- [x] Build phpredis 6.3.0 provado na imagem PHP 8.5.
- [x] Pest: base 17 + 13 async (3 negativas) = 30 passed.
- [x] Guardas F1/F2/F3 comprovados (prod simulado, default stack sem Redis/Mailpit).
- [x] CSD strict + gates locais; revisão independente pendente (não bloqueada por implementador).
