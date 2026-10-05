# Versioned Handoff — FEAT-11

## Historical handoff context — 2026-10-04

## State

- Current phase/status: verify (artefato OpenAPI da main `e646c34` publicado pelo workflow `OpenAPI Contract` e verificado em 2026-10-05; critérios de consumo frontend e proteção de docs em produção ainda sem evidência; issue #11 continua ativa).
- Approved scope: transferência de ownership autorizada pelo parent (EXC-0001): execução do escopo contract-owner NESTA worktree AUTH, enquanto o writer da lane AUTH está com terminal pausado.
- Decisions:
  - Sessão NATIVA FRESCA iniciada com `--dir` na worktree AUTH. Motivo: tentativa de continuar a sessão produtora `ses_ef6fc2ef1ffekDEo6PtpfrA3jn` com `--dir` falhou probe-verify — o cwd do worker permaneceu na worktree do contrato (`starter-kit-api-wave3-contract-worktree`, HEAD `0cb5fda`); `--dir` não redireciona sessão continuada, então continuar escreveria na árvore errada.
  - Fonte da verdade para exemplos de mensagem: constantes `App\Support\ApiMessage` (nenhuma string EN/pt-BR independente duplicada nos builders do contrato).
  - `ContractResponseFactory`/`ContractOperationOverlay` agora referenciam `ApiMessage::THROTTLED/CSRF/INVALID_CREDENTIALS/DEACTIVATED/UNAUTHENTICATED`; overlay assumiu 401/403 de login e 401 de logout/profile (antes `$ref` sem exemplo) para alinhar a instruções de runtime reais.
  - CSRF vendor (`/sanctum/csrf-cookie`) permanece unthrottled com 204 apenas (sem 429) — verdade de runtime preservada; 403 por desativação permanece DISTINTO do 403 genérico.
- Files and contracts: `app/OpenApi/ContractOperationOverlay.php`, `app/OpenApi/ContractResponseFactory.php`, `tests/Feature/Contract/OpenApiSnapshotTest.php` (parity com runtime real + asserção dos exemplos a partir de `ApiMessage`), `contracts/openapi.json` (SOMENTE regenerado via Scramble 0.13.47; nunca editado à mão).
- Commands and evidence: gates reais desta execução registrados no recibo da sessão produtora (Pint 86 files, Larastan 10 OK, composer validate/audit/platform OK, Pest 106 passed/1160 assertions/0 warnings, Python 113 OK, CSD strict/examples/doctor 1.2.1 OK, export 2× byte-idêntica + 2× APP_URL variantes byte-idênticas, oasdiff real 1.33.0 (tarball `43a4e328…`, binário `fa65aae4…` verificados) contra baseline real `21fc960` = zero breaking, sem bootstrap).
- Failures and risks: revisão independente do NOVO delta (overlay/ snapshot) PENDENTE — o PASS histórico vale para `227b75f`/`9334e2d`, não para o novo SHA; CI do novo head deve confirmar freshness gate do snapshot.
- Next step: revisão independente do combo (core `f89be5d` + overlay delta) pelo parent antes de qualquer merge/publicação.

## Publicação observada em main — 2026-10-05

- Evidência: workflow `374937885`, run `37255680565` (`workflow_dispatch`, head `e646c34a280b851847676af290494e872edcaa9b`) SUCCESS; artifact `11321734331`, nome versionado por SHA. Detalhes, hashes, comandos e limitações em `release.md`.
- Verificação: SHA-256 do ZIP local corresponde ao digest publicado pelo provider; extração segura e `contract_cli.py --mode download-fresh` passaram; OpenAPI extraído (21,323 bytes) corresponde ao blob versionado na main.
- Pendências: frontend ainda precisa demonstrar geração/consumo dos tipos; documentação protegida em produção ainda sem evidência; issue #11 não atende todos os critérios e não deve ser fechada. Parent valida bundle/integração e decide fechamento.
- Entrega documental desta continuação: branch `docs/wave-3-contract-published` → `main`, remote `origin`, sob EXC-0001.

## Política de docs em contexto production — 2026-10-05

- Verificação request-time para Scramble UI + JSON registrada em `verification.md`: anônimo, non-admin, admin não verificado e admin desativado recebem 403 nos dois endpoints; admin verificado/ativo recebe 200. `APP_DEBUG=false`; corpos negados sem stack/class SQL detalhada.
- A decisão do vendor middleware é lazy por request (`environment('local')`), enquanto provider/Gate e rotas são registrados no boot sem condição de ambiente. Teste muda somente `app.environment` após boot; não alega boot ou infra de produção reais.
- Suíte completa passou: 108 testes/1.279 assertions. Composer validate/platform/audit, Pint (86 arquivos), Larastan (58 arquivos), CSD strict/examples, Python 113 e doctor 1.2.1 passaram. CI do novo commit ainda aguardará push.
- Critérios #11: critério 1 observado por snapshot/contract validation; critério 3 observado no harness limitado; critério 2 (frontend gera tipos do artefato) continua pendente. Issue #11 segue open/active; parent faz review/integração.
