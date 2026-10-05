# Versioned Handoff — FEAT-10 (registro de owner handoff no delta de contrato)

## State

- Current phase/status: verify (sem mudança de fase; nota mínima de integração).
- Approved scope: owner handoff autorizado pelo parent (EXC-0001) executado na worktree AUTH (`starter-kit-api-wave3-auth-worktree`, branch `chore/wave-3-ptbr-quality`, base `f89be5d59e04288661a3efe7e3c2ac5b0ebd6a52`) enquanto o writer da lane está pausado.
- Decisions: o catálogo pt-BR (`App\Support\ApiMessage`) passou a ser a fonte única também dos exemplos do contrato OpenAPI — 401/403/419/429 de login, 401/419/429 de logout e 401/403/429 de profile agora refletem EXATAMENTE os bodies de runtime (incluindo `'Não autenticado.'` para 401 de logout/profile). Middleware/disallow/bootstrap intocados; nenhum endpoint novo.
- Files and contracts: apenas `app/OpenApi/*`, `tests/Feature/Contract/OpenApiSnapshotTest.php`, `contracts/openapi.json` (regenerado) — detalhe completo em `../FEAT-11/handoff.md`.
- Commands and evidence: Pest 106 passed/1160 assertions/0 warnings com novo parity test (`contract examples match real runtime responses`) cobrindo 401/403/404/405/419/429 reais contra endpoints existentes; demais gates no recibo da sessão e em `../FEAT-11/handoff.md`.
- Failures and risks: revisão independente do delta PENDENTE; nenhuma aprovação alegada.
- Next step: combo review do parent (core `f89be5d` + overlay/snapshot delta).
