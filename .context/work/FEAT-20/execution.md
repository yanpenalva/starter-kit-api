# Execução — FEAT-20

Fase: `verify` (após execução completa e correções F1–F4) · Branch de entrega: `feat/wave-2-async-recovery` (persistent recovery root `/home/hiyan/Development/github/personal/starter-kit-api-wave2-async-worktree`) → alvo `main` (remote `origin`), EXC-0001. Branch anterior `feat/wave-2-async` nunca recebeu commit de fonte (apontava para `065ff90`); seu worktree temporário em `/tmp/opencode` foi perdido.

## Registro de recuperação (2026-10-03)

- O worktree temporário original (`/tmp/opencode/starter-kit-api-wave2-async`) desapareceu do filesystem, confirmado (`No such file or directory`), junto com vendor/instalações e artefatos não commitados.
- NENHUM commit de fonte único existia na branch antiga — `feat/wave-2-async` continha apenas a referência de criação `065ff90`; portanto **nada foi recuperado do Git**.
- A versão FINAL pós-correções (F1–F4) foi **reconstruída a partir do contexto retido do implementador** (especificações, fontes finais e comandos da conversa), no novo root persistente. Nenhum comando histórico foi reexecutado às cegas; o usuário autorizou continuar o escopo FEAT-20 aprovado.
- Nenhuma edição no primário/core; sem alterações de roles/catálogo/auth; User/Core PR #33 (head `011d72d`) é lane separada do parent — não bloqueia esta recuperação.

## Veredito de preflight real (pré-código, 2026-10-03)

`READY` para execução, sob as condições obrigatórias abaixo — gate real = Oracle PASS independente pré-código (ver preflight.md, adendo F4). Nenhum passo de build/teste havia sido executado antes dessa data no worktree original.

## Condições de execução (vinculantes)

1. Todo comando Docker usa `-p starter-wave2-async`; imagem da API desta frente: `starter-wave2-async-api:local` via `API_DEV_IMAGE`. Nunca tocar imagens/volumes/containers do projeto primário (`starter-kit-api`) nem de outras lanes (`starter-wave2-core-*`).
2. `.env` primário usado SOMENTE como `--env-file` em runtime de containers; nunca lido/copiado/impresso no contexto. Portas dev desta frente: `HTTP_PORT=18080`, `MAILPIT_PORT=18025` (127.0.0.1).
3. phpredis pin `6.3.0` (PECL estável verificado); prova real = build da imagem com `php -m`/`phpversion('redis')` dentro dela. Falha de compilação = PARAR; sem troca de cliente (nada de Predis).
4. `composer install` NÃO altera `composer.json`/`composer.lock` (dono: núcleo).
5. Testes: worker real efêmero `queue:work redis --queue=testqueue --stop-when-empty --max-jobs` — só redis-test/mailpit-test; dados sintéticos (`recipient@example.test`, assunto `[infra-smoke] <uuid>`); Mailpit via HTTP API com metadados mínimos; limpeza da caixa ao fim.
6. Sem Horizon/Telescope/Pulse, sem pacote novo, sem API pública de #14/#15, sem arquivos do núcleo (User, bootstrap, providers, logging, migrations 000002/000003, composer) — exceto a ÚNICA linha guardada em `routes/console.php`, com consentimento explícito do parent para coordenação (core writer não edita esse arquivo).
7. Falha visível: testes falham com mensagem real; sem skip silencioso.
8. Git: local commit permitido como checkpoint após gates; push da branch de recuperação permitido sob EXC-0001; PR/merge/integração = parent apenas.

## Correções pós-revisão independente (F1–F4)

1. **F1 — guardas de ambiente**: `routes/console.php` registra `infra:probe` SOMENTE em `local/testing` (linha única guardada, consentida). `InfraProbeCommand::handle()` retorna SUCCESS sem tocar Redis fora de `local/testing`. Evidência: `InfraProdEnvGuardTest` — prod SIMULADO (container de teste; nenhum deploy real): `schedule:list` sem probe; invocação com Redis em porta fechada sai 0 sem conectar/criar chaves.
2. **F2 — after_commit default FALSE**: `config/queue.php` default `env('QUEUE_AFTER_COMMIT', false)`; `true` explícito somente na fatia async (worker/scheduler/test no compose). Evidência: subprocesso sem a variável mostra `after_commit … false`; com a variável, true.
3. **F3 — defaults seguros fora do async**: anchor Compose sem `REDIS_HOST/REDIS_PORT/REDIS_QUEUE/MAIL_HOST/MAIL_PORT`; `MAIL_MAILER: log`. Stack default sobe saudável, `/up` 200, `after_commit=false` — zero dependência Redis/Mailpit. `.env.example` com `MAIL_MAILER=log` (baseline) e `QUEUE_AFTER_COMMIT=false`; async sobrescreve explicitamente. Smoke dev despacha via container `worker` (caminho real de fila).
4. **F4 — provenância do preflight**: adendo em preflight.md registrando o Oracle PASS pré-código e corrigindo a autodeclaração anterior; sem aprovação retroativa.

