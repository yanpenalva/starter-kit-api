# Discovery — FEAT-20 (API #20, onda 2)

Data original: 2026-10-03 · Base: `065ff90` (merge PR #32, wave 1 gate PASSED) · Branch original `feat/wave-2-async` → alvo `main` (origin), EXC-0001. **Recuperação (2026-10-03): worktree temporário perdido sem commit de fonte; descoberta preservada do contexto retido — fatos da base `065ff90` permanecem válidos para o novo root persistente (mesma base).**

## Roadmap (fonte: `.context/project/roadmap.md`)

- Onda 2: "Filas e email local | API #20 | L | Após #8/#9/#10: Redis e Mailpit realmente integrados, worker/scheduler, afterCommit, retry/backoff, falhas observáveis e isolamento dev/test."
- Pré-requisitos #8/#9/#10 concluídos na wave 1 (PR #32 integrado; 17 testes API + 5 SPA no gate).
- Frente async é dona de "Compose, Dockerfiles, filas, mail e observabilidade"; composer.json/lock, User, migrations numeradas, rotas e catálogo de permissões têm dono único (integrador).

## Estado atual verificado (base `065ff90`)

| Fato | Evidência |
|---|---|
| `QUEUE_CONNECTION=sync` dev e test; `CACHE_STORE=file`; `SESSION_DRIVER=file`; test usa `array` | `compose.yaml`, `.env.example` |
| Redis e Mailpit existem **apenas** como `profiles: [async]`, sem integração (`MAIL_MAILER=log`) | `compose.yaml`, `.env.example` |
| Mailpit dev publica só `127.0.0.1:${MAILPIT_PORT:-8025}:8025`; redis sem porta pública | `compose.yaml` |
| Rede `testing` é `internal: true`; `postgres-test` tmpfs; projeto Compose `starter-kit-api` (sem `-p` no arquivo) | `compose.yaml` |
| Conexão `redis` em `config/queue.php` com `after_commit => false`; failed driver `database-uuids` → `failed_jobs` | `config/queue.php` |
| `config/mail.php` tem mailer `smtp` env-driven | `config/mail.php` |
| Única migration: `000001_users`; slot **000004** (`failed_jobs`) livre; 000002/000003 de outras frentes | `database/migrations/` |
| composer.json sem predis/phpredis (sem conflito); php `~8.5.0`, laravel `^13.17` | `composer.json` |
| Dockerfile php:8.5-fpm-bookworm por digest, **sem** extensão redis | `Dockerfile` |
| `.env` gerado por script dev-only; nunca lido por agentes | `scripts/setup_local_env.py` |

## Preflight executado (2026-10-03, somente leitura)

1. **Git**: base `065ff90` = `origin/main` do PR #32. ✔
2. **Docker**: 29.8.2 / Compose v5.6.0. ✔
3. **Colisão CONFIRMADA**: checkout primário `starter-kit-api` com containers ativos (incl. redis/mailpit) + imagem compartilhada + volume — `-p starter-wave2-async` + imagem própria obrigatórios.
4. **phpredis**: PECL estável **6.3.0** (REST pecl.php.net via `php:8.5-cli`). Pin proposto; compilação provada depois no build (sem Predis → zero mudança no composer.lock).
5. **Portas/dados**: bindings dev `127.0.0.1` apenas; PG/Redis sem portas públicas; volume `postgres-dev` do primário intocado.

## Lacunas reais (fechadas pela implementação)

- Nenhuma migration `failed_jobs`; `after_commit` falso; nenhum worker/scheduler; nenhum smoke; nenhum teste de entrega real; `.env.example` sem variáveis de fila redis.
