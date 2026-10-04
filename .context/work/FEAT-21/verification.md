# Verification — FEAT-21 (primeira fatia, wave 2)

## Evidence (executed 2026-10-03, real)

- `App\Support\Correlation` + `App\Http\Middleware\CorrelateRequest` (global): X-Correlation-Id aceito somente como UUID válido, caso contrário gerado (anti log-forgery); header ecoado na resposta; contexto de log estruturado.
- Redação: envelope de auditoria testado com password/cpf/email/token/full_payload oferecidos — nenhum sobrevive (asserts no JSON); sem IP cru/PII.
- Zero ferramentas novas; canal stderr do compose preservado.

## Commands

| Command | Result |
| --- | --- |
| `composer test` | 33 passed / 163 assertions (CorrelationRedactionTest) |
| `composer analyse` / `lint` | No errors / PASS |

## Limits

Rotação/produção NOT FOUND (Marco B); issue #21 aberta.

## Re-run delta — R1 (2026-10-03)

Middleware com gate de ambiente; testes dev-preserved/prod-ignored/unique incluídos na suíte (36 passed total).

## Evidência corrigida — R1 (2026-10-03, append-only)

- Reivindicação anterior de "prod ignores + unique" na suíte estava SEM teste real no arquivo (apontado pelo grep do pai); entrada anterior é corrigida por esta.
- Testes reais adicionados: `test_local_or_testing_preserves_a_valid_client_header_as_log_context` (tests/Feature/CorrelationRedactionTest.php:58) e `test_production_ignores_client_correlation_headers` (:76) — header UUID estrangeiro em APP_ENV simulado `production` (instância 'env' trocada com restore em finally) → novo UUID diferente, único em 2 requests, válido; local/testing preserva header válido == `Correlation::current()`. Host/fixture de runtime apropriados; nenhum alvo real de produção, sem segredos/dados de usuário.
- Suíte completa re-executada: **38 passed, 199 assertions**; Pint PASS 46; Larastan 10 No errors; CSD strict exit 0; unittest 7 OK; doctor healthy; diff-check clean.

**Checkpoint onda 2 (2026-10-04):** fatia de logs seguros (correlação/redação) integrada via PR #33 (`main` `4f7c74d`); stack async da lane #20 (PR #34, `main` `44aa89a`) provisiona os serviços que esta fatia observa; issue [#21](https://github.com/yanpenalva/starter-kit-api/issues/21) permanece aberta (observabilidade restante = onda 6).
