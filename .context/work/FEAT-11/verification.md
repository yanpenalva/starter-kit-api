# Verification — FEAT-11 (Stage B)

Data de execução: 2026-10-04 · Worktree persistente exclusivo: `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree` · Branch: `feat/wave-3-openapi-prep` (main integrada `f5dce81711315458e8c32cf61367d58b5206ac05`).

## Gates Executados — Stage B (Evidência Real desta execução)

| Gate | Comando | Exit Code | Resultado e contagens |
| --- | --- | :---: | --- |
| 1. Composer validate | `docker compose -p starter-wave3-contract run --rm --no-deps api composer validate --strict` | 0 | `./composer.json is valid` |
| 2. Platform requirements | `docker compose -p starter-wave3-contract run --rm --no-deps api composer check-platform-reqs` | 0 | `success` para todas as extensões e PHP 8.5.11 |
| 3. Composer audit | `docker compose -p starter-wave3-contract run --rm --no-deps api composer audit` | 0 | `No security vulnerability advisories found.` |
| 4. Pint linter | `docker compose -p starter-wave3-contract run --rm --no-deps api composer lint` | 0 | `PASS 81 files` (estilo Laravel estrito) |
| 5. Larastan Level 10 | `docker compose -p starter-wave3-contract run --rm --no-deps api composer analyse` | 0 | `[OK] No errors` (57 arquivos analisados) |
| 6. Pest test suite | `docker compose -p starter-wave3-contract --profile test run --rm test vendor/bin/pest` | 0 | **96 passed (645 assertions), 0 warnings** em ~30.7s contra PostgreSQL + Redis + Mailpit reais (2 execuções consecutivas com o mesmo resultado) |
| 7. Validador estrito CSD | `python3 scripts/validate_context.py --strict` | 0 | `Context validation passed.` |
| 8. Validador CSD exemplos | `python3 scripts/validate_context.py --strict --examples` | 0 | `Context validation passed.` (warning esperado: nenhum example suite instalado; work items ativos validados) |
| 9. Unittests Python | `python3 -m unittest discover -s tests` | 0 | **113 passed, 0 failed** em ~2.9s (7 CSD + 52 Stage A + 54 Stage B) |
| 10. CSD Doctor | `npx -y @owlcodium/context-spec-develop@1.2.1 doctor --scope project` | 0 | `CSD is healthy (1.2.1)` |
| 11. Checker contra baseline | `python3 scripts/contract_compat.py --base-commit=1390009d37f6a3493b21be7d83dbd32ae355d579 --candidate=contracts/openapi.json` | 0 | `BOOTSTRAP VÁLIDO` (ausência comprovada via `git ls-tree`) com 5 fixtures negativas rejeitadas e 1 positiva aceita (`oasdiff 1.33.0` real) |
| 12. Instalador oasdiff | `scripts/install_oasdiff.py` (pipeline completo testado; hashes reais medidos do asset oficial) | 0 | Tarball `43a4e328e2d13ba1552d760aa68d2485c75c5621f309f6ff64ae895188345247` (6.822.600 bytes) confirmado no `checksums.txt` oficial da release v1.33.0; binário extraído medido `fa65aae43c867e9f79da9cb121d3c1a113637883afe014c0680fd5b50696f416` (18.837.664 bytes, `oasdiff version 1.33.0`); ambos fixados e verificados antes de qualquer extração/execução |

Suítes Stage B novas observadas na contagem do gate 9: resolvedor de baseline (validação de SHA, eventos `pull_request`/`push`, ciclo de vida Git real com bootstrap, quebra com exatamente 1 finding `error[api-path-removed-without-deprecation]` — removação da rota inteira; a remoção apenas da operação produz o ID observado `error[api-removed-without-deprecation]` —, SHA inexistente, caminho inválido, repositório sem Git, push com `before` zero, baseline == HEAD, commit indisponível) e integridade do instalador (digest do asset, digest do binário, symlink, membro ausente, membro oversized, membro não regular, pipeline feliz com binário falso e divergência de digest do binário).

---

## Auditoria Estrutural AST dos Módulos de Produção (code-quality.md / CSD15)

