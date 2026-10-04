# Spec — FEAT-11 (API #11)

## Objetivo

Implementar a geração e exportação determinística do contrato OpenAPI 3.1 com Scramble 0.13.47 a partir de endpoints reais de autenticação SPA (Stage B), prover verificação de compatibilidade e breaking changes contra a base imutável da main, e assegurar total conformidade com os invariantes normativos e de qualidade de código (CSD15).

## Escopo Stage A (CONTRACT prep — Aprovado em `edbbe2f`)

- Infraestrutura de proveniência de artefato fail-closed (`scripts/verify_contract_artifact.py`, `scripts/contract_core.py`, `scripts/contract_receipt.py`, `scripts/contract_cli.py`).
- 59 testes unitários (`test_verify_contract_artifact.py`) e 25 testes adversariais históricos.
- Assinaturas conformes com `<= 7` parâmetros declarados via objetos congelados tipados (`VerificationPolicy`, `ReceiptRecord`).

## Escopo Stage B (Publicação Real e Fechamento dos Cinco Blockers)

1. **Servidor OpenAPI e Paths Completos**:
   - Servidor fixado em `'/'` root-relative em `ContractDocumentTransformer::ensureServers`, independente de `APP_URL` ou origem local.
   - Rotas documentadas com paths reais completos: `/api/v1`, `/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile`.
   - Rota `/sanctum/csrf-cookie` na raiz, válida e acessível.
   - Resolução de URLs de operações comprovada via testes automatizados.

2. **Remoção de 429 na Rota CSRF**:
   - Rota vendor `/sanctum/csrf-cookie` (grupo `web`, sem throttling) declara apenas resposta 204 (sem 429).
   - Rotas throttled da API preservam resposta 429 com cabeçalho `Retry-After`.

3. **Verificação de Breaking Changes contra Base Imutável**:
   - Ferramenta fixada: `oasdiff` versão 1.33.0 (`scripts/contract_compat.py`).
   - Commit baseline imutável da main: `1390009d37f6a3493b21be7d83dbd32ae355d579`.
   - Bootstrap estrito: acionado somente com commit baseline existente e caminho `contracts/openapi.json` comprovadamente ausente; falha fechada para erros de acesso/git/tool.
   - Fixtures operacionais: 5 negativas (remoção de rota, remoção de status de sucesso, alteração de tipo, campo obrigatório adicionado na requisição, propriedade obrigatória removida da resposta) e 1 positiva (mudança compatível) comprovam checker operacional.

4. **Workflow de Publicação com Disparo Estrito**:
   - Workflow `.github/workflows/openapi.yml` restrito a `workflow_dispatch` na branch `main`.
   - Remoção do trigger extra `push: branches: [main]`.

5. **Decomposição Coesa CSD15**:
   - `ContractResponseFactory` (3 métodos): construção de respostas JSON e descrições de erro.
   - `ContractOperationOverlay` (12 métodos): overlay de segurança, XSRF headers e respostas de erro.
   - `ContractDocumentTransformer` (8 métodos): orquestração geral do documento.
   - Todas as classes atendem estritamente a `<= 15` métodos declarados, `<= 500` linhas, `<= 7` parâmetros, `<= 3` retornos e zero `else`/`elif`.

## Convenção de Idioma

- Variáveis, identificadores e tokens de máquina em en-US.
- Todas as mensagens humanas de status, erro, validação, requisição e resposta em pt-BR no código de produção pertencente à lane CONTRACT tocado.

## Critérios de Aceite

- [x] Blocker 1: Servidor OpenAPI root-relative `'/'` e paths completos `/api/v1/...`; `/sanctum/csrf-cookie` na raiz; independência comprovada de `APP_URL`.
- [x] Blocker 2: Ausência de 429 em CSRF vendor não throttled; preservação de 429 nas operações de API throttled.
- [x] Blocker 3: Comparação de breaking changes contra base imutável `1390009` com `oasdiff 1.33.0` fixado; fixtures negativas rejeitadas e positiva aceita; bootstrap comprovado.
- [x] Blocker 4: Workflow `.github/workflows/openapi.yml` restrito a `workflow_dispatch` em main.
- [x] Blocker 5: Decomposição CSD15 de `ContractDocumentTransformer` em classes com `<= 15` métodos.
- [x] Todos os gates aplicáveis (composer validate/audit/platform, Pint, Larastan Level 10, Pest 96/644, Python unittests 87, CSD strict/examples/doctor) passando com exit 0.
- [x] Snapshot `contracts/openapi.json` exportado deterministicamente 2x (byte a byte idêntico).
- [ ] Revisão independente do candidato SHA exato pelo parent orchestrator (Stage B PENDING).
