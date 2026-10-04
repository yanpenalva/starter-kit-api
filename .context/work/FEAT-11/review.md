# Review — FEAT-11

> **STATUS: PENDING — AGUARDANDO REVISÃO INDEPENDENTE (Stage A — CONTRACT prep, resolução R1 & remediações af10a42).**
> Esta entrega conclui a resolução do item **R1** e as remediações arquiteturais apontadas na revisão independente do commit `af10a423c0f74e73b4a8943ab8d2f35ef614d73c` (relatório `66afdcbd-69b5-471e-af8f-32c072109b84`).
> O implementador NÃO autoaprova este item nem progride o status para `Done`.
> A conclusão de API #11 permanece como item progressivo que depende da integração da autenticação L1 (FEAT-13) e da execução do Stage B.

## Inputs para o revisor independente

- **Escopo**: FEAT-11 Stage A (CONTRACT prep, onda 3, API #11, resolução de R1 & remediações arquiteturais af10a42).
- **Branch**: `feat/wave-3-openapi-prep` → alvo `main`, remote `origin`, EXC-0001.
- **Base commit**: `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` (PR #36 merge).
- **Histórico de revisões**:
  - Em `883246c`: Oracle PASS WITH CONDITIONS (condições S1–S9).
  - Em `f910f66` e `33cbcab`: Condições S1–S9 implementadas; 47 testes unitários + 25 testes adversariais passaram com sucesso sem defeitos de lógica; único blocker remanescente: **R1** (tamanho de `scripts/verify_contract_artifact.py` de 542 linhas excedia o limite de 500 linhas da CSD).
  - Em `af10a42`: Revisão Independente PASS WITH CONDITIONS (Relatório `66afdcbd-69b5-471e-af8f-32c072109b84`). R1 confirmado atendido (< 500 linhas). Condições de correção: (1) baseline de carregamento dinâmico via `spec_from_file_location` a partir de CWD externo sem depender de `sys.path`; (2) arquitetura DAG verdadeiramente acíclica sem ciclos fachada-irmãos e sem dupla execução sob `__main__`; (3) teste para exit 2 em erros inesperados com mensagem `FAIL CLOSED: Unexpected error:`; (4) alinhamento documental (remoção da palavra "signatures" no docstring e contagem física exata).
  - Neste commit: **Remediações implementadas**:
    1. Extração do módulo foundation `scripts/contract_core.py` (380 linhas físicas, 15 declarações, 0 dependências irmãs).
    2. Satélites `scripts/contract_receipt.py` (79 linhas) e `scripts/contract_cli.py` (180 linhas) dependem apenas de `contract_core`, nunca da fachada `verify_contract_artifact.py`.
    3. Fachada `scripts/verify_contract_artifact.py` (156 linhas) reexporta a API pública estável sem sofrer dupla execução sob `__main__` e suporta carregamento dinâmico via `spec_from_file_location` em subprocesso isolado.
    4. Adicionado teste in-process e teste de subprocesso para `run_cli` exit code 2 em erros inesperados.
    5. Docstring e métricas rigorosamente alinhados.
- **Especificação**: `.context/work/FEAT-11/spec.md`.
- **Plano**: `.context/work/FEAT-11/plan.md`.
- **Registro de execução**: `.context/work/FEAT-11/execution.md`.
- **Evidências de verificação**: `.context/work/FEAT-11/verification.md`.

## Verificações de fronteira e segurança a conferir pelo revisor independente

1. **Lane boundary**: Confirmar que nenhum arquivo de `app/`, `config/`, `routes/`, `database/`, `bootstrap/`, `composer.json` ou `composer.lock` foi alterado ou criado (lane auth preservada intacta).
2. **Qualidade de código Python (.context/policies/core/code-quality.md)**:
   - Confirmar ausência total de `else` ou `elif` em todo o código Python novo (`verify_contract_artifact.py`, `contract_core.py`, `contract_receipt.py`, `contract_cli.py`, `tests/tools/test_verify_contract_artifact.py`).
   - Confirmar contagens físicas e declarações: todos os 4 arquivos de produção possuem `<= 500` linhas físicas e `<= 15` funções/métodos declarados.
   - Confirmar que nenhuma função excede 150 linhas e nenhuma possui mais de 3 retornos.
   - Parâmetros com assinaturas legadas > 7 parâmetros (`validate_receipt_identity`, `verify_provenance`, `build_receipt`) foram preservados para manter a compatibilidade da interface pública.
3. **DAG Acíclico e Isolamento de Carregamento**:
   - Confirmar que a estrutura de módulos forma um DAG estrito: `contract_core` <- `contract_receipt` e `contract_cli` <- `verify_contract_artifact`.
   - Confirmar ausência de ciclos mútuos e que os satélites não importam a fachada.
   - Confirmar ausência de dupla execução sob `__main__` e estabilidade da classe `ProvenanceVerificationError`.
   - Confirmar suporte a carregamento isolado via `importlib.util.spec_from_file_location` a partir de diretórios externos arbitrários sem `sys.path`.
4. **Draft guardado**: Confirmar que `.github/workflows/openapi.yml` permanece inalterado com seu guard `exit 1` que falha informativamente sem simular falso sucesso de exportação.
5. **Gates CSD**: Confirmar que os 4 gates de validação CSD passaram com código de saída 0 (57 testes unitários no runner discover).
