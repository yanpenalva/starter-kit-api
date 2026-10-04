# Execução — FEAT-11

Fase: `plan` (Stage A — CONTRACT prep pós-revisão Oracle e resolução R1) · Branch de entrega: `feat/wave-3-openapi-prep` (worktree persistente `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`) → alvo `main` (remote `origin`), EXC-0001. Base: `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` (PR #36 merge).

> **REGISTRO DE REVISÃO INDEPENDENTE:**
> - Oracle PASS WITH CONDITIONS em `883246c`: condições S1–S9 implementadas em `f910f66`.
> - Oracle PASS WITH CONDITIONS em `33cbcab55df28c4fa4cb4e8abc8b50cbae89c65b` (47 security/unit tests + 25 adversariais passaram, zero defeitos de lógica; único blocker remanescente: **R1** — `scripts/verify_contract_artifact.py` possuía 542 linhas físicas > teto CSD de 500 linhas).
> - **Revisão Independente PASS WITH CONDITIONS em `af10a423c0f74e73b4a8943ab8d2f35ef614d73c`** (Relatório `66afdcbd-69b5-471e-af8f-32c072109b84`):
>   - R1 (<=500 linhas físicas) confirmado atendido; 53 testes unittests e 25 testes adversariais passaram.
>   - Condições de correção apontadas: (1) restaurar baseline de carregamento dinâmico via `importlib.util.spec_from_file_location` a partir de CWD externo arbitrário sem depender de `sys.path`; (2) estabelecer DAG acíclico genuíno eliminando ciclos mútuos entre fachada e módulos irmãos e eliminando dupla execução sob `__main__`; (3) teste automatizado para exit code 2 em erros inesperados (`except Exception:`) com mensagem `FAIL CLOSED: Unexpected error:`; (4) alinhamentos documentais (remover menção obsoleta a "signatures" no docstring e registrar contagens físicas e de testes exatas).
> - **Remediações implementadas**:
>   - Extração do módulo foundation `scripts/contract_core.py` (zero dependências irmãs).
>   - Satélites `scripts/contract_receipt.py` e `scripts/contract_cli.py` dependem unicamente de `contract_core`, nunca da fachada `verify_contract_artifact.py`.
>   - Fachada `scripts/verify_contract_artifact.py` consome os irmãos via importador isolado ancorado em `__file__`, resolvendo carregamento dinâmico via `spec_from_file_location` a partir de qualquer CWD sem mutação de `sys.path`.
>   - Ausência de dupla execução sob `__main__` e estabilidade de identidade de `ProvenanceVerificationError`.
>   - Adição de testes de erro inesperado com código 2 e teste de subprocesso com `spec_from_file_location`.
> - Re-review independente pendente; nenhuma autoaprovação; status permanece `active` / fase `plan`. O implementador apenas executa e evidencia as correções necessárias.

## Condições de execução e fronteira de lane

1. **Execução exclusiva no worktree persistente**: Todas as operações são executadas com isolamento no diretório `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`. Nenhuma operação toca o checkout primário, worktrees wave2, frontend ou worktree da lane auth.
2. **Lane boundary estrita**: A lane auth é dona de `routes/`, `app/Models/User*`, `database/migrations/`, `app/Providers/AppServiceProvider.php`, `bootstrap/` e `FEAT-13`. Zero alterações em `composer.json`, `composer.lock`, `app/` ou `config/`. Nenhum pacote instalado hoje (Scramble permanece não instalado).
3. **Sem execução Docker**: Containers e banco de dados não foram iniciados (escopo restrito a ferramentas e documentação).
4. **Sem simulação de export falso**: O workflow `.github/workflows/openapi.yml` foi mantido byte a byte como um draft guardado com `name: OpenAPI Contract` que falha informativamente (`exit 1`) até o desbloqueio do Stage B.

## Decomposição de Responsabilidades e Arquitetura DAG (Resolução R1 & Remediações)

Para satisfazer os limites estruturais normativos da CSD (máximo 500 linhas físicas por arquivo de produção, máximo 15 funções/métodos declarados por arquivo/classe, máximo 150 linhas e 3 retornos por função, zero `else`/`elif`) e garantir um grafo de dependências verdadeiramente acíclico (DAG) com carregamento isolado dinâmico, o subsistema foi estruturado em quatro módulos coesos sob `scripts/`:

```
          [ contract_core.py ]  (foundation: 0 dependências)
              ^            ^
             /              \
[ contract_receipt.py ]   [ contract_cli.py ]  (satélites: dependem apenas de core)
             \              /
              v            v
      [ verify_contract_artifact.py ]  (fachada pública e entrypoint __main__)
```

1. **`scripts/contract_core.py` (Fundação e Núcleo de Validação de Proveniência)**:
   - **Responsabilidade**: Contém todas as 14 funções de validação fail-closed (`check_duplicate_keys`, `read_bounded_bytes`, `compute_file_sha256`, `validate_commit_sha`, `validate_sha256_digest`, `parse_utc_timestamp`, `validate_strict_int_id`, `validate_owner_repo`, `validate_run_url`, `validate_expiration`, `validate_receipt_structure`, `validate_receipt_identity`, `verify_artifact_file`, `verify_provenance`), limites nominais (`MAX_RECEIPT_BYTES = 64 KiB`, `MAX_ARTIFACT_BYTES = 10 MiB`), padrões regex e a classe `ProvenanceVerificationError`.
   - **Dependências**: Zero dependências de módulos irmãos.
   - **Métricas estruturais**: 380 linhas físicas (<= 500), 14 funções declaradas + 1 classe de erro (15 declarações <= 15), tamanho máx. de função 70 linhas (<= 150), máx. 1 retorno por função (<= 3), zero `else`/`elif`.
   - **Compatibilidade de interface**: Assinaturas históricas com > 7 parâmetros (`validate_receipt_identity` com 8, `verify_provenance` com 13) preservadas integralmente.

2. **`scripts/contract_receipt.py` (Construção de Receipt)**:
   - **Responsabilidade**: Construção do dicionário de receipt de proveniência do lado do produtor contendo os metadados do workflow run e do artefato publicado.
   - **Dependências**: Depende unicamente de `contract_core.py` (via importação direta ou carregamento ancorado no caminho sem mutação de `sys.path`). Não depende da fachada `verify_contract_artifact.py` nem de CLI.
   - **Métricas estruturais**: 79 linhas físicas (<= 500), 2 funções declaradas (`_load_core`, `build_receipt` <= 15), tamanho máx. de função 36 linhas (<= 150), máx. 3 retornos (<= 3), zero `else`/`elif`.
   - **Compatibilidade de interface**: Assinatura pública com 13 parâmetros e corpo 100% equivalente em AST ao baseline `33cbcab`.

3. **`scripts/contract_cli.py` (Parsing e Orquestração de CLI)**:
   - **Responsabilidade**: Análise de argumentos via `argparse`, orquestração da execução, formatação de saída e controle de códigos de saída de processo (0 = sucesso, 1 = falha de verificação fail-closed, 2 = erro de sintaxe/argumentos ou erro inesperado capturado em `except Exception:` com `FAIL CLOSED: Unexpected error:`).
   - **Dependências**: Depende unicamente de `contract_core.py`. Não depende da fachada `verify_contract_artifact.py`.
   - **Métricas estruturais**: 180 linhas físicas (<= 500), 4 funções declaradas (`_load_core`, `parse_args`, `run_cli`, `main` <= 15), tamanho máx. de função 84 linhas (<= 150), máx. 3 retornos (<= 3), zero `else`/`elif`.
   - **Compatibilidade de interface**: Flags CLI, argumentos, defaults e saídas 100% equivalentes em AST ao baseline `33cbcab`.

4. **`scripts/verify_contract_artifact.py` (Fachada Pública de Compatibilidade & Ponto de Entrada)**:
   - **Responsabilidade**: Ponto de entrada canônico e fachada pública estável. Reexporta todas as 36 constantes, funções, exceções e helpers dos módulos irmãos (`contract_core`, `contract_receipt`, `contract_cli`).
   - **Resolução de importação e isolamento**: Carrega os irmãos via mecanismo ancorado em `__file__`, sem poluir `sys.path`. Permite execução direta como script (`python3 scripts/verify_contract_artifact.py`), execução como módulo (`python3 -m scripts.verify_contract_artifact`), importação normal em pacotes Python e carregamento isolado via `importlib.util.spec_from_file_location` a partir de diretórios de trabalho externos arbitrários sem erros de `ModuleNotFoundError`.
   - **Eliminação de dupla execução sob `__main__`**: Como `contract_cli` e `contract_receipt` não importam `verify_contract_artifact`, a execução direta executa a fachada exatamente uma vez, preservando a identidade estrita de `ProvenanceVerificationError`.
   - **Métricas estruturais**: 156 linhas físicas (<= 500), 1 função declarada (`_load_sibling` <= 15), tamanho máx. de função 14 linhas (<= 150), máx. 3 retornos (<= 3), zero `else`/`elif`.

## Histórico das Condições S1–S9 (Implementadas e Verificadas)

1. **S1 — Workflow/artifact identity allowlist**: defaults validados (`expected_workflow_name="OpenAPI Contract"`, `expected_workflow_path=".github/workflows/openapi.yml"`, `expected_artifact_name="openapi-<fullsha>"`), igualdade estrita, rejeição de "latest", `expected_workflow_id` validado como inteiro positivo.
2. **S2 — Política de expiração explícita**: `--mode download-fresh` (enforce max_age default 90d via relógio UTC; futuro > 300s falha) vs `--mode verify-committed` (sem checagem de idade no CI consumidor).
3. **S3 — `run_url` estrita**: HTTPS obrigatório, host exato `github.com`, sem query/fragment, `run_id` idêntico na URL e no receipt contra confused-deputy.
4. **S4 — Rejeição de duplicatas JSON**: `object_pairs_hook=check_duplicate_keys` lançando em duplicatas.
5. **S5 — Validação estrita de IDs**: Rejeição de bool, float e string numérica; aceita unicamente inteiros estritos positivos.
6. **S6 — Limites nominais**: `MAX_RECEIPT_BYTES = 64 KiB`, `MAX_ARTIFACT_BYTES = 10 MiB` antes do parsing ou hash.
7. **S7 — Dual-digest separation**: `archive_sha256` (provider ZIP) vs `artifact_file_sha256` (arquivo OpenAPI extraído recomputado independentemente).
8. **S8 — Subtask Stage B sanitize**: Subtask `FEAT-11-STAGE-B-SANITIZE` registrada em `plan.md`.
9. **S9 — Pin exato `dedoc/scramble 0.13.47`**: 3 componentes estritos sem `>=` ou `^`.

## Condições e Limitações Remanescentes do Stage B (Aguardando Integração)

1. **`expected_workflow_id` confiável**: No Stage B e na orquestração de produção, o chamador deve sempre resolver o `workflow_id` confiável via `gh api repos/{owner}/{repo}/actions/workflows` selecionando por path antes do fetch e passá-lo ao verificador.
2. **Validação do archive ZIP real**: No Stage A, o verificador valida a sintaxe e o formato do `archive_sha256`, mas não descompacta ZIP real (nenhum artefato é baixado do Actions nesta fase). A revalidação completa do hash do ZIP contra a resposta da API do GitHub Actions será integrada no Stage B.
3. **Extração segura de arquivo (`FEAT-11-STAGE-B-SANITIZE`)**: Extração restrita a arquivos na allowlist, normalização canônica de caminhos, rejeição estrita de symlinks e hardlinks, limites nominais de tamanho total e contagem de arquivos.
4. **Mapeamento de campos da API do GitHub**: Validação contra o schema ao vivo dos endpoints `/actions/runs` e `/actions/artifacts` quando o workflow producer estiver gerando builds reais.
5. **Nome estável do artefato**: Padrão imutável `openapi-<full_commit_sha>` (nunca "latest").
6. **Instalação do Scramble**: Instalação de `dedoc/scramble 0.13.47` em `composer.json` estritamente bloqueada até que a lane auth congele a autenticação L1 (FEAT-13).
7. **Mapeamento de autenticação Sanctum SPA no Scramble**: No Stage B, o Scramble deverá ser configurado para emitir `securityScheme` tipo cookie (`starter-kit-api-session` HttpOnly) + token CSRF (`X-XSRF-TOKEN`), sobrescrevendo a emissão padrão incorreta de `Bearer`.
8. **Rota de cookie CSRF (`/sanctum/csrf-cookie`)**: Fornecida pelo Sanctum e excluída por default pelo filtro de vendor do Scramble; exigirá inclusão via transformer no Stage B.
9. **Exportação determinística**: Execução sob `LC_ALL=C` com verificação de idempotência 2x sucessiva antes da geração do digest.
10. **Autenticação de metadados sem attestation**: O receipt atesta proveniência de metadados do GitHub Actions; nenhuma assinatura digital / cosign / notary é reivindicada ou requerida.
