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

## Additive evidence — 2026-10-04 (wave 3, política pt-BR em respostas humanas da API; issue API #10)

Escopo: localização pt-BR de todo texto human-facing da API + correção de regressões reais encontradas no estado adotado; vinculada à issue #10 (conventions: human-facing pt-BR, machine interfaces intocadas). Producer self-check apenas; revisão independente pelo parent depois.

### O que ficou pt-BR (cobertura)

- Corpos de erro JSON da API, todos com shape exato `{"message": "..."}`: 401 (`Não autenticado.` / `Credenciais inválidas.`), 403 (`Acesso não autorizado.` / `Esta conta foi desativada.`), 404 (`Recurso não encontrado.`), 405 (`Método não permitido.`), 419 (`Token CSRF inválido ou expirado.`), 429 (`Muitas tentativas. Por favor, tente novamente mais tarde.`), 500 (`Erro interno do servidor.`).
- 422 de validação: mensagens do `lang/pt_BR/validation.php` (ex.: `O campo email deve ser um endereço de e-mail válido.`, `O campo password é obrigatório.`), shape `{message, errors}` preservado.
- Regra `Cpf`: 4 mensagens pt-BR (string/exatamente 11 dígitos/dígitos repetidos/dígitos verificadores), cobertas por teste.
- Saída CLI voltada a operador: `app:promote-admin`, `infra:probe`, `infra:fail-job`, `infra:smoke-mail` (mensagens de runtime, descrições de signature) + mensagens de domínio de `BootstrapAdmin`.
- `config/app.php`: locale/fallback/faker `pt_BR` (estado adotado mantido).

### Dono único das mensagens

`app/Support/ApiMessage.php` (novo): constantes nomeadas para todas as mensagens de API + `forHttpException()` com allowlist de mensagens próprias — só textos possuídos pela aplicação passam verbatim; qualquer texto upstream do framework é substituído pela mensagem genérica pt-BR do status. Sem literais duplicados: controller/middlewares/bootstrap consomem as constantes.

### Correções de segurança/consistência no tratamento de exceções (bootstrap/app.php)

1. **Regressão corrigida (prova: teste quebrando 422→500 antes do fix):** o closure `Throwable` adotado interceptava `ValidationException` (registrado por último ⇒ avaliado primeiro em `renderViaCallbacks`) e devolvia 500 genérico. Agora `ValidationException` e `HttpResponseException` caem para o rendering do framework.
2. **Vazamento de texto inglês corrigido:** `prepareException()` (Handler.php:721) converte `AuthorizationException`→`AccessDeniedHttpException("This action is unauthorized.")` e `TokenMismatchException`→`HttpException(419, "CSRF token mismatch.")` ANTES dos render callbacks — os closures adotados por tipo eram mortos para esses caminhos e o default render vazaria o texto inglês. O catch-all atual substitui defaults upstream pela mensagem pt-BR do status e preserva headers de throttle (429 `Retry-After`/`X-RateLimit-*` via `getHeaders()`).
3. **Fail-closed preservado:** 500 genérico sem trace/paths/detalhes (testes asseveram corpo exato e ausência de `secret internal detail`, `/var/www`, `trace`); 404 genérico ignora detalhes de model-binding; anti-enumeration de login intocado (401 genérico com senha errada, 403 explícito só com credenciais corretas); `APP_DEBUG` desliga a substituição (debug → null ⇒ comportamento de desenvolvimento preservado).
4. CSD: closures com ≤3 returns, sem else, nesting ≤1; Pint PASS; Larastan 10 sem baseline.

### Comandos executados (2026-10-04, projeto compose `starter-wave3-ptbr`, imagem `starter-wave3-ptbr-api:local`, PHP 8.5.11, PG18/Redis/Mailpit com digests pinned)

| Gate | Resultado |
| --- | --- |
| `composer validate --strict` / `check-platform-reqs` / `audit` | válidos / success / sem advisories, exit 0 |
| `composer lint` | Pint PASS, 79 arquivos, exit 0 |
| `composer analyse` | Larastan nível 10, 52 arquivos, no errors, exit 0 |
| `docker compose --profile test run --rm test` | **93 passed, 601 assertions, 0 warnings**, ~29.2s, exit 0 |
| `validate_context.py --strict` / `--strict --examples` / unittest / doctor | exit 0 / exit 0 / 7 OK / CSD healthy 1.2.1 |