## Evidência de execução

Registro histórico (worktree original, antes da perda): build phpredis 6.3.0/PHP 8.5.11 ✔; Pest 30 passed/115 assertions ✔; Pint 40 files ✔; Larastan 10 "No errors" ✔; audit limpo ✔; CSD strict/--examples/unittest/doctor ✔; smoke worker/scheduler com entrega sintética ✔; cleanup com primário intacto ✔.

Registro de RECUPERAÇÃO (novo root persistente): reexecução completa obrigatória pós-reconstrução — resultados em verification.md após os gates frescos. Evidência histórica NÃO conta como prova fresca da recuperação.

## Fresh recovery gates (root persistente, 2026-10-03)

| Gate | Comando | Resultado |
| --- | --- | --- |
| Build | `docker build -t starter-wave2-async-api:local .` + `phpversion('redis')` | ✔ `bool(true)`, `6.3.0`, PHP 8.5.11 |
| Vendor | `composer install` (imagem própria) | ✔ composer.json/lock intocados (git status: 0 mudanças) |
| Static | Pint `PASS 40 files` / Larastan `[OK] No errors` / validate `valid` / audit `No security vulnerability advisories` | ✔ |
| Pest real | `--profile test run --rm test` (PG tmpfs + redis-test + mailpit-test) | ✔ **30 passed, 0 failed, 115 assertions** |
| Smoke async | `--profile async up -d --wait` (portas 127.0.0.1:18080/18025) + `infra:probe`/`infra:smoke-mail` via worker | ✔ probe ok; entrega sintética `[infra-smoke] <uuid>` no Mailpit; scheduler healthy; cleanup `down -v` sem leftovers |
| CSD | strict / strict --examples / unittest / doctor | ✔ passed / passed / OK / "CSD is healthy (1.2.1)" |

Checkpoint Git: commit local na branch de recuperação (conventional commit, arquivos FEAT-20 apenas) + push da branch sob EXC-0001; PR/merge = parent. Observação fora desta lane: containers do projeto primário `starter-kit-api` não estavam presentes no daemon durante o cleanup (nenhum comando desta frente os tocou; todas as operações usaram `-p starter-wave2-async`).

## Integração origin/main (PR #33 core, `4f7c74d`) — combined snapshot (2026-10-03)

- Merge normal (sem rebase/force/reset) já registrado em `eef1206`; integridade verificada: TODOS os arquivos core-owned (composer.json/lock, User, bootstrap, providers, phpunit.xml, configs de núcleo, rotas api/web, seeders, Support/Rules/Http, migrations 000002/000003) com diff ZERO vs `origin/main`; única sobreposição = `routes/console.php` com a linha ÚNICA guardada consentida (11 linhas).
- Correções de revisão preservadas/aplicadas nesta árvore (F5): guardas de ambiente em `infra:smoke-mail`/`infra:fail-job` + 2 testes negativos de prod simulado (Mailpit inbox intocado; `failed_jobs` intocado); (F6) `MAILPIT_API_URL` pinado no serviço de teste; restart unless-stopped dev-only; warnings documentados dos healthchecks (probe ≠ consumo).
- Gates frescos no snapshot combinado: composer install (lock do main, spatie 8.3/5.1) sem mudança de lock ✔; build proof phpredis 6.3.0/PHP 8.5.11 ✔; Pint `PASS 58 files` ✔; Larastan 10 `[OK] No errors` ✔; validate/audit/check-platform ✔; Pest combinado **53 passed, 0 failed, 248 assertions** (contagem real; base core + 13 async, incl. 5 guardas negativas) ✔; CSD strict/--examples/unittest/doctor ✔; smoke async worker→Mailpit entrega sintética ✔; cleanup `down -v` sem leftovers.
- `.env` primário usado só como `--env-file` runtime; nenhum segredo lido/impresso.

## Round 2 de correções (F2–F6, 2026-10-04)

Após merge `eef1206` (origin/main `4f7c74d`, zero conflito, histórico preservado), correções da revisão independente oracle (ver `review.md`):

