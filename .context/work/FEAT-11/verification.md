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

Suítes Stage B novas observadas na contagem do gate 9: resolvedor de baseline (validação de SHA, eventos `pull_request`/`push`, ciclo de vida Git real com bootstrap, quebra com exatamente 1 finding `error[api-path-removed-without-deprecation]`, SHA inexistente, caminho inválido, repositório sem Git, push com `before` zero, baseline == HEAD, commit indisponível) e integridade do instalador (digest do asset, digest do binário, symlink, membro ausente, membro oversized, membro não regular, pipeline feliz com binário falso e divergência de digest do binário).

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
- **Detecção real de quebra**: baseline contendo o contrato + candidato removendo a única rota → `CompatibilityError` com exatamente 1 finding `error[api-path-removed-without-deprecation]` (observado no output real do oasdiff 1.33.0).
- **Fixtures operacionais testadas**:
  - `path_removido`: Rejeitada com `exit 1` (`api-path-removed-without-deprecation`).
  - `status_removido`: Rejeitada com `exit 1` (`response-success-status-removed`).
  - `tipo_alterado`: Rejeitada com `exit 1` (`response-property-type-changed`).
  - `campo_obrigatorio_adicionado_requisicao`: Rejeitada com `exit 1` (`request-property-became-required`).
  - `propriedade_obrigatoria_removida_resposta`: Rejeitada com `exit 1` (`response-required-property-removed`).
  - `positiva_compativel`: Aceita com `exit 0` (nenhuma quebra de contrato reportada).
- **Fail-closed comprovado em fixtures reais**: SHA inexistente, caminho fora do caminho fixo `contracts/openapi.json`, repositório sem Git válido, push com `before` zero-SHA, baseline igual ao HEAD e commit indisponível no repositório — todos rejeitados sem bootstrap.

---

## Observações de Asserções Pest — Fatos Observados Apenas

- **Observação ANTIGA (CI run sobre o source remoto `35f6ae82`)**: registrada pela revisão independente anterior do parent — **96 testes, com WARNINGS, 644 asserções, exit 0**. Nenhuma causa para a divergência histórica de contagens (621 vs 625 em relatos anteriores) foi verificada; nenhuma explicação causal é alegada.
- **Observação ATUAL (execução local desta verificação, 2026-10-04, duas execuções consecutivas)**: **96 passed, 645 asserções, 0 warnings**, exit 0. O aviso `file_get_contents` citado em runs antigas de CI **não** foi observado nesta árvore atual (main `f5dce81` já integrada).
- As duas observações acima são mantidas separadas e nenhuma reconciliação causal entre elas é alegada. Qualquer diff de contagens entre observações permanece fato bruto registrado, sem história reconstruída.

---

## Status de Entrega e Limitações

- **Stage A**: Aprovado em `edbbe2f` com 59 unit + 25 testes adversariais históricos preservados.
- **Stage B**: **PREVIOUS FAIL (integration) — PENDING INDEPENDENT PARENT RE-REVIEW** sobre o novo SHA candidato desta submissão.
- A revisão independente anterior via conversa AGY `b0f5f43d-85e2-4848-9cc6-a3c37bd67e14` resultou em FAIL de integração sobre `35f6ae82`. As cinco constatações foram remediadas nesta submissão (resolvedor dinâmico de baseline, reconciliação de observações, alinhamento CSD, integridade criptográfica do instalador, limpeza pt-BR dos workflows) e submetidas para reavaliação independente. O SHA exato do candidato está registrado no corpo do PR #39 e no recibo final desta execução.
- Gap de preflight histórico permanece registrado em `preflight.md` como lacuna observável (preflight do Stage B documentado apenas nesta data, sem evidência anterior): nada foi retrofabricado.
- Nenhum merge, fechamento de issue, publicação real no GitHub Actions ou alegação de auto-aprovação de PASS é feita.
