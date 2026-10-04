# Plan — FEAT-11 (API #11)

Registro de entrega: branch `feat/wave-3-openapi-prep` → alvo `main`, remote `origin`, EXC-0001.
Worktree persistente exclusivo: `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| FEAT-11-STAGE-A-PROVENANCE | yanpenalva | none | scripts/verify_contract_artifact.py fail-closed implementation, unittests in tests/tools/ passing | 1 | core,project,testing |
| FEAT-11-STAGE-A-WORKFLOW | yanpenalva | FEAT-11-STAGE-A-PROVENANCE | .github/workflows/openapi.yml draft guarded workflow rejecting premature execution | 1 | core,project,architecture |
| FEAT-11-STAGE-A-DOCS | yanpenalva | FEAT-11-STAGE-A-WORKFLOW | Updated .context/project/api.md and stack.md reflecting private gh transport decision | 2 | core,project,security |
| FEAT-11-STAGE-A-VERIFY | yanpenalva | FEAT-11-STAGE-A-DOCS | Execution of all 4 CSD gates: validate_context strict/examples, unittest discover, csd doctor | 2 | core,project,testing |
| FEAT-11-STAGE-B-DISPATCH | yanpenalva | FEAT-11-STAGE-A-VERIFY | Stage B contract export unlocked after L1 auth freeze and Scramble installation | 3 | core,project,architecture,testing |
| FEAT-11-STAGE-B-SANITIZE | yanpenalva | FEAT-11-STAGE-B-DISPATCH | Extract archive sanitization: extraction without symlinks, expected files allowlist, count and size limits | 3 | core,architecture,security |

## Passos detalhados

1. **Script de proveniência (`scripts/verify_contract_artifact.py`)**:
   - Construir validação de SHA exato de 40 caracteres hexadecimais (rejeição de `latest` e tags).
   - Validar evento de trigger via allowlist (`workflow_dispatch`, `release`, `workflow_run`).
   - Validar estritamente identidade do workflow (`workflow_name`, `workflow_path`, `artifact_name`, e `workflow_id` inteiro).
   - Validar estritamente `run_url` HTTPS sem query/fragment com run_id correspondente.
   - Rejeitar chaves JSON duplicadas e tipos não estritos de ID (rejeitar bool, float, string numérica).
   - Aplicar bounds nominais (64 KiB para receipt, 10 MiB para artefato) antes de leitura/parse/hash.
   - Separar digest do archive (`archive_sha256`) e hash do arquivo extraído (`artifact_file_sha256`).
   - Implementar modos explícitos `download-fresh` (com relógio UTC e max_age de 90 dias) e `verify-committed` (sem expiração aleatória em CI).
   - Enforçar regras de qualidade de código CSD (zero `else`/`elif`, no máximo 3 retornos por função).
   - Integrar requisitos de exportação determinística para o Stage B (`LC_ALL=C` e 2x export idempotente).

2. **Suite de testes (`tests/tools/test_verify_contract_artifact.py`)**:
   - Desenvolver testes dedicados com `unittest` sob `tests/tools/`.
   - Incluir casos de teste negativos para todos os modos de falha (tamper, metadata inválida, eventos não permitidos, branch divergente, IDs não inteiros, bounds excedidos, duplicata de chaves JSON, created_at futuro em download-fresh).
   - Garantir descoberta recursiva com `python3 -m unittest discover -s tests` preservando os testes existentes.

3. **Workflow guardado (`.github/workflows/openapi.yml`)**:
   - Definir trigger restrito `workflow_dispatch` com input de justificativa.
   - Definir permissões de leitura mínima (`contents: read`) e ausência de segredos.
   - Implementar falha com mensagem clara indicando o bloqueio do Stage B até o congelamento da autenticação L1.
   - Incluir comentário com os 3 pontos: pin exato de `dedoc/scramble 0.13.47`, guard exit-1 e proibição de sucesso fake.

4. **Documentação de contexto**:
   - Atualizar `.context/project/api.md` registrando o transporte local `gh`, separação de digests archive/file, modos de expiração e ausência de credenciais no consumidor.
   - Atualizar `.context/project/stack.md` com o version pin exato (`dedoc/scramble 0.13.47`).

5. **Subtask Stage B — Extração e sanitização segura de arquivo (futuro — documentado, não implementado agora)**:
   - No Stage B, o processo de descompactação do artefato baixado aplicará salvaguardas estritas: rejeição de symlinks / hardlinks, allowlist de caminhos e nomes de arquivos esperados, verificação de caminhos canônicos contra path traversal, e limites estritos de contagem e tamanho descompactado. Nenhum download de ZIP é implementado no Stage A.

6. **Verificação de qualidade e gates**:
   - Executar os quatro gates definidos no CSD (validador estrito, exemplos, unittests e csd doctor).

## Limites de propriedade da lane

- **Nosso escopo (Stage A)**: `scripts/verify_contract_artifact.py`, `tests/tools/`, `.github/workflows/openapi.yml`, `.context/work/FEAT-11/`, `.context/project/api.md`, `.context/project/stack.md`.
- **Intocável (lane auth / FEAT-13)**: `app/`, `config/`, `routes/`, `database/`, `composer.json`, `composer.lock`.

