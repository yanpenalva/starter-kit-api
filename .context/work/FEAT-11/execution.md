# Execução — FEAT-11

Fase: `plan` (Stage A — CONTRACT prep pós-revisão Oracle) · Branch de entrega: `feat/wave-3-openapi-prep` (worktree persistente `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`) → alvo `main` (remote `origin`), EXC-0001. Base: `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` (PR #36 merge).

> **REGISTRO DE REVISÃO INDEPENDENTE:**
> Oracle PASS WITH CONDITIONS em `883246c`; condições S1–S9 implementadas neste commit; re-review pendente; nenhuma autoaprovação.
> A revisão independente foi realizada pelo oracle; o implementador apenas executa e evidencia as correções necessárias.

## Condições de execução e fronteira de lane

1. **Execução exclusiva no worktree persistente**: Todas as operações são executadas com isolamento no diretório `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`. Nenhuma operação toca o checkout primário, worktrees wave2, frontend ou worktree da lane auth.
2. **Lane boundary estrita**: A lane auth é dona de `routes/`, `app/Models/User*`, `database/migrations/`, `app/Providers/AppServiceProvider.php`, `bootstrap/` e `FEAT-13`. Zero alterações em `composer.json`, `composer.lock`, `app/` ou `config/`. Nenhum pacote foi instalado hoje.
3. **Sem execução Docker**: Containers e banco de dados não foram iniciados (escopo restrito a ferramentas e documentação).
4. **Sem simulação de export falso**: O workflow `.github/workflows/openapi.yml` foi mantido como um draft guardado com `name: OpenAPI Contract` que falha informativamente (`exit 1`) até o desbloqueio do Stage B.

## Implementação das Condições S1–S9 (Stage A)

1. **S1 — Workflow/artifact identity allowlist (`scripts/verify_contract_artifact.py`)**:
   - Argumentos esperados implementados com defaults rigorosos: `expected_workflow_name` (`OpenAPI Contract`), `expected_workflow_path` (`.github/workflows/openapi.yml`), `expected_artifact_name` (`openapi-<expected_sha>`).
   - Comparação por igualdade estrita: `workflow_name`, `workflow_path` e `artifact_name` devem ser exatamente iguais aos esperados; o nome do artefato jamais pode ser "latest".
   - `workflow_id` validado como inteiro estrito associado ao workflow confiável resolvido via `gh` antes do fetch.
   - `.github/workflows/openapi.yml` inclui `name: OpenAPI Contract` e comentário cobrindo o pin exato de Scramble (S9), guard exit-1 e proibição de falso sucesso.
2. **S2 — Política de expiração explícita (fetch-time enforcement)**:
   - Dois modos implementados: `--mode download-fresh` (aplica `max_age` default 90 dias com relógio confiável UTC; criados no futuro além de 300s de tolerância falham) e `--mode verify-committed` (sem checagem de idade, evitando expirações acidentais no CI do consumidor).
   - Ausente ou expirado falha no primeiro fetch antes de qualquer commit no consumidor.
3. **S3 — `run_url` estrita**:
   - Exige formato estrito `https://github.com/{owner}/{repo}/actions/runs/{run_id}`: HTTPS obrigatório, host `github.com` exato, sem query params e sem fragment.
   - O `run_id` na URL deve ser estritamente igual ao `run_id` do receipt (proteção contra confused-deputy).
   - `owner` e `repo` validados com regex `^[A-Za-z0-9_.-]+$` sem `..`.
4. **S4 — Rejeição de chaves JSON duplicadas**:
   - `json.loads` configurado com `object_pairs_hook=check_duplicate_keys`, lançando `ProvenanceVerificationError` caso qualquer chave apareça duplicada no receipt.
5. **S5 — Validação estrita de inteiros para IDs**:
   - `run_id`, `artifact_id` e `workflow_id` aceitam somente JSON number inteiro positivo.
   - Rejeição explícita de `bool` (`isinstance(x, bool)` verificado antes de `int`), `float` e string numérica (`"123"`).
6. **S6 — Limites nominais de leitura e memória**:
   - Constantes nominais documentadas: `MAX_RECEIPT_BYTES = 64 KiB` e `MAX_ARTIFACT_BYTES = 10 MiB`.
   - Leitura limitada via `read_bounded_bytes` verificando tamanho antes do parsing e truncamento de stream para mitigar exaustão de memória.
7. **S7 — Restauração do baseline e separação dual de digests**:
   - Linhas históricas restauradas em `.context/project/api.md` ("Record per delivery:" e "Recovery is limited...").
   - Separação mandatória no receipt entre `archive_sha256` (digest do ZIP do provider) e `artifact_file_sha256` (hash do arquivo OpenAPI extraído). Proibido copiar o hash do ZIP como hash do arquivo. O verificador recomputa o digest do arquivo de contrato independentemente.
8. **S8 — Subtask Stage B: Extração e sanitização do archive**:
   - Subtask `FEAT-11-STAGE-B-SANITIZE` adicionada em `.context/work/FEAT-11/plan.md` definindo que a futura descompactação do ZIP rejeitará symlinks/hardlinks, aplicará allowlist de arquivos e limites de tamanho/contagem. Nenhum ZIP é descompactado no Stage A.
9. **S9 — Pin exato `dedoc/scramble 0.13.47`**:
   - Versão corrigida para pin exato de 3 componentes (`dedoc/scramble 0.13.47`), sem `>=` ou `^`, em todos os documentos e comentários. Instalação estritamente bloqueada até o Stage B.

## Suposições e integração futura — Stage B (assumptions/needs-integration)

1. **Versões e compatibilidade do framework**:
   - Laravel 13.34.0 sob PHP 8.5.11 (ambiente de runtime).
   - Scramble (`dedoc/scramble 0.13.47` pin exato) suporta PHP 8.5 / Laravel 13. A instalação só deve ocorrer no Stage B após o congelamento da autenticação L1 (FEAT-13).
2. **Mapeamento de autenticação Sanctum SPA no Scramble**:
   - A documentação automática do Scramble para Sanctum mapeia por padrão autenticação do tipo `Bearer` (token de acesso pessoal), o que é **inadequado e incorreto** para a arquitetura aprovada deste projeto (ADR 0002 / ADR 0003).
   - No Stage B, será obrigatório configurar manualmente o `securityScheme` tipo cookie (`starter-kit-api-session`, HttpOnly, stateful) associado à proteção CSRF (`X-XSRF-TOKEN`), sobrescrevendo a inferência padrão do Scramble.
3. **Tratamento da rota de cookie CSRF**:
   - A rota `/sanctum/csrf-cookie` é fornecida pelo pacote Laravel/Sanctum e é excluída por padrão pelo filtro de vendor do Scramble.
   - No Stage B, será necessário registrar um transformer ou rota explícita para documentar formalmente o endpoint de CSRF no contrato OpenAPI publicado.
4. **Exportação determinística**:
   - No Stage B, o pipeline de exportação CLI deve ser executado sob `LC_ALL=C` e realizar a exportação 2x de forma idempotente, verificando que duas execuções sucessivas produzem bytes idênticos antes de calcular o digest SHA256 do artefato publicado.
5. **Autenticação de metadados vs Attestation criptográfica**:
   - O receipt de proveniência autentica os metadados do workflow run e do artefato via API do GitHub Actions; nenhuma assinatura digital (cosign/notary) é alegada ou necessária nesta fase.
6. **Consumo sem segredos no Frontend**:
   - O repositório frontend consumirá os artefatos de contrato (spec OpenAPI, tipos TypeScript e receipt) diretamente através de commit no repositório gerenciado pelo orquestrador local `gh`, preservando builds de PR no CI sem necessidade de PATs ou credenciais cruzadas.

