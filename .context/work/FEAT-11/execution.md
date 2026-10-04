# Execução — FEAT-11

Fase: `plan` (Stage A — CONTRACT prep pós-revisão Oracle e resolução R1) · Branch de entrega: `feat/wave-3-openapi-prep` (worktree persistente `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`) → alvo `main` (remote `origin`), EXC-0001. Base: `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` (PR #36 merge).

> **REGISTRO DE REVISÃO INDEPENDENTE:**
> - Oracle PASS WITH CONDITIONS em `883246c`: condições S1–S9 implementadas em `f910f66`.
> - Oracle PASS WITH CONDITIONS em `33cbcab55df28c4fa4cb4e8abc8b50cbae89c65b` (47 security/unit tests + 25 adversariais passaram, zero defeitos de lógica; único blocker remanescente: **R1** — `scripts/verify_contract_artifact.py` possuía 542 linhas físicas > teto CSD de 500 linhas).
> - **R1 IMPLEMENTADO** neste commit via decomposição de responsabilidade coesa em módulos irmãos nomeados (`scripts/contract_receipt.py`, `scripts/contract_cli.py` e `scripts/verify_contract_artifact.py`).
> - Re-review independente pendente; nenhuma autoaprovação; status permanece `active` / fase `plan`. O implementador apenas executa e evidencia as correções necessárias.

## Condições de execução e fronteira de lane

1. **Execução exclusiva no worktree persistente**: Todas as operações são executadas com isolamento no diretório `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`. Nenhuma operação toca o checkout primário, worktrees wave2, frontend ou worktree da lane auth.
2. **Lane boundary estrita**: A lane auth é dona de `routes/`, `app/Models/User*`, `database/migrations/`, `app/Providers/AppServiceProvider.php`, `bootstrap/` e `FEAT-13`. Zero alterações em `composer.json`, `composer.lock`, `app/` ou `config/`. Nenhum pacote instalado hoje (Scramble permanece não instalado).
3. **Sem execução Docker**: Containers e banco de dados não foram iniciados (escopo restrito a ferramentas e documentação).
4. **Sem simulação de export falso**: O workflow `.github/workflows/openapi.yml` foi mantido byte a byte como um draft guardado com `name: OpenAPI Contract` que falha informativamente (`exit 1`) até o desbloqueio do Stage B.

## Decomposição de Responsabilidades e Resolução de R1

Para satisfazer os limites estruturais normativos da CSD (máximo 500 linhas físicas por arquivo de produção, máximo 15 funções/métodos declarados por arquivo/classe, máximo 150 linhas e 3 retornos por função, zero `else`/`elif`), o verificador foi decomposto em três módulos irmãos coesos sob `scripts/`:

1. **`scripts/contract_receipt.py` (Construção de Receipt)**:
   - **Responsabilidade**: Construção do dicionário de receipt de proveniência do lado do produtor contendo os metadados do workflow run e do artefato publicado.
   - **Métricas estruturais**: 66 linhas físicas (<= 500), 1 função declarada (`build_receipt` <= 15), 51 linhas (<= 150), 1 retorno (<= 3), zero `else`/`elif`.
   - **Compatibilidade de interface**: Assinatura histórica mantida com seus 13 parâmetros explícitos.
   - **Dependências**: Importa helpers de validação sob demanda, sem dependência de CLI ou ciclos de importação.

2. **`scripts/contract_cli.py` (Parsing e Orquestração de CLI)**:
   - **Responsabilidade**: Análise de argumentos via `argparse`, orquestração da execução, formatação de saída e controle de códigos de saída de processo (0 = sucesso, 1 = falha de verificação / fail-closed, 2 = erro de sintaxe/argumentos).
   - **Métricas estruturais**: 183 linhas físicas (<= 500), 3 funções declaradas (`parse_args`, `run_cli`, `main` <= 15), tamanho máximo de função 109 linhas (<= 150), máximo 3 retornos em `run_cli` (<= 3), zero `else`/`elif`.
   - **Dependências**: Invoca `verify_provenance` e consome defaults e tipos de erro sem ciclos.

3. **`scripts/verify_contract_artifact.py` (Core de Validação de Proveniência & Ponto de Entrada)**:
   - **Responsabilidade**: Núcleo substantivo de regras de proveniência fail-closed (verificação de SHA, digests dual archive/file, allowlists de identidade de workflow/artefato, limites nominais de tamanho, janelas de expiração) e fachada de compatibilidade pública para reexportação e execução de script.
   - **Métricas estruturais**: 433 linhas físicas (<= 500), 14 funções declaradas (<= 15), 1 classe de erro declarada (`ProvenanceVerificationError`, 0 métodos), tamanho máximo de função 73 linhas (`verify_provenance` <= 150), retornos por função todos <= 1 (<= 3), zero `else`/`elif`.
   - **Resolução de importação e ausência de ciclos**: Arquitetura DAG sem ciclos; reexporta a API pública completa (`build_receipt`, `parse_args`, `run_cli`, `main`, constantes nominais e exceção) suportando invocação direta de script (`python3 scripts/verify_contract_artifact.py`), execução de módulo (`python3 -m scripts.verify_contract_artifact`), importação normal de pacote e execução a partir de CWDs externos arbitrários sem monkeypatch de `sys.path`.

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
