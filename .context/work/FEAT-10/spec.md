# Specification — FEAT-10 (primeira fatia, wave 1)

## Scope

Contratos de erro JSON (422/401/403/404/409/419/429 conforme api.md, sem stack trace), CORS credenciado com allowlist explícita de dev (`http://localhost:5173` SPA, `http://localhost:8080` API; produção TBD), baseline de cookies seguros local, rota `GET sanctum/csrf-cookie` compatível com Sanctum 4.3.3 / Laravel 13.34 / PHP 8.5, `statefulApi()` + guard `web` para a SPA somente (sem PAT, sem trait/migration de token, sem login), throttle genérico e `trustedproxy` com defaults seguros explícitos (sem wildcard de host/origin).

Rationale (review Oracle 2026-10-03): `localhost:8080` é same-origin (a própria API atende nessa origem) — não precisa de entrada CORS nem preflight; a allowlist credenciada é só a SPA em `5173`. Nenhuma ampliação de CORS.

## Regras anti-falsificação de teste

- Smoke HTTP real em servidor não-testing com cookie jar: 419 em CSRF ausente/inválido usando recursos de PG de teste isolado e env dedicado — o rebind de `app.env=test` serve apenas para simulação, não como prova de navegador.
- Nenhum endpoint público de negócio criado só para smoke; fixture de rota somente-teste isolada é proposta aceitável e será submetida ao revisor.
- Bypass de testes NÃO é usado para fabricar teste de 419; browser E2E real fica na wave 3.
- Sem auth/login/cadastro/CRUD (waves donas #13/#14+); sem URLs/segredos de produção.

## Out of scope

Middlewares de produção (#25/#26), autenticação (#13), rate limiting por endpoint de identidade (#13+), produção TBD.

## Acceptance

- Erros JSON sem trace com status/shape por api.md; allowlist CORS exata (origem não listada rejeitada); `/sanctum/csrf-cookie` responde e seta cookie; `/up` e rota de disponibilidade preservadas.
- Instalação do Sanctum registrada no composer.lock via container PHP 8.5 (só após preflight).
- Pint/Larastan 10/strict types; gates CSD verdes; issue #10 aberta (produção/RBAC restam).

## Entrega

Branch `feat/wave-1-api-foundations`, alvo `main`, remote `origin`; [EXC-0001](../../exceptions/EXC-0001.json).
