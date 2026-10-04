# Execução — FEAT-11

Fase: `verify` (Stage B implementado; FAIL histórico da revisão AGY sobre `35f6ae82` registrado; re-review independente fresca PASS sobre `227b75f` registrada em `verification.md`/`review.md`; pendente gate/revisão final do parent) · Branch de entrega: `feat/wave-3-openapi-prep` (worktree persistente `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`) → alvo `main` (remote `origin`), EXC-0001. Base main integrada: `f5dce81711315458e8c32cf61367d58b5206ac05`; primeiro baseline histórico da main: `1390009d37f6a3493b21be7d83dbd32ae355d579`.

## Resolução dos Cinco Blockers Confirmados

1. **Servidor OpenAPI root-relative `'/'` e paths reais completos `/api/v1/...` (Blocker 1)**:
   - Configuração de `config/scramble.php` atualizada com `'api_path' => ['include' => ['api/v1', 'api/v1/*']]`, instruindo o Scramble a preservar os prefixos completos nas rotas `/api/v1`, `/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile`.
   - Método `ensureServers` em `ContractDocumentTransformer` define `$openApi->servers = [Server::make('/')]`, garantindo que o servidor permaneça `'/'` root-relative e limpa servidores alternativos locais.
   - Endpoint `/sanctum/csrf-cookie` na raiz, válido e independente.
   - Independência de `APP_URL` e origem local comprovada: execução com `APP_URL=http://staging.internal:9999` e `http://unexpected-custom-origin.test:9999` gerou saída byte a byte idêntica.
   - Resolução de URLs de operação validada programaticamente no teste `OpenApiSnapshotTest`.

2. **Não declarar 429 em CSRF vendor não throttled (Blocker 2)**:
   - O endpoint `/sanctum/csrf-cookie` pertence ao grupo de middleware `web` e não possui throttling configurado; a declaração de resposta 429 foi removida em `ContractDocumentTransformer::injectCsrfCookieOverlay` (mantendo apenas 204).
   - As operações throttled da API (`/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile`) preservam a declaração de resposta 429 com o cabeçalho `Retry-After`.
   - Teste automatizado `OpenApiSnapshotTest` valida a presença de 429 nas rotas da API e sua ausência estrita na rota CSRF.

3. **CI de Breaking Changes contra Base Imutável (Blocker 3)**:
   - Implementado `scripts/contract_compat.py` utilizando a ferramenta fixada `oasdiff` versão `1.33.0`.
   - Comparação contra o commit baseline imutável da main: `1390009d37f6a3493b21be7d83dbd32ae355d579`.
   - Verificação estrita de bootstrap: o commit baseline é verificado no Git local; a ausência de `contracts/openapi.json` no commit é comprovada (código 128 com erro de objeto/caminho não existente). Qualquer erro arbitrário de Git, ferramenta, permissão ou rede falha fechado sem bootstrap.
   - Fixtures operacionais: o checker fixado é submetido a 5 fixtures negativas (remoção de rota, remoção de status 200, alteração de tipo de campo, campo obrigatório adicionado em requisição, campo obrigatório removido de resposta) comprovando que todas são rejeitadas com erro, e 1 fixture positiva (rota adicional e campo opcional) comprovando que é aceita.
   - Workflow `.github/workflows/quality.yml` atualizado com checkout completo (`fetch-depth: 0`), download do binário fixado `oasdiff 1.33.0` e execução de `contract_compat.py`.

4. **Remoção de trigger extra push main no workflow de publicação (Blocker 4)**:
   - Removido `push: branches: [main]` de `.github/workflows/openapi.yml`.
   - Publicação restrita exclusivamente ao trigger manual `workflow_dispatch` na branch `main`.

5. **Decomposição Coesa CSD15 de `ContractDocumentTransformer` (Blocker 5)**:
   - `ContractDocumentTransformer` possuía 17 métodos, violando o teto CSD15 (`<= 15` métodos por classe/arquivo de produção).
   - Extração coesa de responsabilidades:
     - `app/OpenApi/ContractResponseFactory.php`: 3 métodos (`messageResponse`, `rateLimitedResponse`, `errorDescription`), 56 linhas físicas, zero `else`/`elif`.
     - `app/OpenApi/ContractOperationOverlay.php`: 12 métodos (`__construct`, `apply`, `overlayOperation`, `applyLogin`, `applyLogout`, `applyProfile`, `ensureXsrfHeaderParam`, `ensureResponse`, `normalizePath`, `isLogin`, `isLogout`, `isProfile`), 134 linhas físicas, zero `else`/`elif`.
     - `app/OpenApi/ContractDocumentTransformer.php`: 8 métodos (`__construct`, `__invoke`, `ensureLogicalInfo`, `ensureServers`, `registerSessionSchemes`, `fixProfileSchema`, `profileSchema`, `injectCsrfCookieOverlay`), 153 linhas físicas, zero `else`/`elif`.
   - Todas as classes de produção atendem integralmente aos limites de `<= 15` métodos, `<= 500` linhas físicas, `<= 7` parâmetros, `<= 3` retornos e zero `else`/`elif`.

## Convenção de Idioma e Nomenclatura

- Variáveis, identificadores de código e tokens de máquina mantidos em en-US.
- Todas as mensagens humanas de status, erro, validação, requisição e resposta vertidas para pt-BR no código de produção pertencente à lane CONTRACT tocado (`scripts/contract_export.py`, `scripts/contract_extract.py`, `scripts/contract_fetch.py`, `scripts/contract_compat.py`, `ContractResponseFactory.php`, `ContractOperationOverlay.php`, `ContractDocumentTransformer.php`).
- Convenção devidamente registrada em `.context/project/conventions.md`.

## Reconciliação de Asserções e Execução de Testes

> Registro histórico da execução anterior do Stage B. Observações separadas, sem reconciliação causal.

- Discrepância histórica: registro de produtor anterior citava `96 passed, 621 assertions`, enquanto revisor citava `96 passed, 625 assertions` — fatos brutos registrados com sua fonte.
- Causa NÃO VERIFICADA: nenhuma explicação causal para a divergência foi comprovada; alegações causais anteriores (contagens 572 + 33 + 20 sobre `HttpAuthSmokeTest`/testes Stage B) foram removidas por especulativas.
- Observação histórica da execução local anterior: `96 passed (644 asserções)` em 34.37s (fonte: verificação da submissão anterior do Stage B).
- Observação histórica da suíte Python anterior: 87 unittests (59 Stage A + 28 Stage B), todos verdes. Contagem atual observada: 113 (7 CSD + 52 Stage A + 54 Stage B).