Auditoria estrutural executada em todos os arquivos de produção tocados ou criados:

### Módulos Python em `scripts/`

| Arquivo de produção | Linhas físicas (teto: 500) | Funções / Métodos (teto: 15) | Classes | Máx. parâmetros (teto: 7) | Retornos máx. (teto: 3) | `else` / `elif` |
| --- | :---: | :---: | :---: | :---: | :---: | :---: |
| `scripts/contract_core.py` | **390** | **14** | 2 | **5** (`verify_provenance`) | 1 | **0** |
| `scripts/contract_receipt.py` | **84** | **2** | 1 | **1** | 3 | **0** |
| `scripts/contract_cli.py` | **185** | **4** | 0 | **1** | 3 | **0** |
| `scripts/verify_contract_artifact.py` | **160** | **1** | 0 | **1** | 3 | **0** |
| `scripts/contract_export.py` | **130** | **6** | 2 | **2** | 2 | **0** |
| `scripts/contract_extract.py` | **182** | **6** | 3 | **3** | 2 | **0** |
| `scripts/contract_fetch.py` | **126** | **6** | 2 | **2** | 2 | **0** |
| `scripts/contract_compat.py` | **362** | **10** | 2 | **4** | 2 | **0** |

### Classes PHP em `app/`

| Arquivo de produção | Linhas físicas (teto: 500) | Métodos declarados (teto: 15) | Máx. parâmetros (teto: 7) | Retornos máx. (teto: 3) | `else` / `elseif` |
| --- | :---: | :---: | :---: | :---: | :---: |
| `app/OpenApi/ContractResponseFactory.php` | **56** | **3** | **2** (`messageResponse`) | 1 | **0** |
| `app/OpenApi/ContractOperationOverlay.php` | **134** | **12** | **2** (`ensureResponse`) | 1 | **0** |
| `app/OpenApi/ContractDocumentTransformer.php` | **153** | **8** | **2** (`injectCsrfCookieOverlay`) | 1 | **0** |
| `app/Providers/ScrambleDocServiceProvider.php` | **36** | **2** | **0** | 1 | **0** |

---

## Evidência de Exportação Determinística (Snapshot OpenAPI)

- **Comandos de exportação**:
  ```sh
  docker compose -p starter-wave3-contract --profile test run --rm -e LC_ALL=C -e TZ=UTC test php artisan scramble:export --path=storage/app/pass1.json
  docker compose -p starter-wave3-contract --profile test run --rm -e LC_ALL=C -e TZ=UTC test php artisan scramble:export --path=storage/app/pass2.json
  cmp storage/app/pass1.json storage/app/pass2.json
  ```
- **Idempotência**: Saída de `cmp` vazia (exit code 0: arquivos byte a byte idênticos em passadas consecutivas).
- **Digest SHA256**: `be1842f089e66755d48ecc4661c6b9ec7bb86762198544316b6d2870213c3239`
- **Tamanho**: `19439` bytes
- **Independência de `APP_URL`**: Execução com `APP_URL=http://staging.internal:9999` e `http://unexpected-custom-origin.test:9999` validada contra `contracts/openapi.json`: `cmp` retornou exit 0 (independência total).
- **Servidor OpenAPI**: `servers: [{"url": "/"}]` (root-relative `/`, sem URLs locais fixas).
- **Paths completos**: `/api/v1`, `/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile`, `/sanctum/csrf-cookie`.
- **Validação de 429**:
  - `/sanctum/csrf-cookie`: apenas `204` (sem declaração de `429`).
  - `/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile`: declaram `429` com header `Retry-After`.

---

## Verificador de Compatibilidade Baseline (`oasdiff 1.33.0`)

