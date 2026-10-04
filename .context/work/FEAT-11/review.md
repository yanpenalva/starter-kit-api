# Review — FEAT-11

> **STATUS: PREVIOUS FAIL (integration) — PENDING INDEPENDENT PARENT RE-REVIEW (candidate SHA exact).**
> Stage A (CONTRACT prep, `edbbe2f`) remains approved (59 unittests + 25 adversariais históricos). A revisão independente anterior pelo parent orchestrator via conversa AGY `b0f5f43d-85e2-4848-9cc6-a3c37bd67e14` resultou em FAIL de integração sobre o commit `35f6ae8`. As cinco constatações de revisão foram integralmente remediadas nesta submissão candidata e submetidas para reavaliação independente. Nenhum merge, fechamento de issue, publicação real ou alegação de auto-aprovação de PASS é feita.

## Inputs para o revisor independente — Stage A (Aprovado em `edbbe2f`)

- **Escopo**: FEAT-11 Stage A (CONTRACT prep, onda 3, API #11, resolução de CSD MUST `<= 7` parâmetros declarados via objetos de parâmetros congelados).
- **Branch**: `feat/wave-3-openapi-prep` → alvo `main`, remote `origin`, EXC-0001.
- **Base commit**: `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` (PR #36 merge).
- **Histórico Stage A**:
  - Em `883246c`: Oracle PASS WITH CONDITIONS (condições S1–S9).
  - Em `f910f66` e `33cbcab`: Condições S1–S9 implementadas; 47 testes unitários + 25 testes adversariais passaram com sucesso sem defeitos de lógica; blocker: **R1** (teto CSD de 500 linhas).
  - Em `af10a42`: Revisão Independente PASS WITH CONDITIONS (Relatório `66afdcbd-69b5-471e-af8f-32c072109b84`). R1 atendido (< 500 linhas); remediações de DAG acíclico, carregamento dinâmico sem `sys.path`, ausência de dupla execução sob `__main__` e exit code 2 implementadas em `78c3f57`.
  - Em `78c3f57`: Aprovado com a introdução dos objetos `@dataclass(frozen=True)` `VerificationPolicy` e `ReceiptRecord`, reduzindo assinaturas para `<= 5` parâmetros. Aprovado formalmente em `edbbe2f`.

---

## Inputs para o revisor independente — Stage B (Candidato Atual, 2026-10-04)

- **Base Main Integrada**: `f5dce81711315458e8c32cf61367d58b5206ac05` (PR #38 merge consumido via merge commit ordinário).
- **Scramble**: `dedoc/scramble 0.13.47` fixado no `composer.lock` (`d1597812fa8ce40a257d83c527bec28cb60de7a5`); PHP 8.5.11, Laravel 13.34.
- **Snapshot OpenAPI**: `contracts/openapi.json` (OpenAPI 3.1.0, 19439 bytes, SHA256 `be1842f089e66755d48ecc4661c6b9ec7bb86762198544316b6d2870213c3239`, exportado deterministicamente 2x sob `LC_ALL=C` e `TZ=UTC`).

### Resolução dos Cinco Findings de Revisão do Parent

1. **Resolvedor Dinâmico de Baseline Imutável (Finding 1 — HIGH)**:
   - Implementado `scripts/resolve_contract_baseline.py` para extração estrita do payload oficial do GitHub Actions:
     - `pull_request`: extrai `pull_request.base.sha` (nunca head ou candidato).
     - `push`: restrito exclusivamente a `refs/heads/main`, extrai `before` SHA.
   - Validação estrita de SHA de 40 caracteres hexadecimais, rejeição de zero-SHA e rejeição de auto-comparação contra o HEAD atual.
   - Caminho fixo exclusivo em `contracts/openapi.json` (sem caminhos arbitrários ou fallbacks).
   - Ausência do contrato no baseline comprovada por `git ls-tree` em estado tri-partido: qualquer erro Git que não seja ausência genuína falha fechado sem bootstrap.
   - Testado com repositórios reais de ciclo de vida git: bootstrap na primeira ausência; quebra real (remoção de rota) rejeitada com exatamente 1 finding `error[api-path-removed-without-deprecation]`; SHA inexistente, caminho inválido, repositório sem Git, push `before` zero, baseline == HEAD e commit indisponível falham fechado; mudanças compatíveis passam.

2. **Reconciliação e Prova de Asserções (Finding 2 — MED)**:
   - Causa da discrepância histórica de 621 asserções permanece **HISTORICAL CAUSE NOT VERIFIED**; nenhuma especulação causal é alegada.
   - Observação ANTIGA registrada (CI sobre `35f6ae82`): 96 testes, WARNINGS, 644 asserções, exit 0.
   - Observação ATUAL local desta submissão (2026-10-04, duas execuções): **96 passed, 645 asserções, 0 warnings**, exit 0.
   - Observação ATUAL de CI (run 37240902731, job `application`, sobre `e73f993`): 96 testes, 96 warnings, 645 asserções, exit 0 — todos da classe `file_get_contents(/home/runner/...)` (issue pré-existente API-24 da lane de auth, fora do escopo daqui).
   - As observações permanecem estritamente separadas, sem reconciliação causal.

3. **Alinhamento do Work Item e Fases Canônicas CSD (Finding 3 — MED)**:
   - Atualizado `work-item.json` para fase canônica `verify`, histórico completo `["discover", "specify", "plan", "preflight", "execute", "verify"]`.
   - Criado `preflight.md` registrando o preflight check do Stage B e o registro factual do gap documental do passado.
   - Status de revisão explicitado como **Previous FAIL (integration) — pending independent parent re-review**.

4. **Integridade Criptográfica do Binário oasdiff (Finding 4 — MED)**:
   - Implementado `scripts/install_oasdiff.py` com validação de digest SHA256 oficial do tarball (`43a4e328e2d13ba1552d760aa68d2485c75c5621f309f6ff64ae895188345247`, confirmado no `checksums.txt` oficial da release v1.33.0) e do binário extraído (`fa65aae43c867e9f79da9cb121d3c1a113637883afe014c0680fd5b50696f416`, medido nesta execução; não publicado no `checksums.txt` oficial).
   - Verificação fail-closed antes da extração ou execução; extração com limites de tamanho/nome, rejeição de symlink/link, membro ausente ou não regular; aplicado consistentemente nos jobs `context` e `contract` do workflow `quality.yml`.
   - Testes unitários comprovam rejeição imediata em caso de adulteração, mismatch de asset ou de binário.

5. **Localização em pt-BR e Limpeza Narrativa em openapi.yml (Finding 5 — LOW)**:
   - Removidos comentários narrativos extensos de `.github/workflows/openapi.yml`.
   - Traduzidas as descrições de inputs e mensagens de erro do guard para pt-BR.
   - Exemplos de resposta em runtime da API preservados no padrão em inglês atual da API.

### Observações de Asserções Pest — Fatos Observados Apenas

- **Discrepância histórica**: Produtor anterior relatou `96 passed, 621 assertions`; revisor relatou `96 passed, 625 assertions`.
- **Causa**: **NOT VERIFIED**. Nenhuma explicação causal é alegada; relatos permanecem como fatos brutos separados.
- **Observação ANTIGA (CI sobre `35f6ae82`)**: 96 testes, WARNINGS, 644 asserções, exit 0.
- **Observação ATUAL (2026-10-04, duas execuções locais)**: **96 passed, 645 asserções, 0 warnings**, exit 0.
- **Python unittests atuais**: **113 passed** (7 CSD + 52 Stage A + 54 Stage B).

### O Revisor Independente Deve Conferir

1. Frozen `ReceiptRecord` e `VerificationPolicy` permanecem íntegros sem alterações no Stage A aprovado.
2. Comportamento e rotas de autenticação da lane auth intactos.
3. Ausência de esquemas Bearer, JWT ou PAT na especificação exportada.
4. Servidor root-relative `'/'` e paths completos `/api/v1/...` com `/sanctum/csrf-cookie` na raiz.
5. Ausência de declaração de 429 na rota CSRF e presença nas rotas de API.
6. Funcionamento de `scripts/contract_compat.py` com baseline resolvido dinamicamente do evento (ou `--base-commit` explícito, ex.: `1390009` para fixtures locais), bootstrap tri-state via `git ls-tree` e fixtures operacionais.
7. Trigger único `workflow_dispatch` em `.github/workflows/openapi.yml`.
8. Conformidade CSD15 (`<= 15` métodos, zero `else`) em todos os fontes de produção tocados.
9. Resolvedor de baseline: eventos fora de `pull_request`/`push`, push fora de `refs/heads/main`, zero-SHA, SHA malformado, commit indisponível e baseline == HEAD falham fechado.
10. Integridade do instalador: digests do asset e do binário fixados; verificação antes de extração/execução; rejeição de symlink, membro ausente, oversized e não regular.
