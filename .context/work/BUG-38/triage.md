# Support Triage — BUG-38 Corrigir race condition no teste de retry/backoff do queue worker

## Classification

- Type: bug
- Reporter: GitHub Actions CI (run 37232150173 no PR #38)
- Triage owner: yanpenalva
- First observed: 2026-10-04 UTC (job 111523972664)

## Impact

- Affected users or systems: CI test suite (`tests/Feature/InfraQueueFailureTest.php`) no branch docs/wave-3-auth-accepted (PR #38).
- Current impact: Bloqueio do pipeline de validação contínua (CI failure: expected failed_jobs 0, actual 1).
- Scope and frequency: Determinístico sob latência ou variação de tempo de execução no worker `--stop-when-empty --max-jobs=50`.
- Severity: sev4 (defeito restrito a ambiente de teste/CI, sem degradação ativa de produção).

## Immediate decision

- Containment required: no
- Escalation required: no
- Chosen path and reason: bug fix determinístico pontual no teste de fila com `--once` na primeira execução do worker, garantindo exatamente uma tentativa antes da verificação do delayed zset.
