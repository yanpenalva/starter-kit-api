# Review — FEAT-11

> **STATUS: PENDING — AGUARDANDO REVISÃO INDEPENDENTE (Stage A — CONTRACT prep, conformidade estrita CSD <= 7 parâmetros).**
> Esta entrega conclui a resolução do requisito normativo CSD MUST `<= 7` parâmetros declarados, apontado na revisão independente do commit `78c3f57709e212b6f846f99276ae2c202f84c9bd`.
> O implementador NÃO autoaprova este item nem progride o status para `Done`.
> A conclusão de API #11 permanece como item progressivo que depende da integração da autenticação L1 (FEAT-13) e da execução do Stage B.

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
