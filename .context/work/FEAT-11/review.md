# Review — FEAT-11

> **STATUS: PENDING — STAGE B REVIEW PENDING (real export implemented, independent review NOT yet performed).**
> Stage A (CONTRACT prep, `edbbe2f`) remains the last independently reviewed state; the Stage B implementation below is new, unreviewed, and must NOT be treated as approved. No production, browser, or attestation gate is claimed.

## Inputs para o revisor independente

- **Escopo**: FEAT-11 Stage A (CONTRACT prep, onda 3, API #11, resolução de CSD MUST `<= 7` parâmetros declarados via objetos de parâmetros congelados).
- **Branch**: `feat/wave-3-openapi-prep` → alvo `main`, remote `origin`, EXC-0001.
- **Base commit**: `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` (PR #36 merge).
- **Histórico de revisões**:
  - Em `883246c`: Oracle PASS WITH CONDITIONS (condições S1–S9).
  - Em `f910f66` e `33cbcab`: Condições S1–S9 implementadas; 47 testes unitários + 25 testes adversariais passaram com sucesso sem defeitos de lógica; blocker: **R1** (teto CSD de 500 linhas).
  - Em `af10a42`: Revisão Independente PASS WITH CONDITIONS (Relatório `66afdcbd-69b5-471e-af8f-32c072109b84`). R1 atendido (< 500 linhas); remediações de DAG acíclico, carregamento dinâmico sem `sys.path`, ausência de dupla execução sob `__main__` e exit code 2 implementadas em `78c3f57`.
  - Em `78c3f57` (Revisão Independente Oracle, evidência histórica relatada pelo usuário): FAIL exclusivamente pela regra CSD MUST `<= 7` parâmetros declarados (`contract_core.verify_provenance`: 13, `validate_receipt_identity`: 8, `contract_receipt.build_receipt`: 13). Requisitos de segurança S1–S9, suíte de 57 unitários + 25 adversariais e integridade de carregamento confirmados.
  - Neste commit: **Refatoração com Objetos de Dados Tipados Congelados**:
    1. Retratação formal da justificativa prévia de "interfaces externas imutáveis" (consumidores apenas internos) e autorização humana explícita para refatoração da API interna.
    2. Criação de `@dataclass(frozen=True) VerificationPolicy` em `scripts/contract_core.py` (9 campos, agrupamento de expectativas de identidade confiável, construtor gerado automaticamente).
    3. Criação de `@dataclass(frozen=True) ReceiptRecord` em `scripts/contract_receipt.py` (13 campos exatos do receipt, sem `__post_init__` prematuro).
    4. Redução das assinaturas: `verify_provenance` possui agora **5 parâmetros** (era 13), `validate_receipt_identity` possui **2 parâmetros** (era 8) e `build_receipt` possui **1 parâmetro** (era 13). Nenhuma função em código de produção declara mais de 7 parâmetros.
    5. Migração de todos os pontos de chamada em `contract_cli.py` e na suíte de testes `tests/tools/test_verify_contract_artifact.py`.
    6. Suíte de testes expandida para **59 testes unitários** (7 CSD + 52 tools), cobrindo também imutabilidade dos objetos congelados e chamada direta a `validate_receipt_identity`.
- **Especificação**: `.context/work/FEAT-11/spec.md`.
- **Plano**: `.context/work/FEAT-11/plan.md`.
- **Registro de execução**: `.context/work/FEAT-11/execution.md`.
- **Evidências de verificação**: `.context/work/FEAT-11/verification.md`.

## Verificações de fronteira e segurança a conferir pelo revisor independente

1. **Lane boundary**: Confirmar que nenhum arquivo de `app/`, `config/`, `routes/`, `database/`, `bootstrap/`, `composer.json` ou `composer.lock` foi alterado ou criado (lane auth preservada intacta).
2. **Qualidade de código Python (.context/policies/core/code-quality.md)**:
   - Confirmar ausência total de `else` ou `elif` em todo o código Python (`contract_core.py`, `contract_receipt.py`, `contract_cli.py`, `verify_contract_artifact.py`, `tests/tools/test_verify_contract_artifact.py`).
   - Confirmar contagens físicas e declarações: todos os 4 arquivos de produção possuem `<= 500` linhas físicas e `<= 15` funções/métodos declarados.
   - Confirmar estritamente o limite de parâmetros declarados: nenhuma função de produção excede **7 parâmetros** (máximo real: 5 em `verify_provenance`).
   - Confirmar que nenhuma função excede 150 linhas e nenhuma possui mais de 3 retornos.
3. **DAG Acíclico e Isolamento de Carregamento**:
   - Confirmar que a estrutura de módulos forma um DAG estrito: `contract_core` <- `contract_receipt` e `contract_cli` <- `verify_contract_artifact`.
   - Confirmar ausência de ciclos mútuos e que os satélites não importam a fachada.
   - Confirmar ausência de dupla execução sob `__main__` e estabilidade da classe `ProvenanceVerificationError`.
   - Confirmar suporte a carregamento isolado via `importlib.util.spec_from_file_location` a partir de diretórios externos arbitrários sem `sys.path`.
4. **Draft guardado**: Confirmar que `.github/workflows/openapi.yml` permanece inalterado com seu guard `exit 1` que falha informativamente sem simular falso sucesso de exportação.
5. **Gates CSD**: Confirmar que os 4 gates de validação CSD passaram com código de saída 0 (59 testes unitários no runner discover).

## Stage B inputs for the independent reviewer (unreviewed, 2026-10-04)

- **Base**: merged `origin/main` @ `1390009d37f6a3493b21be7d83dbd32ae355d579` (auth PR #37) into `feat/wave-3-openapi-prep` via merge commit `7693209` (history preserved, no reset/force).
- **Scramble**: `dedoc/scramble 0.13.47` exact pin (lock ref `d1597812fa8ce40a257d83c527bec28cb60de7a5`); PHP 8.5.11, Laravel 13.34.
- **New app-owned files**: `config/scramble.php` (`api/v1`, info 1.0.0), `app/Providers/ScrambleDocServiceProvider.php` (Gate `viewApiDocs`: verified active admin via Role enum), `app/OpenApi/ContractDocumentTransformer.php` (sessionCookie + XSRF overlay, CSRF route, real error contracts), `bootstrap/providers.php` registration.
- **Snapshot**: `contracts/openapi.json` (OpenAPI 3.1.0, 20447 bytes, SHA256 be6e4acd79e8bb9383b39b1e698ba9e3e147cda3c3cf12db223e82cdeee88b8d, LC_ALL=C 2x identical).
- **New scripts**: `scripts/contract_extract.py`, `scripts/contract_export.py`, `scripts/contract_fetch.py` (all ≤7 params, ≤15 funcs, ≤500L, ≤3 returns, no else).
- **New tests**: `tests/tools/test_contract_stageb.py` (18 offline tests), `tests/Feature/Docs/ApiDocsAccessTest.php` (6), `tests/Feature/Contract/OpenApiSnapshotTest.php` (1); `ScaffoldTest` updated for `docs/api` + `docs/api.json` routes.
- **Workflows**: `openapi.yml` real producer export on main (guard fail-closed, minimal perms, artifact `openapi-<fullSHA>`); `quality.yml` contract job (determinism + snapshot freshness + structural validation).
- **Gates observed**: composer validate/audit/platform/lint/analyse exit 0; Pest 96 passed; Python 77 passed; CSD strict/examples/doctor exit 0.
- **Reviewer must verify**: frozen ReceiptRecord/VerificationPolicy unchanged; no auth behavior changes; no Bearer/PAT/JWT schemes; no external $refs; ZIP dual-digest decision documented; no PR/merge/workflow trigger until review.