- **Ferramenta**: `oasdiff` versão `1.33.0` (binário oficial linux_amd64, digests fixados e verificados).
- **Baseline**: resolvido dinamicamente do payload oficial do evento (`pull_request.base.sha`; `before` em push restrito a `refs/heads/main`), com fallback manual a `--base-commit` para execução local controlada. O primeiro baseline histórico da main foi `1390009d37f6a3493b21be7d83dbd32ae355d579` (imutável, usado nas fixtures locais desta verificação).
- **Comprovação de ausência de contrato**: `git ls-tree <commit> -- contracts/openapi.json` com estado tri-partido — exit 0 sem saída prova ausência; exit 0 com saída prova presença; qualquer outro código Git falha fechado sem bootstrap. Verificação de existência do commit precede a checagem de caminho.
- **Bootstrap comprovado**: executado contra commit real existente e caminho comprovadamente ausente em ciclo de vida Git real (repositório fixture temporário).
- **Detecção real de quebra**: baseline contendo o contrato + candidato removendo a única rota do objeto `paths` → `CompatibilityError` com exatamente 1 finding `error[api-path-removed-without-deprecation]` (observado verbatim no output real do oasdiff 1.33.0 fixado, digest do binário `fa65aae43c867e9f79da9cb121d3c1a113637883afe014c0680fd5b50696f416`; quando apenas a operação é removida e o objeto do caminho permanece, o ID observado é `error[api-removed-without-deprecation]`).
- **Fixtures operacionais testadas** (IDs observados verbatim no binário fixado nesta execução):
  - `path_removido`: Rejeitada com `exit 1` (`api-removed-without-deprecation`).
  - `status_removido`: Rejeitada com `exit 1` (`response-success-status-removed`).
  - `tipo_alterado`: Rejeitada com `exit 1` (`response-property-type-changed`).
  - `campo_obrigatorio_adicionado_requisicao`: Rejeitada com `exit 1` (`request-property-became-required`).
  - `propriedade_obrigatoria_removida_resposta`: Rejeitada com `exit 1` (`response-required-property-removed`).
  - `positiva_compativel`: Aceita com `exit 0` (nenhuma quebra de contrato reportada).
- **Fail-closed comprovado em fixtures reais**: SHA inexistente, caminho fora do caminho fixo `contracts/openapi.json`, repositório sem Git válido, push com `before` zero-SHA, baseline igual ao HEAD e commit indisponível no repositório — todos rejeitados sem bootstrap.

---

## Observações de Asserções Pest — Fatos Observados Apenas

- **Observação ANTIGA (CI run sobre o source remoto `35f6ae82`)**: registrada pela revisão independente anterior do parent — **96 testes, com WARNINGS, 644 asserções, exit 0**. Nenhuma causa para a divergência histórica de contagens (621 vs 625 em relatos anteriores) foi verificada; nenhuma explicação causal é alegada.
- **Observação ATUAL local (execução local desta verificação, 2026-10-04, duas execuções consecutivas)**: **96 passed, 645 asserções, 0 warnings**, exit 0, no Docker pinado PHP 8.5 com PostgreSQL + Redis + Mailpit reais.
- **Observação ATUAL de CI (workflow Quality, run 37240902731, job `application`, sobre `e73f993`)**: **96 testes, 96 warnings, 645 asserções, exit 0**. Todos os warnings observados pertencem à mesma classe `file_get_contents(/home/runner/...)` em testes da lane de autenticação (issue pré-existente, rastreada como API-24 na outra lane; fora do escopo deste work item).
- As três observações acima são mantidas separadas e nenhuma reconciliação causal entre ambientes (Docker local vs runner GitHub) é alegada. Qualquer diff de contagens entre observações permanece fato bruto registrado, sem história reconstruída.

---

## Re-Review Independente Fresca — PASS (2026-10-04)

