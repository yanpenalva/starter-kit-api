# Execução — FEAT-11

Fase: `plan` (Stage A — CONTRACT prep concluído) · Branch de entrega: `feat/wave-3-openapi-prep` (worktree persistente `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`) → alvo `main` (remote `origin`), EXC-0001. Base: `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` (PR #36 merge).

## Condições de execução e fronteira de lane

1. **Execução exclusiva no worktree persistente**: Todas as operações são executadas com isolamento no diretório `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`. Nenhuma operação toca o checkout primário, worktrees wave2, frontend ou worktree da lane auth.
2. **Lane boundary estrita**: A lane auth é dona de `routes/`, `app/Models/User*`, `database/migrations/`, `app/Providers/AppServiceProvider.php`, `bootstrap/` e `FEAT-13`. Zero alterações em `composer.json`, `composer.lock`, `app/` ou `config/`. Nenhum pacote foi instalado.
3. **Sem execução Docker**: Containers e banco de dados não foram iniciados (escopo restrito a ferramentas e documentação).
4. **Sem simulação de export falso**: O workflow `.github/workflows/openapi.yml` foi criado como um draft guardado que falha informativamente até o desbloqueio do Stage B.

## Itens implementados no Stage A

1. **Script de proveniência fail-closed (`scripts/verify_contract_artifact.py`)**:
   - Valida que o artefato é derivado de `main` com commit SHA exato de 40 caracteres hexadecimais (rejeita `latest`, tags ou referências parciais).
   - Valida evento de workflow em allowlist estrita (`workflow_dispatch`, `release`, `workflow_run`).
   - Recomputa digest SHA256 do arquivo e compara com o valor no receipt.
   - Valida todos os campos obrigatórios do receipt de metadados (`workflow_name`, `workflow_id`, `run_id`, `run_url`, `artifact_id`, `artifact_name`, `commit_sha`, `head_branch`, `event`, `created_at`, `artifact_sha256`).
   - Conformidade com a política de qualidade de código: ausência de ramificações proibidas (`else`/`elif`), máximo de 3 retornos por função, funções concisas com tipagem estrita.

2. **Workflow draft guardado (`.github/workflows/openapi.yml`)**:
   - Trigger restrito `workflow_dispatch` com entrada de justificativa obrigatória.
   - Permissões mínimas `contents: read` e sem novos segredos.
   - Step de guarda que falha com status não-zero e mensagem clara apontando que o Stage B aguarda o congelamento da autenticação L1.

3. **Suite de testes dedicados (`tests/tools/test_verify_contract_artifact.py`)**:
   - 19 testes automatizados com `unittest` cobrindo cenários positivos e modos adversariais de falha.
   - Localização sob `tests/tools/` com descoberta automática sem quebrar a suite de testes CSD (`tests/test_context_validation.py`). Total de 26 testes Python executados com 100% de sucesso.
   - Sem chamadas de API do GitHub, sem mutação de repositório e sem segredos.

4. **Alinhamento documental de contexto**:
   - Atualizados `.context/project/api.md` e `.context/project/stack.md` para documentar o transporte privado via orquestrador local com `gh` e o version pin pretendido do Scramble (`dedoc/scramble >=0.13.4`).

## Suposições e integração futura — Stage B (assumptions/needs-integration)

1. **Versões e compatibilidade do framework**:
   - Laravel 13.34.0 sob PHP 8.5.11 (ambiente de runtime).
   - Scramble (`dedoc/scramble >=0.13.4`) suporta ranges e PHP 8.5 / Laravel 13. A instalação só deve ocorrer no Stage B após o congelamento da autenticação L1 (FEAT-13).
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