1. **F2 — foot-gun do `name:` global**: warning explícito no topo de `compose.yaml`: o nome fixo `starter-kit-api` colide entre checkouts (mesmo projeto Compose = containers/rede/volume `postgres-dev` compartilhados; `down -v` num checkout destrói dados do outro; NUNCA `down -v` na stack primária). Documentado que `COMPOSE_PROJECT_NAME` default ambiente NÃO elimina o risco (shell sem a variável cai no nome compartilhado). `scripts/async_dev.sh` mantém `-p starter-wave2-async` + portas isoladas e ganhou guarda que recusa `PROJECT=starter-kit-api`.
2. **F5 — guardas F1 estendidos**: `infra:smoke-mail` e `infra:fail-job` com a mesma guarda `local/testing` do `infra:probe` (no-op SUCCESS fora). Negativos novos em `InfraProdEnvGuardTest`: smoke-mail com SMTP+sync no prod simulado — inbox do Mailpit-test intocado (snapshot antes/depois); fail-job com sync — exit 0 + `failed_jobs` intocado.
3. **F3 — restart dev-only**: `worker`/`scheduler` com `restart: unless-stopped` (conveniência dev; serviços só existem no perfil `async`; supervisão de produção fora de escopo). Validado por `docker compose config`.
4. **F6 — `MAILPIT_API_URL` explícito**: pinado `http://mailpit-test:8025` no serviço `test` do compose; default documentado em `config/mail.php` (uso SOMENTE verificação de smoke/teste; nome de serviço da rede `testing`, não resolve no host) e `.env.example` (chave comentada).
5. **F4 — limitação do healthcheck documentada**: comentários em `worker`/`scheduler`: o probe prova só conectividade/escrita Redis, NÃO consumo de jobs; aceito dev-only, produção deve monitorar profundidade/lag de fila.

Gates frescos pós-correções: ver `verification.md` (seção 2026-10-04). **Nota de governança**: o commit `9c5f102` (contendo estas correções + um adendo anterior de execution.md) apareceu no reflog como commit não executado por este fixer; conteúdo conferido arquivo a arquivo contra as edições desta sessão (idêntico) e a alegação de zero-diff core re-verificada de forma independente (`git diff origin/main` nos arquivos core-owned: vazio; única sobreposição `routes/console.php` = 11 linhas consentidas). Anomalia reportada ao orquestrador para atribuição.

## Re-review oracle round 2 (2026-10-04, SHA `ccee7f4`)

- **PASS WITH CONDITIONS**: F2–F6 verificadas como aplicadas; zero-diff core vs `origin/main` confirmado de forma independente; gates técnicos auditáveis (Pest 53 passed/248 assertions; Pint 58 files; Larastan 10 limpo; CSD strict/--examples/unittest/doctor; smoke async com cleanup lane-scoped).
- **Nenhuma aprovação GitHub é alegada** (re-review por agente ≠ approving review); PR/merge/quadro = parent.
- **Merge BLOQUEADO** até esclarecimento da proveniência de `9c5f102` (investigação explorer em andamento) — condição única restante; work item permanece `active`.
- Follow-up docs-only pós-re-review (este registro): README.md (seção async — perfil opt-in com Redis/Mailpit/worker/scheduler dev, defaults `sync`/`log`, recomendação `scripts/async_dev.sh` + `-p` único por worktree, sem comando cru perigoso) + adendos FEAT-20. Nenhum arquivo de produção/config/testes alterado; gates PHP NÃO re-executados (produção intocada); CSD re-validado após as edições.

## Decisão parent — procedência resolvida e liberação condicional (2026-10-04)

- **Resolução**: explorer comprovou via lifecycle OpenCode (log + DB) que `9c5f102` foi commitado em **2026-10-04T14:16:28Z pela sessão fixer antiga retomada `ses_efbddf033ffevWJI0m8ZqB6Z2U`** — distinta da sessão fixer seguinte desta frente. Conteúdo = edições novas da sessão antiga + adendo no execution.md pela mesma sessão, todo em escopo FEAT-20. Nenhum escritor ativo/locks restantes.
- Ocorrência histórica e auditorias da seção anterior **preservadas** (não reescritas); bloqueio de governança **removido** pela decisão parent.
- **Liberação condicional**: PR/CI/merge condicional autorizados sob EXC-0001 + autorização de continuação do user/parent — condições: checks todos em sucesso no SHA exato do PR (context/application e qualquer required), nenhuma review requerida pendente, base `main` ainda `4f7c74d` (base revisada); base mudou → sem merge e retorno para review; merge normal SEM `--admin`/`--force`, sem apagar branch; sem tag/produção/secrets.
- Oracle: revisão de todos os commits/conteúdo **PASS em `ccee7f42214b91ba394d6ddcae37a0c10d596f6c`** (mantida; conteúdo inalterado desde então — docs FEAT-20 somente). **Nenhuma aprovação GitHub é alegada.**

## Execução PR/CI (2026-10-04)

- PR **#34** aberto (base `main` `4f7c74d`, head `4888e89`, EXC-0001; `Refs #20` sem fechar a issue — aceite 1 depende de consumidores #14/#15): `https://github.com/yanpenalva/starter-kit-api/pull/34`.
- CI no SHA exato: `context` **pass**; `application` **fail** (9 falhas — testes async exigem redis/mailpit ausentes no workflow core; detalhe em verification.md); `GitGuardian` **fail** (1 finding, dashboard-only; gitleaks local 0 leaks no range). **Merge NÃO executado** (condição parent não satisfeita); base `main` inalterada; sem `--admin`/`--force`; branch não apagada.
- Desbloqueio exigido (decisão parent/core, fora do escopo desta lane): workflow CI com redis/mailpit efêmeros OU segregação dos testes async no CI + classificação do finding GG.
