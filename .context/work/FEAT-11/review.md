# Review — FEAT-11

> **STATUS: PENDING — STAGE B REVIEW PENDING (candidate SHA exact, independent review NOT yet performed).**
> Stage A (CONTRACT prep, `edbbe2f`) remains approved (59 unittests + 25 adversariais históricos). A implementação do Stage B abaixo é nova, não revisada de forma independente, e NÃO deve ser tratada como aprovada. Nenhum merge, fechamento de issue, publicação real ou claim de produção/attestation é feito.

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

- **Base Main Imutável**: `1390009d37f6a3493b21be7d83dbd32ae355d579` (PR #37 auth merge consumido via merge commit `7693209`).
- **Scramble**: `dedoc/scramble 0.13.47` fixado no `composer.lock` (`d1597812fa8ce40a257d83c527bec28cb60de7a5`); PHP 8.5.11, Laravel 13.34.
- **Snapshot OpenAPI**: `contracts/openapi.json` (OpenAPI 3.1.0, 19439 bytes, SHA256 `be1842f089e66755d48ecc4661c6b9ec7bb86762198544316b6d2870213c3239`, exportado deterministicamente 2x sob `LC_ALL=C` e `TZ=UTC`).

### Resolução dos Cinco Blockers Confirmados

1. **Servidor OpenAPI e Paths Completos (Blocker 1)**:
   - Servidor fixado em `'/'` root-relative em `ContractDocumentTransformer::ensureServers`, sem dependência de `APP_URL` ou origem local (`localhost:8080`).
   - Configuração de `config/scramble.php` com `'api_path' => ['include' => ['api/v1', 'api/v1/*']]` preservando caminhos reais completos: `/api/v1`, `/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile`.
   - Rota `/sanctum/csrf-cookie` na raiz, válida e independente.
   - Resolução de URLs de operação contra rotas reais do framework testada programaticamente em `OpenApiSnapshotTest`.
   - Independência de `APP_URL` comprovada: exportação sob `APP_URL=http://staging.internal:9999` e `http://unexpected-custom-origin.test:9999` produz bytes idênticos.

2. **Remoção de 429 na Rota CSRF (Blocker 2)**:
   - Endpoint `/sanctum/csrf-cookie` pertence ao middleware `web` sem throttling; declaração de 429 removida em `injectCsrfCookieOverlay` (mantendo apenas resposta 204).
   - Operações throttled da API (`/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile`) preservam resposta 429 com cabeçalho `Retry-After`.
   - Ambas as condições são estritamente asseridas em `OpenApiSnapshotTest`.

3. **CI de Breaking Changes com Ferramenta Fixada (Blocker 3)**:
   - Ferramenta fixada: `oasdiff` versão `1.33.0` via script `scripts/contract_compat.py`.
   - Base de comparação: commit baseline imutável `1390009d37f6a3493b21be7d83dbd32ae355d579`.
   - Bootstrap fail-closed: commit existe no Git e o arquivo `contracts/openapi.json` está comprovadamente ausente no baseline (código 128 com erro de objeto não existente). Erros arbitrários falham fechado sem bootstrap.
   - Fixtures operacionais: 5 negativas (remoção de rota, remoção de status de sucesso, alteração de tipo, novo campo obrigatório na requisição, remoção de propriedade obrigatória da resposta) rejeitadas com erro; 1 fixture positiva (mudança compatível) aceita.
   - Workflow `.github/workflows/quality.yml` atualizado com checkout completo (`fetch-depth: 0`), instalação do binário fixado e execução de `contract_compat.py`.

4. **Workflow de Publicação com Disparo Estrito (Blocker 4)**:
   - Removido trigger extra `push: branches: [main]` de `.github/workflows/openapi.yml`.
   - Publicação restrita exclusivamente a `workflow_dispatch` na branch `main`.

5. **Decomposição Coesa CSD15 (Blocker 5)**:
   - `ContractDocumentTransformer` decomposto em 3 classes com responsabilidades estritas e coesas:
     - `app/OpenApi/ContractResponseFactory.php`: 3 métodos, 56 linhas, zero `else`/`elif`.
     - `app/OpenApi/ContractOperationOverlay.php`: 12 métodos, 134 linhas, zero `else`/`elif`.
     - `app/OpenApi/ContractDocumentTransformer.php`: 8 métodos, 153 linhas, zero `else`/`elif`.
   - Todos os 8 scripts de produção em `scripts/` e todas as classes PHP atendem a `<= 15` métodos/funções, `<= 500` linhas, `<= 7` parâmetros, `<= 3` retornos e zero `else`/`elif`.

### Reconciliação da Discrepância de Asserções

- **Discrepância histórica**: produtor anterior relatou `96 passed, 621 assertions`; revisor relatou `96 passed, 625 assertions`.
- **Origem comprovada**: o commit base da main `1390009` contém 89 testes e 572 asserções (com `HttpAuthSmokeTest` contribuindo 60 asserções após o fechamento dos achados M1, M2, L3, L4 em `617e562`). A adição de `OpenApiSnapshotTest` (33 asserções) e `ApiDocsAccessTest` (20 asserções) perfaz exatamente 572 + 33 + 20 = 625 asserções. A contagem histórica de 621 refletia a execução contra rascunho anterior em que `HttpAuthSmokeTest` possuía 57 asserções (-3) e outra asserção defasada (-1).
- **Contagem atual**: com a adição de asserções de resolução de URL de operações e verificação estrita de ausência/presença de 429, a suíte Pest reporta **96 passed (644 assertions)**.
- **Python unittests**: **87 passed** (59 Stage A + 28 Stage B).

### O Revisor Independente Deve Conferir

1. Frozen `ReceiptRecord` e `VerificationPolicy` permanecem íntegros sem alterações no Stage A aprovado.
2. Comportamento e rotas de autenticação da lane auth intactos.
3. Ausência de esquemas Bearer, JWT ou PAT na especificação exportada.
4. Servidor root-relative `'/'` e paths completos `/api/v1/...` com `/sanctum/csrf-cookie` na raiz.
5. Ausência de declaração de 429 na rota CSRF e presença nas rotas de API.
6. Funcionamento de `scripts/contract_compat.py` contra `1390009` com bootstrap e fixtures operacionais.
7. Trigger único `workflow_dispatch` em `.github/workflows/openapi.yml`.
8. Conformidade CSD15 (`<= 15` métodos, zero `else`) em todos os fontes de produção tocados.