Testes atualizados/estendidos (arquivos existentes, sem novas rotas/endpoints): `LoginTest` (422 pt-BR com mensagens exatas, 419/429 corpo exato + `Retry-After`, bearer 401 pt-BR), `LogoutTest`/`ProfileTest` (401/403 pt-BR corpo exato), `HttpAuthSmokeTest` (403 desativado pt-BR em HTTP real), `ScaffoldTest` (404 corpo exato pt-BR, novo 405, novo teste de render de `AuthorizationException`→403 genérico, novo 500 sem leaks), `UserSchemaTest` (mensagens pt-BR da regra CPF), `InfraProdEnvGuardTest` (assertions dos guards CLI em pt-BR).

### Cobertura restante — exceções explícitas

Contrato OpenAPI/snapshots/exemplos intencionalmente intocados (lane do dono do contrato fará o sync pt-BR); strings internas de vendor/SDK nunca chegam ao corpo (substituídas pelo allowlist); interfaces de máquina inalteradas (status HTTP, chaves JSON, rotas, nomes de cookie, correlation IDs); `routes/console.php` mantém o comando scaffold `inspire` em inglês (arquivo com nota de consentimento de coordenação de lane anterior); mensagens de invariantes internas fail-closed (`CorrelateRequest`, `PrivilegeTransaction`, `Audit`) permanecem en-US — nunca expostas em respostas, apenas em logs de desenvolvedor.

## Delta pós-integração do PR #39 (2026-10-04/05) — merge 21fc960 na branch; producer self-check

- **PASS histórico:** SHA `9334e2d602256a40ada9efd6a2501b1602f53e0f` (PR #40) revisado independentemente via revisão GLM nativa na sessão `ses_ef6c9e6b8ffev3xi7l4q6S65yV`. **O delta atual (merge `51812ff` + commit do delta) AGUARDA nova revisão independente — nenhum PASS é alegado para o novo SHA.**
- Merge ordinário de `origin/main` `21fc960` (PR #39, lane contrato: Scramble/OpenAPI overlay EN + jobs CI de contrato) — zero conflitos; `touch .env` (FEAT-24) e jobs de contrato coexistem em `quality.yml`.
- Fail-closed MINOR corrigido: `ApiMessage::forStatus()` não recebe mais `$fallback`; status não mapeados (400/409/413/503) retornam a constante segura `REQUEST_FAILED` (`Não foi possível concluir a requisição.`) — texto upstream nunca vira corpo. Headers/status preservados.
- Testes adversariais novos em `ScaffoldTest`: sentinelas hostis (PII/inglês/segredo) em 400/409/413/503 nunca aparecem no corpo; headers de máquina preservados; `AuthorizationException::asNotFound()`/`withStatus()` mapeiam fail-closed (404 genérico/423 genérico).
- CSD: `Cpf::validate()` 4 returns → 2 (extração coesa `validateNormalizedDigits`, sem wrapper fake); `PromoteAdmin` sem else (mensagem única escolhida por already_admin); comentários narrativos removidos dos arquivos tocados e racionais movidos para `.context/project/security.md` (seção "Error-body negative space").
- `lang/pt_BR/validation.php`: catálogo completo — as 8 chaves Laravel 13 faltantes eram `any_of`, `array_keys`, `base64`, `doesnt_contain`, `encoding`, `in_array_keys`, `prohibited_if_accepted`, `prohibited_if_declined` (comparação exata com `vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php` instalado); placeholders preservados; testes de catálogo + anti-chave-crua adicionados.
- Gates (projeto compose `wauth-auth`, imagem `wauth-auth-api:local`): validate/check-platform/audit exit 0; Pint PASS 86 arquivos; Larastan 10 sem erros; Pest completo **105 passed / 901 assertions / 0 warnings** (30.6s); unittest 113 OK; CSD strict/--examples/doctor 1.2.1 exit 0.
- **Coordenação de contrato (pendência declarada, NENHUM gate manipulado):** export determinístico 2x byte-idêntico na branch combinada diverge do snapshot `contracts/openapi.json` da main em exatamente 2 exemplos inferidos do runtime — login 401 `Invalid credentials.` → `Credenciais inválidas.` e my-profile 403 `This account has been deactivated.` → `Esta conta foi desativada.` — portanto o passo "Committed snapshot freshness gate" (cmp) falha até o re-export pelo dono da lane contrato. `contract_compat` contra o baseline imutável `21fc960` via oasdiff 1.33.0 (hash verificado): **APROVADO, zero breaking changes**. Adicionalmente, os exemplos 419/429 do overlay (`CSRF token mismatch.`/`Too many requests.`) continuam EN e hoje divergem dos corpos pt-BR — adaptação pertence ao mesmo passe do dono do contrato. FEAT-10 permanece em review, não Done.
