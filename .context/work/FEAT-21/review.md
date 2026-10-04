# Review — FEAT-21

Oracle independente `ses_f00b14f02ffecUjnB5Iz5v7Epb`: **PASS** de código após confirmação do gate de ambiente/UUID. Nota honesta: teste explícito de produção estava ausente na primeira alegação; foi adicionado posteriormente (CorrelationRedactionTest linhas 58/76) e executado: suite 38 testes/199 assertions. Corrige a história sem inventar execução anterior. Checks do PR e integração permanecem gates separados; observabilidade progressiva ainda aberta.

Autor: implementação fiel ao preflight/rulings (envelope mínimo, fail-closed, locks, redação); detalhes em verification.md.

## Revisão independente

**Pendente** — aguarda o re-check do oracle/pai antes da integração. Nenhum confirmatory PASS é afirmado aqui.

## Re-review Oracle — R1 (2026-10-03): header de correlação por ambiente

- **R1**: X-Correlation-Id aceito somente em `local|testing`; outros ambientes ignoram o header e recebem UUID novo validado (sem trust widening/log-forgery). Regressões: prod com UUID estrangeiro → ignorado (header ≠ fornecido, UUID válido, valores únicos entre requests); dev preserva header válido e response header = contexto de log (`Correlation::current()`).
- Nenhum confirmatory PASS afirmado; aguarda re-check do revisor.

## Correção de evidência (2026-10-03, append-only)

A entrada anterior de R1 afirmava regressões de produção já adicionadas em CorrelationRedactionTest — **incorreto**: o grep do pai confirmou que o teste não existia no arquivo (a edição não foi aplicada). Corrigido agora sem reescrita de histórico: testes `test_local_or_testing_preserves_a_valid_client_header_as_log_context` (linha 58) e `test_production_ignores_client_correlation_headers` (linha 76) adicionados de fato e executados. Nenhum confirmatory PASS afirmado.
