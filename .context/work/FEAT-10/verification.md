# Verification — FEAT-10

## Confirmação final de isolamento pelo orquestrador

Serviço test recebeu tmpfs em cache, sessions e testing. `docker compose --profile test run --rm test` passou: 17 testes/74 assertions. Novo container, leitura filtrada de `/proc/self/mountinfo`, confirmou os três mounts como tmpfs (rw,nosuid,nodev,noexec). Dev volumes intactos; arquivo versionado cache/data/.gitignore removido pela limpeza anterior foi restaurado ao conteúdo original, sem reset Git. Oracle confirmou PASS deste delta.

## Evidence (executed 2026-10-03, all real)

- `laravel/sanctum ^4.3.3` installed via composer **inside the PHP 8.5 container** (composer.json + lock, single owner); plus required dev deps surfaced by the suite: `mockery/mockery`, `fakerphp/faker`, and `Database\Factories` autoload mapping.
- `bootstrap/app.php`: `statefulApi()`; explicit api group with `ThrottleRequests:api` (limiter 60/min per user/IP in AppServiceProvider) + SubstituteBindings; **trusted proxies default EMPTY** (ruling 1) — no RFC1918/localhost ranges; `trustHosts(subdomains:false)` preserved; JSON errors render for `api/*`.
- `config/cors.php`: exact credentialed allowlist (`http://localhost:5173`); `config/sanctum.php`: stateful 5173, guard `web` only, no PAT; `routes/web.php` registered in bootstrap (was missing — Sanctum auto-registers `GET /sanctum/csrf-cookie`).
- Real non-testing HTTP smoke (`tests/Feature/HttpBoundarySmokeTest.php`, APP_ENV=local server, cookie jar, isolated test PG): CSRF cookie 204 + encrypted XSRF-TOKEN; POST without token → **419**; invalid token + foreign Origin → **419**; valid X-XSRF-TOKEN (percent-decoded as a browser exposes) → **204**; fixture route absent without flag file; `GET /api/v1` + `GET /up` preserved (route inventory test updated: `api/v1, sanctum/csrf-cookie, up`); CORS exact-allowlist honored (foreign origin gets no matching ACAO — single-origin echo noted); 62 GETs → 429 present.
- No `APP_ENV=testing` bypass used as proof; no business endpoints shipped; production surface unchanged.

## Commands

| Command | Result |
| --- | --- |
| `docker compose run --rm --no-deps api composer require laravel/sanctum:^4.3.3` | installed, lock updated |
| `docker compose run --rm --no-deps api composer validate --strict` | valid |
| `composer check-platform-reqs` / `composer audit` | success / no advisories |
| `docker compose --profile test run --rm test` | 15 passed / 63 assertions |
| `composer lint` / `composer analyse` | PASS / No errors |

## Limits

- No auth/login/register/CRUD (own waves); production URLs/secrets TBD; browser E2E wave 3. Issue #10 stays open (progressive).

## Re-review delta (2026-10-03) — Oracle conditional PASS dispositions

- F1 (FEAT-10): fixture agora exige ambiente testing|local E flag-file; regressão real com APP_ENV=production + flag presente → 404 (POST /testing/csrf-echo) e /up 200; smoke local intacto. Sem deployment real — apenas config simulada de produção sobre PG de teste e servidor efêmero com guards existentes.
- F2 (FEAT-9): normalização digits-only antes do state (`withCpf('529.982.247-25')` → `52998224725`); zeros à esquerda preservados; geração opcional/null mantida; regressão adicionada.
- F3 (FEAT-9): `cpf` em `$hidden`; serialização (toArray + jsonSerialize) sem password/remember_token/cpf.
- F4 (FEAT-10, opcional): rationale CORS no spec; nenhuma mudança de comportamento.
- Contadores atualizados após re-run (ver comandos abaixo); nenhum confirmatory PASS afirmado antes do re-check do revisor.

## Re-run delta — fixes F1–F4 implementadas (2026-10-03)

Comandos re-executados após as correções: `composer validate --strict` (válido); `composer check-platform-reqs` (success, PHP 8.5.11); `composer audit` (sem advisories); `composer lint` (PASS, 28 arquivos); `composer analyse` (Larastan 10, no errors); `docker compose --profile test run --rm test` → **17 passed, 74 assertions** (inclui regressões novas: fixture 404 em APP_ENV=production com flag presente, normalização `529.982.247-25`→`52998224725`, serialização sem cpf); smoke HTTP real (csrf-cookie/419/204, CORS, throttle 429) dentro da suite; CSD strict `--examples` exit 0; unittest 7 OK; doctor healthy 1.2.1; `git diff --check` clean. Conflito de estabilidade do throttle resolvido com cache `file` + limpeza guardada de `storage/framework/cache/data` antes da janela (servers efêmeros `php -S` de processo único, sem orfãos).
