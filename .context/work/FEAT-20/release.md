# Release and Deployment — FEAT-20

## Readiness

- [x] Verification and review approved. (oracle PASS em `08009f7`; re-reviews rounds 1–2 registradas)
- [x] Required tests and smoke checks have evidence. (Pest 53/248 em CI do PR e do `main` pós-merge; smoke async local)
- [x] Migration and data compatibility assessed. (migration reservada `failed_jobs`, schema padrão; nenhuma migração de produção executada)
- [x] Feature flags/configuration documented. (perfil `async` opt-in; defaults sync/log documentados em compose/README)
- [x] Rollback trigger and procedure tested or justified. (N/A produção: entrega dev/local; rollback = reverter merge na `main`; nenhum deploy feito)
- [ ] Communication and support coverage arranged. (N/A — sem release de produção; suporte operacional permanece NOT FOUND)
- [ ] Observability signals and thresholds named. (N/A produção — observabilidade completa é #21/#6, onda 6)

## Authorization

- Release owner: yanpenalva
- Production approver: N/A (nenhuma liberação de produção nesta fatia; alvo de produção continua indefinido)
- Target: branch `main` do repositório API (integração de código, não deploy)
- Planned time: executado em 2026-10-04 (PR #34, merge `44aa89a29dbda6a16164014c914cc3b411324186`, autorização excepcional do owner com GitGuardian `Triggered` — [approval.md](approval.md))

## Rollback

- Trigger: N/A produção (nenhum deploy); regressão em `main` → revert do merge `44aa89a` por decisão do parent
- Procedure: `git revert -m 1 44aa89a` + CI; branch `feat/wave-2-async-recovery` preservada
- Decision owner: yanpenalva

## Observation

- Smoke check: smoke async local pós-gates (worker/scheduler healthy, entrega sintética Mailpit, cleanup lane-scoped)
- Window: N/A (sem produção)
- Healthy signals: CI `main` verde no SHA do merge (run `37211538628`)
- Escalation path: parent/orquestrador

## Git finalization

- Finalization mode: `automatic` (EXC-0001 + autorizações específicas registradas)
- Startup authorization recorded in work item: EXC-0001; autorização excepcional de merge em approval.md
- Validation summary and exact commands: ver [verification.md](verification.md) (gates 2026-10-03/04 e CI remota)
- Proposed commit message: n/a (commits já entregues na PR #34)
- Commit approval: `APPROVED`
- Commit hash: `08009f7717c8e9e4a77782649c7680c39e15dd6e` (head da PR)
- Push target: `origin/feat/wave-2-async-recovery`
- Push approval: `APPROVED`
- Push result: `ccee7f4..08009f7` pushed
- Tag approval/result: NENHUMA tag criada (fora de escopo)
