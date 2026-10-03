# Plan — FEAT-10

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | API integrator (único escritor) | none | Middlewares/CORS/csrf-cookie/throttle/trustedproxy + testes HTTP reais 419 no PG isolado (executa após S1 do FEAT-9, na mesma branch) | 1 | security,testing |
| S2 | independent oracle reviewer | S1 | Preflight/verificação antes da implementação; fixture de rota somente-teste submetida ao revisor | 2 | security |

## Preflight proposal (concreto, para o revisor; nada executado ainda)

- Arquivos: `bootstrap/app.php` (middleware/statefulApi/errors), `config/cors.php` (allowlist explícita), `config/sanctum.php` (pós-install), `config/trustedproxy.php` ou equivalente em bootstrap, `routes/api.php`/`routes/web.php` (adicionar GET `sanctum/csrf-cookie`; inventário de rotas atualizado preservando `/up` e rota de disponibilidade), `tests/Feature/HttpBoundaryTest.php` (smoke real 419/cookie jar).
- Comandos: `docker compose run --rm --no-deps api composer require laravel/sanctum:^4.3.3` (somente pós-preflight, no container PHP 8.5); perfil `test` do compose para Pest; gates CSD padrão.
- Rollback: remoção dos arquivos/configs novos; Sanctum removido do composer.json/lock se necessário; branch descartável.
- Aceite: ver spec.md. Unknown material para o oracle: defaults exatos de `trustedproxy` (ranges privados locais vs. IPs explícitos do compose) — proposta:_ranges privados RFC 1918 + localhost, decisão reversível; forma da fixture de rota somente-teste.

## Correção de preflight (Oracle PASS, 2026-10-03 — engineering rulings)

1. **trustedproxy: default VAZIO** — nenhuma range RFC1918/localhost configurada; proxy explícito só quando necessário (não nesta fatia).
2. **Fixture CSRF somente em servidor de teste isolado** (env dedicado `HTTP_SMOKE_FIXTURE`, nunca carregada em produção); superfície de rotas de produção: `GET /api/v1`, `GET /up`, `GET /sanctum/csrf-cookie` apenas.
3. CSRF 419 real em servidor HTTP não-testing com cookie jar e PG de teste isolado; rebind `app.env=testing` não serve como prova.
