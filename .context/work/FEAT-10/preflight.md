# Preflight — FEAT-10

**Revisão independente Oracle (ses_f00b14f02ffecUjnB5Iz5v7Epb): PASS** para a primeira fatia (2026-10-03), com decisões (engineering rulings, sem nova escolha humana):

1. **trustedproxy: default VAZIO** — não configurar ranges RFC1918/localhost; proxy explícito só se/quando necessário.
2. **Fixture CSRF somente em servidor de teste isolado** (env dedicado, flag própria); superfície de rotas de produção esperada: `GET /api/v1`, `GET /up`, `GET /sanctum/csrf-cookie` — nenhum endpoint extra.
3. CSRF 419 real: servidor HTTP não-testing, cookie jar, PG de teste isolado; bypass de `app.env=testing` não é prova.
4. Instalação `laravel/sanctum:^4.3.3` somente após preflight, no container PHP 8.5 (composer.json/lock, dono único).

Rollback: remoção de configs/rotas/testes novos + Sanctum do composer.json/lock se preciso. Gates: Pint/Larastan 10, Pest/PG isolado, smoke HTTP real, gates CSD. Fase avança para execute.