- **Veredito**: **PASS** sobre o source EXATO `227b75fb119350d963764df3ce52e0d56fa2c302`.
- **Revisor**: sessão GLM fresca e separada `ses_ef6e17a3effeen2tj5fBnlQBeG` — independente da sessão produtora desta entrega (`ses_ef6fc2ef1ffekDEo6PtpfrA3jn`). A revisão não é esta sessão.
- **Logs da revisão**: `/tmp/opencode/feat11-review/native-run.json` e `/tmp/opencode/feat11-review/final-text.md` (citados apenas como evidência somente leitura; não modificados).
- **Escopo verificado pela re-review**: suíte Python 113 (7 CSD + 52 Stage A + 54 Stage B) com ciclo de vida Git real + 8 cenários fail-closed; oasdiff real 1.33.0 (5 fixtures negativas + 1 positiva; tamper rejeitado ANTES da extração); export determinístico 2× com variantes de `APP_URL` (19439 bytes, mesmo hash); PHP focado 7/72 com source read-only; suíte completa/gates reaproveitados do CI `37241164800` no SHA exato; Stage A imutável intacto; gap de preflight registrado.
- **Proveniência de modelo**: registrada como **prova de invocação CLI** (`--model zai-coding-plan/glm-5.3-flash` na linha de comando da sessão de review). O stream JSON da revisão NÃO contém IDs de modelo — nenhuma consulta a DB/banco de sessões é alegada.
- **Prova histórica preservada**: a re-review anterior (modelo AGY, source `35f6ae82…`) resultou em **FAIL** e permanece como registro histórico — o PASS atual NÃO substitui nem apaga o FAIL histórico.
- **Nota de reconciliação**: a observação INFO da re-review sobre o ID do finding oasdiff foi reconciliada empiricamente nesta sessão produtora — ambos os IDs existem no oasdiff 1.33.0 e dependem da forma da remoção (ver seção do verificador de compatibilidade).

---

## Status de Entrega e Limitações

- **Stage A**: Aprovado em `edbbe2f` com 59 unit + 25 testes adversariais históricos preservados.
- **Stage B**: **FAIL histórico (AGY, sobre `35f6ae82`) preservado + RE-REVIEW INDEPENDENTE FRESCA PASS sobre `227b75f`** (sessão `ses_ef6e17a3effeen2tj5fBnlQBeG`, ver seção própria) — pendente gate/revisão final do parent; nenhuma aprovação final é alegada.
- A revisão independente anterior via conversa AGY `b0f5f43d-85e2-4848-9cc6-a3c37bd67e14` resultou em FAIL de integração sobre `35f6ae82`. As cinco constatações foram remediadas nesta submissão (resolvedor dinâmico de baseline, reconciliação de observações, alinhamento CSD, integridade criptográfica do instalador, limpeza pt-BR dos workflows). O SHA exato do candidato está registrado no corpo do PR #39 e no recibo final desta execução.
- Gap de preflight histórico permanece registrado em `preflight.md` como lacuna observável (preflight do Stage B documentado apenas nesta data, sem evidência anterior): nada foi retrofabricado.
- Nenhum merge, fechamento de issue, publicação real no GitHub Actions ou alegação de auto-aprovação de PASS é feita.

---

## Verificação adicional — política de acesso à documentação (2026-10-05)

### Policy real e estratégia de execução

- `config/scramble.php` associa `web` e `Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess` às rotas UI e JSON.
- `App\Providers\ScrambleDocServiceProvider::boot()` define Gate `viewApiDocs`: nega usuário ausente, desativado ou não verificado; permite apenas papel `admin` verificado e ativo. Gate e rotas são registrados sem branch por ambiente no boot.
- O middleware Scramble `vendor/dedoc/scramble/src/Http/Middleware/RestrictedDocsAccess.php::handle()` decide em cada request: `app()->environment('local')` é bypass; fora de `local`, exige `Gate::allows('viewApiDocs')`. O branch ambiental é lazy/request-time; por isso não foi necessário bootstrap de produção separado.
- `ApiDocsAccessTest::useProductionDocsPolicy()` confirma ambiente inicial `testing`, muda o ambiente da aplicação para `production` depois do boot e força `app.debug=false`. Requests HTTP de teste passam pelo middleware e Gate reais; não há mock de response, rotas ou RBAC.
- `beforeRefreshingDatabase()` verifica antes do `RefreshDatabase` executar migração: connection `pgsql`, host `postgres-test`, porta 5432, database/user `starter_api_test`, `DB_URL` ausente, mail SMTP em `mailpit-test:1025`, ambiente `testing` e debug falso.

### Resultado observado por identidade e endpoint

