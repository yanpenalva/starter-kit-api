# Verification — FEAT-11

Data de execução: 2026-10-04 · Worktree persistente exclusivo: `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree` · Branch: `feat/wave-3-openapi-prep` (base main imutável `origin/main` @ `1390009d37f6a3493b21be7d83dbd32ae355d579`).

## Gates Executados — Stage B (Evidência Real)

| Gate | Comando | Exit Code | Resultado e contagens |
| --- | --- | :---: | --- |
| 1. Composer validate | `docker compose -p starter-wave3-contract run --rm --no-deps api composer validate --strict` | 0 | `./composer.json is valid` |
| 2. Platform requirements | `docker compose -p starter-wave3-contract run --rm --no-deps api composer check-platform-reqs` | 0 | `success` para todas as extensões e PHP 8.5.11 |
| 3. Composer audit | `docker compose -p starter-wave3-contract run --rm --no-deps api composer audit` | 0 | `No security vulnerability advisories found.` |
| 4. Pint linter | `docker compose -p starter-wave3-contract run --rm --no-deps api composer lint` | 0 | `PASS 81 files` (estilo Laravel estrito) |
| 5. Larastan Level 10 | `docker compose -p starter-wave3-contract run --rm --no-deps api composer analyse` | 0 | `[OK] No errors` (57 arquivos analisados, memória 512M) |
| 6. Pest test suite | `docker compose -p starter-wave3-contract --profile test run --rm test vendor/bin/pest` | 0 | **96 passed (644 assertions)** em 34.37s |
| 7. Validador estrito CSD | `python3 scripts/validate_context.py --strict` | 0 | `Context validation passed.` (0 erros) |
| 8. Validador CSD exemplos | `python3 scripts/validate_context.py --strict --examples` | 0 | `Context validation passed.` (0 erros) |
| 9. Unittests Python | `python3 -m unittest discover -s tests -v` | 0 | **87 passed, 0 failed** em 2.268s (7 CSD + 52 Stage A + 28 Stage B) |
| 10. CSD Doctor | `npx -y @owlcodium/context-spec-develop@1.2.1 doctor --scope project` | 0 | `CSD is healthy (1.2.1)` (0 achados) |
| 11. Checker Breaking Changes | `python3 scripts/contract_compat.py --base-commit=1390009d37f6a3493b21be7d83dbd32ae355d579 --contract-path=contracts/openapi.json --candidate=contracts/openapi.json` | 0 | `BOOTSTRAP VÁLIDO: Commit baseline '1390009...' existe e caminho comprovadamente ausente. Checker fixado 'oasdiff 1.33.0' operacional com 5 fixtures negativas rejeitadas e 1 positiva aceita.` |

---

## Auditoria Estrutural AST dos Módulos de Produção (code-quality.md / CSD15)

Auditoria estrutural executada em todos os arquivos de produção tocados ou criados:

### Módulos Python em `scripts/`

| Arquivo de produção | Linhas físicas (teto: 500) | Funções / Métodos (teto: 15) | Classes | Máx. parâmetros (teto: 7) | Retornos máx. (teto: 3) | `else` / `elif` |
| --- | :---: | :---: | :---: | :---: | :---: | :---: |
| `scripts/contract_core.py` | **390** | **14** | 2 | **5** (`verify_provenance`) | 1 | **0** |
| `scripts/contract_receipt.py` | **84** | **2** | 1 | **1** | 3 | **0** |
| `scripts/contract_cli.py` | **185** | **4** | 0 | **1** | 3 | **0** |
| `scripts/verify_contract_artifact.py` | **160** | **1** | 0 | **1** | 3 | **0** |
| `scripts/contract_export.py` | **130** | **6** | 2 | **2** | 2 | **0** |
| `scripts/contract_extract.py` | **182** | **6** | 3 | **3** | 2 | **0** |
| `scripts/contract_fetch.py` | **126** | **6** | 2 | **2** | 2 | **0** |
| `scripts/contract_compat.py` | **362** | **10** | 2 | **4** | 2 | **0** |

### Classes PHP em `app/`

| Arquivo de produção | Linhas físicas (teto: 500) | Métodos declarados (teto: 15) | Máx. parâmetros (teto: 7) | Retornos máx. (teto: 3) | `else` / `elseif` |
| --- | :---: | :---: | :---: | :---: | :---: |
| `app/OpenApi/ContractResponseFactory.php` | **56** | **3** | **2** (`messageResponse`) | 1 | **0** |
| `app/OpenApi/ContractOperationOverlay.php` | **134** | **12** | **2** (`ensureResponse`) | 1 | **0** |
| `app/OpenApi/ContractDocumentTransformer.php` | **153** | **8** | **2** (`injectCsrfCookieOverlay`) | 1 | **0** |
| `app/Providers/ScrambleDocServiceProvider.php` | **36** | **2** | **0** | 1 | **0** |

