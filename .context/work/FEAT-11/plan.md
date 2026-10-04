# Plan — FEAT-11 (API #11)

Registro de entrega: branch `feat/wave-3-openapi-prep` → alvo `main`, remote `origin`, EXC-0001.
Worktree persistente exclusivo: `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| FEAT-11-STAGE-A-PROVENANCE | yanpenalva | none | scripts/verify_contract_artifact.py fail-closed implementation, unittests in tests/tools/ passing | 1 | core,project,testing |
| FEAT-11-STAGE-A-WORKFLOW | yanpenalva | FEAT-11-STAGE-A-PROVENANCE | .github/workflows/openapi.yml draft guarded workflow rejecting premature execution | 2 | core,project,architecture |
| FEAT-11-STAGE-A-DOCS | yanpenalva | FEAT-11-STAGE-A-WORKFLOW | Updated .context/project/api.md and stack.md reflecting private gh transport decision | 3 | core,project,security |
| FEAT-11-STAGE-A-VERIFY | yanpenalva | FEAT-11-STAGE-A-DOCS | Execution of all 4 CSD gates: validate_context strict/examples, unittest discover, csd doctor | 4 | core,project,testing |
| FEAT-11-STAGE-B-DISPATCH | yanpenalva | FEAT-11-STAGE-A-VERIFY | Scramble 0.13.47 export determinístico (2x, LC_ALL=C), server '/', paths completos '/api/v1/...', CSRF sem 429 | 5 | core,project,architecture,testing |
| FEAT-11-STAGE-B-DECOMPOSITION | yanpenalva | FEAT-11-STAGE-B-DISPATCH | Decomposição CSD15: ContractDocumentTransformer <=15 métodos, ContractOperationOverlay, ContractResponseFactory | 6 | core,architecture |
| FEAT-11-STAGE-B-COMPATIBILITY | yanpenalva | FEAT-11-STAGE-B-DECOMPOSITION | scripts/contract_compat.py com oasdiff 1.33.0 pinned, fixtures operacionais e bootstrap fail-closed | 7 | core,testing |
| FEAT-11-STAGE-B-PUBLICATION-TRIGGER | yanpenalva | FEAT-11-STAGE-B-COMPATIBILITY | openapi.yml estrito a workflow_dispatch em main (remoção de push trigger) | 8 | core,security |

## Passos detalhados do Stage B

1. **Servidor OpenAPI e paths completos (Blocker 1)**:
   - Configuração de `config/scramble.php` com `api_path => ['include' => ['api/v1', 'api/v1/*']]` para preservar paths completos (`/api/v1`, `/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile`).
   - Servidor fixado em `'/'` root-relative em `ContractDocumentTransformer::ensureServers`, eliminando dependência de `APP_URL` ou localhost:8080.
   - Endpoint `/sanctum/csrf-cookie` na raiz, válido e independente.
   - Teste automatizado validando independência de `APP_URL` e resolução de URL de operações contra rotas registradas no Laravel.

2. **Remoção de 429 na rota CSRF (Blocker 2)**:
   - Rota vendor `/sanctum/csrf-cookie` usa middleware `web` apenas e não possui throttling: removida declaração de 429 (mantendo apenas 204).
   - Rotas da API (`/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile`) preservam resposta 429 com cabeçalho `Retry-After`.
   - Testes assertam presença de 429 nas rotas da API e ausência estrita na rota CSRF.

3. **Verificação de compatibilidade contra base imutável (Blocker 3)**:
   - Ferramenta fixada: `oasdiff` versão 1.33.0 (`scripts/contract_compat.py` + `scripts/install_oasdiff.py` com digests do asset e do binário fixados).
   - Baseline resolvido dinamicamente do payload oficial do evento (`pull_request.base.sha`; `before` em push restrito a `refs/heads/main`) via `scripts/resolve_contract_baseline.py`; primeiro baseline histórico da main: `1390009d37f6a3493b21be7d83dbd32ae355d579`.
   - Condição de bootstrap estrita e comprovada: commit existe e caminho `contracts/openapi.json` está comprovadamente ausente no baseline (tri-state via `git ls-tree`); qualquer outro erro Git falha fechado.
   - Suíte de fixtures operacionais comprovando que o checker detecta e rejeita quebras (path removido, status removido, tipo alterado, campo obrigatório adicionado em requisição, campo obrigatório removido de resposta) e aceita mudanças compatíveis.
   - Integração em `.github/workflows/quality.yml` com checkout completo (`fetch-depth: 0`) e instalação do binário fixado.

4. **Trigger estrito do workflow de publicação (Blocker 4)**:
   - Remoção do trigger extra `push: branches: [main]` de `.github/workflows/openapi.yml`.
   - Publicação restrita exclusivamente a `workflow_dispatch` na branch `main`.

5. **Decomposição estrutural CSD15 (Blocker 5)**:
   - `ContractResponseFactory`: 3 métodos (construção de respostas JSON e mensagens).
   - `ContractOperationOverlay`: 12 métodos (associação de security schemes, XSRF headers e respostas de erro a operações).
   - `ContractDocumentTransformer`: 8 métodos (orquestração de info, servidores, esquemas, componentes e rota CSRF).
   - Todas as classes atendem estritamente a `<= 15` métodos, `<= 500` linhas, `<= 7` parâmetros, `<= 3` retornos e zero `else`/`elif`.
