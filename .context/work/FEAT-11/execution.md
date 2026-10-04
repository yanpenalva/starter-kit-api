# Execução — FEAT-11

Fase: `plan` (Stage A — CONTRACT prep pós-revisão Oracle e resolução R1) · Branch de entrega: `feat/wave-3-openapi-prep` (worktree persistente `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`) → alvo `main` (remote `origin`), EXC-0001. Base: `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` (PR #36 merge).

> **REGISTRO DE REVISÃO INDEPENDENTE:**
> - Oracle PASS WITH CONDITIONS em `883246c`: condições S1–S9 implementadas em `f910f66`.
> - Oracle PASS WITH CONDITIONS em `33cbcab55df28c4fa4cb4e8abc8b50cbae89c65b` (47 security/unit tests + 25 adversariais passaram, zero defeitos de lógica; blocker: **R1** — teto CSD de 500 linhas).
> - Revisão Independente PASS WITH CONDITIONS em `af10a423c0f74e73b4a8943ab8d2f35ef614d73c` (Relatório `66afdcbd-69b5-471e-af8f-32c072109b84`): R1 atendido; remediações arquiteturais implementadas em `78c3f57` (DAG acíclico, carregamento dinâmico sem `sys.path`, ausência de dupla execução sob `__main__`, exit 2 em erros inesperados).
> - **Última Revisão Independente Oracle em `78c3f57709e212b6f846f99276ae2c202f84c9bd` (Evidência Histórica Transmitida pelo Usuário)**:
>   - **Resultado**: FAIL exclusivamente pelo limite normativo CSD MUST `<= 7` parâmetros declarados (`verify_provenance`: 13, `validate_receipt_identity`: 8, `build_receipt`: 13).
>   - **Confirmações de Segurança**: Requisitos de segurança S1–S9, suítes de teste (57 unitários e 25 adversariais históricos), DAG/módulos e tetos de linha física foram confirmados íntegros e sem defeitos de lógica.
>   - **Retratação de Justificativa Prévia e Autorização Explícita**: A alegação anterior de que essas assinaturas constituíam "interfaces Python públicas externas imutáveis" foi formalmente retratada (os únicos consumidores eram o CLI interno, a fachada e os testes do repositório). O usuário autorizou explicitamente a alteração de assinatura da API Python interna via objetos de dados tipados congelados e a migração de todos os pontos de chamada e testes internos.
>   - **Invariantes Preservados**: Flags de CLI, defaults, argumentos, saídas de texto stdout/stderr, schema JSON do receipt/resultado e padrões de carregamento do script público permanecem 100% inalterados.
> - **Status da Entrega**: Corrigida para conformidade estrita CSD `<= 7` parâmetros; **PENDING NOVA REVISÃO INDEPENDENTE**; status permanece `active` / fase `plan` (sem autoaprovação).

## Condições de execução e fronteira de lane

1. **Execução exclusiva no worktree persistente**: Todas as operações são executadas com isolamento no diretório `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`. Nenhuma operação toca o checkout primário, worktrees wave2, frontend ou worktree da lane auth.
2. **Lane boundary estrita**: A lane auth é dona de `routes/`, `app/Models/User*`, `database/migrations/`, `app/Providers/AppServiceProvider.php`, `bootstrap/` e `FEAT-13`. Zero alterações em `composer.json`, `composer.lock`, `app/` ou `config/`. Nenhum pacote instalado hoje (Scramble permanece não instalado).
3. **Sem execução Docker**: Containers e banco de dados não foram iniciados (escopo restrito a ferramentas e documentação).
4. **Sem simulação de export falso**: O workflow `.github/workflows/openapi.yml` foi mantido byte a byte como um draft guardado com `name: OpenAPI Contract` que falha informativamente (`exit 1`) até o desbloqueio do Stage B.

## Decomposição de Responsabilidades, Arquitetura DAG e Objetos de Parâmetros

Para eliminar assinaturas com mais de 7 parâmetros declarados sem recorrer a artifícios métricos (`*args`, `**kwargs` ou wrappers artificiais), foram introduzidos dois objetos de dados tipados congelados (`@dataclass(frozen=True)`):

```
          [ contract_core.py ]  (foundation: VerificationPolicy, validators, error)
              ^            ^
             /              \
[ contract_receipt.py ]   [ contract_cli.py ]  (satélites: ReceiptRecord, CLI parsing)
             \              /
              v            v
      [ verify_contract_artifact.py ]  (fachada pública e entrypoint __main__)
```

1. **`scripts/contract_core.py` (Fundação e Núcleo de Validação)**:
   - **`@dataclass(frozen=True) VerificationPolicy`**: Agrupa exclusivamente expectativas de identidade confiável do repositório, workflow e commit (`expected_sha` obrigatório; defaults: `expected_branch="main"`, `expected_workflow_name="OpenAPI Contract"`, `expected_workflow_path=".github/workflows/openapi.yml"`, `expected_artifact_name=None`, `expected_workflow_id=None`, `allowed_events=DEFAULT_ALLOWED_EVENTS`, `owner=DEFAULT_OWNER`, `repo=DEFAULT_REPO`). Não inclui caminhos de entrada ou modos operacionais. Construtor gerado automaticamente pelo Python (sem declaração manual > 7).
   - **`validate_receipt_identity(receipt, policy)`**: Exatamente **2 parâmetros declarados** (reduzido de 8).
   - **`verify_provenance(artifact_path, receipt_path, policy, mode=MODE_VERIFY_COMMITTED, max_age_days=DEFAULT_MAX_AGE_DAYS)`**: Exatamente **5 parâmetros declarados** (reduzido de 13). Preserva rigorosamente a ordem de avaliação: leitura dos bytes do receipt antes da validação do SHA esperado, resolução de nome de artefato default (`openapi-<fullsha>`), validação de identidade, URL de execução, expiração e digest recomputado.
   - **Métricas estruturais**: 390 linhas físicas (<= 500), 14 funções declaradas + 2 classes (`ProvenanceVerificationError`, `VerificationPolicy`), 1 callback (`lambda` em `compute_file_sha256`), 0 métodos de classe, máx. 5 parâmetros declarados em funções (<= 7), tamanho máx. de função 65 linhas (<= 150), máx. 1 retorno por função (<= 3), zero `else`/`elif`.

2. **`scripts/contract_receipt.py` (Construção de Receipt)**:
   - **`@dataclass(frozen=True) ReceiptRecord`**: Objeto de dados puro com os 13 campos exatos do receipt de proveniência (`workflow_name`, `workflow_path`, `workflow_id`, `run_id`, `run_url`, `artifact_id`, `artifact_name`, `commit_sha`, `head_branch`, `event`, `created_at`, `archive_sha256`, `artifact_file_sha256`). Não possui `__post_init__` precoce para preservar a sequência e precedência exata de erros em `build_receipt`.
   - **`build_receipt(record)`**: Exatamente **1 parâmetro declarado** (reduzido de 13).
   - **Métricas estruturais**: 84 linhas físicas (<= 500), 2 funções declaradas (`_load_core`, `build_receipt`), 1 classe (`ReceiptRecord`), 0 callbacks, 0 métodos de classe, máx. 1 parâmetro declarado (<= 7), tamanho máx. de função 21 linhas (<= 150), máx. 3 retornos (<= 3), zero `else`/`elif`.

3. **`scripts/contract_cli.py` (Parsing e Orquestração de CLI)**:
   - **Responsabilidade**: Parsing de argumentos via `argparse` com flags, defaults e mensagens de ajuda estritamente preservadas. Instancia `VerificationPolicy` como mero agrupamento e invoca `verify_provenance` com 5 parâmetros. Tratamento e códigos de saída (0, 1, 2) inalterados.
   - **Métricas estruturais**: 185 linhas físicas (<= 500), 4 funções declaradas (`_load_core`, `parse_args`, `run_cli`, `main`), 0 classes, 0 callbacks, 0 métodos de classe, máx. 1 parâmetro declarado (<= 7), tamanho máx. de função 84 linhas (<= 150), máx. 3 retornos (<= 3), zero `else`/`elif`.

4. **`scripts/verify_contract_artifact.py` (Fachada Pública & Entrypoint)**:
   - **Responsabilidade**: Fachada pública estável. Reexporta `VerificationPolicy`, `ReceiptRecord` e todas as constantes, funções e helpers dos módulos irmãos sem wrappers de compatibilidade com > 7 parâmetros.
   - **Métricas estruturais**: 160 linhas físicas (<= 500), 1 função declarada (`_load_sibling`), 0 classes, 0 callbacks, 0 métodos de classe, máx. 1 parâmetro declarado (<= 7), tamanho máx. de função 14 linhas (<= 150), máx. 3 retornos (<= 3), zero `else`/`elif`.

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
