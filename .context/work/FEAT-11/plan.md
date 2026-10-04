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

## Passos detalhados

1. **Script de proveniência (`scripts/verify_contract_artifact.py`)**:
   - Construir validação de SHA exato de 40 caracteres hexadecimais (rejeição de `latest` e tags).
   - Validar evento de trigger via allowlist (`workflow_dispatch`, `release`, `workflow_run`).
   - Recomputar digest SHA-256 do artefato a partir do stream de bytes e validar receipt.
   - Enforçar regras de qualidade de código CSD (zero `else`/`elif`, no máximo 3 retornos por função).
   - Integrar requisitos de exportação determinística para o Stage B (`LC_ALL=C` e 2x export idempotente).

2. **Suite de testes (`tests/tools/test_verify_contract_artifact.py`)**:
   - Desenvolver testes dedicados com `unittest` sob `tests/tools/`.
   - Incluir casos de teste negativos para todos os modos de falha (tamper, metadata inválida, eventos não permitidos, branch divergente).
   - Garantir descoberta recursiva com `python3 -m unittest discover -s tests` preservando os testes existentes.

3. **Workflow guardado (`.github/workflows/openapi.yml`)**:
   - Definir trigger restrito `workflow_dispatch` com input de justificativa.
   - Definir permissões de leitura mínima (`contents: read`) e ausência de segredos.
   - Implementar falha com mensagem clara indicando o bloqueio do Stage B até o congelamento da autenticação L1.

4. **Documentação de contexto**:
   - Atualizar `.context/project/api.md` registrando o transporte local `gh` e a ausência de credenciais no consumidor.
   - Atualizar `.context/project/stack.md` com o version pin pretendido (`dedoc/scramble >=0.13.4`).

5. **Verificação de qualidade e gates**:
   - Executar os quatro gates definidos no CSD (validador estrito, exemplos, unittests e csd doctor).

## Limites de propriedade da lane

- **Nosso escopo (Stage A)**: `scripts/verify_contract_artifact.py`, `tests/tools/`, `.github/workflows/openapi.yml`, `.context/work/FEAT-11/`, `.context/project/api.md`, `.context/project/stack.md`.
- **Intocável (lane auth / FEAT-13)**: `app/`, `config/`, `routes/`, `database/`, `composer.json`, `composer.lock`.