---

## Evidência de Exportação Determinística (Snapshot OpenAPI)

- **Comandos de exportação**:
  ```sh
  docker compose -p starter-wave3-contract --profile test run --rm -e LC_ALL=C -e TZ=UTC test php artisan scramble:export --path=storage/app/pass1.json
  docker compose -p starter-wave3-contract --profile test run --rm -e LC_ALL=C -e TZ=UTC test php artisan scramble:export --path=storage/app/pass2.json
  cmp storage/app/pass1.json storage/app/pass2.json
  ```
- **Idempotência**: Saída de `cmp` vazia (exit code 0: arquivos byte a byte idênticos em passadas consecutivas).
- **Digest SHA256**: `be1842f089e66755d48ecc4661c6b9ec7bb86762198544316b6d2870213c3239`
- **Tamanho**: `19439` bytes
- **Independência de `APP_URL`**: Execução com `APP_URL=http://staging.internal:9999` e `http://unexpected-custom-origin.test:9999` validada contra `contracts/openapi.json`: `cmp` retornou exit 0 (independência total).
- **Servidor OpenAPI**: `servers: [{"url": "/"}]` (root-relative `/`, sem URLs locais fixas).
- **Paths completos**: `/api/v1`, `/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile`, `/sanctum/csrf-cookie`.
- **Validação de 429**:
  - `/sanctum/csrf-cookie`: apenas `204` (sem declaração de `429`).
  - `/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile`: declaram `429` com header `Retry-After`.

---

## Verificador de Compatibilidade Baseline (`oasdiff 1.33.0`)

- **Ferramenta**: `oasdiff` versão `1.33.0` (binário oficial linux_amd64).
- **Baseline commit**: `1390009d37f6a3493b21be7d83dbd32ae355d579` (imutável).
- **Comprovação de ausência de contrato**: `git cat-file -e 1390009...:contracts/openapi.json` retorna código 128 com `fatal: Not a valid object name` (caminho comprovadamente inexistente no commit baseline da main).
- **Bootstrap comprovado**: Executado em modo bootstrap com commit existente e caminho comprovadamente ausente.
- **Fixtures operacionais testadas**:
  - `path_removido`: Rejeitada com `exit 1` (`api-path-removed-without-deprecation`).
  - `status_removido`: Rejeitada com `exit 1` (`response-success-status-removed`).
  - `tipo_alterado`: Rejeitada com `exit 1` (`response-property-type-changed`).
  - `campo_obrigatorio_adicionado_requisicao`: Rejeitada com `exit 1` (`request-property-became-required`).
  - `propriedade_obrigatoria_removida_resposta`: Rejeitada com `exit 1` (`response-required-property-removed`).
  - `positiva_compativel`: Aceita com `exit 0` (nenhuma quebra de contrato reportada).

---

## Reconciliação da Discrepância Histórica de Asserções

- **Discrepância**: Produtor anterior citava `96 passed, 621 assertions`; revisor citava `96 passed, 625 assertions`.
- **Reconciliação e Prova**:
  - No commit base `1390009`, a suíte de autenticação contém 89 testes e 572 asserções (com `HttpAuthSmokeTest` contribuindo 60 asserções após o fechamento dos achados M1, M2, L3, L4 em `617e562`).
  - A adição inicial dos testes de contrato Stage B (`OpenApiSnapshotTest` com 33 asserções e `ApiDocsAccessTest` com 20 asserções) totalizou: 572 + 33 + 20 = **625 asserções**.
  - A menção histórica a 621 asserções decorria da execução contra um rascunho em que `HttpAuthSmokeTest` possuía 57 asserções (-3) e outra asserção pendente (-1).
  - Com o aprofundamento das asserções de resolução de URL de operações e checagem de 429, a suíte Pest agora executa **96 passed (644 assertions)**.

---

## Status de Entrega e Limitações

- **Stage A**: Aprovado em `edbbe2f` com 59 unit + 25 testes adversariais históricos preservados.
- **Stage B**: **PENDING INDEPENDENT REVIEW** pelo parent orchestrator sobre o SHA candidato exato.
- Nenhum merge, fechamento de issue, publicação real no GitHub Actions ou alegação de aprovação é feita.