| Identidade no ambiente avaliado como `production` | `GET /docs/api` UI | `GET /docs/api.json` JSON | Resultado |
| --- | --- | --- | --- |
| Anônimo | 403 | 403 | Negado; corpo sem stack trace, classe Illuminate ou SQLSTATE |
| Usuário ativo/verificado sem admin | 403 | 403 | Negado; sem detalhes internos |
| Admin não verificado | 403 | 403 | Negado; sem detalhes internos |
| Admin desativado | 403 | 403 | Negado; sem detalhes internos |
| Admin verificado e ativo | 200, HTML não vazio | 200, OpenAPI `3.1.0` | Permitido pela policy configurada |

O catalogue RBAC real foi semeado no Postgres efêmero; contas e estados foram criados por factories de teste. O gate focused passou com **8 casos e 139 assertions** na primeira versão e a suíte Pest completa, após os ajustes finais ao guard, passou com **108 testes e 1.279 assertions**.

### Cobertura, limitações e estado dos critérios da issue #11

| Critério | Estado | Evidência / limite |
| --- | --- | --- |
| 1. OpenAPI válido nos endpoints criados e security scheme preciso | Observado no escopo automatizado | `OpenApiSnapshotTest` exporta documento real, verifica paths reais (`/api/v1`, login, logout, profile e CSRF), `sessionCookie` como `apiKey` em cookie, `xsrfToken`, security por sessão, ausência de Bearer/PAT e contrato estrutural; CI de `main` `37249567434` e gate `Contract generation and compatibility` na suíte local passaram. |
| 2. Frontend gera tipos do artefato publicado | **PENDENTE** | Geração/consumo Orval da lane frontend ainda não foi observada nesta sessão; parent valida evidência dessa lane. |
| 3. Docs protegidos em produção conforme policy declarada | Observado via simulação request-time | UI e JSON exercitados por HTTP no framework com ambiente alterado pós-boot para `production`, `APP_DEBUG=false` e policy real. Não representa boot/deploy/infra de produção. |

Limitações explícitas: app de teste inicia em `testing`; só o valor consultado lazy pelo middleware é mudado para `production`, pois código Scramble inspecionado não decide registro de rotas por ambiente no boot. DB, contas e RBAC são efêmeros/sintéticos; `APP_KEY` e senhas de DB são valores fake, Mailpit é isolado na rede interna de teste. Não foram testados browser real, cookie/sessão em servidor live, proxy/CDN, segredo/variáveis de produção, deploy, nem URL pública de produção. A evidência cobre a policy em código e seus responses de request no framework, não disponibilidade nem configuração operacional do ambiente produtivo.

### Runtime isolado e gates desta continuação

- Harness scratch: `/tmp/opencode/starter-api-docsprod-4dbec42` (cópia `git archive` do source, apenas teste atualizado, `vendor` copiado, `.env` vazio; `.env.testing` não copiado nem lido). Compose project/image: `starter-api-docsprod-4dbec42` / `starter-api-docsprod-4dbec42-api:local`; imagens base PHP 8.5, Composer 2 e Postgres 18 pinadas por digest; Redis/Mailpit também usam pins do `compose.yaml`.
- `composer validate --strict`: PASS; `composer check-platform-reqs`: PASS (PHP 8.5.11); `composer audit`: PASS, sem advisories; Pint `composer lint`: PASS, 86 files; Larastan `composer analyse`: PASS, 58 files, sem erros.
- Pest completo: PASS, **108 passed (1.279 assertions)**.
- `python3 scripts/validate_context.py --strict`: PASS; `--strict --examples`: PASS (warning esperado: sem example suite instalado); `python3 -m unittest discover -s tests -v`: PASS, **113 tests**; `npx --yes @owlcodium/context-spec-develop@1.2.1 doctor --scope project`: PASS (`CSD is healthy (1.2.1)`).
- Primeira tentativa da suíte completa injetou `DB_URL=null`; 7 testes de guarda recusaram corretamente com `Refusing to run: inherited DB_URL environment variable is present.` A execução completa seguinte deixou `DB_URL` ausente e passou 108/108. O scratch não contém `.env.testing`; o DB guard assegurou alvo efêmero antes de migração.
- Nenhum fonte de aplicação, workflow, dependência, contrato ou artefato publicado foi alterado.
