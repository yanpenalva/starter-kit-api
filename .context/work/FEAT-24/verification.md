# Verification — FEAT-24

## Evidence

- Date/environment: 2026-10-02, America/Sao_Paulo; workflow reviewed locally, clean source copy used for gates.
- `.github/workflows/quality.yml` defines pull-request and `main` jobs with PHP 8.5, PostgreSQL 18, Composer lock install/validate/audit, Pint, Larastan and Pest. Context CI includes strict validation, Python regression tests and pinned CSD doctor.
- `rtk docker compose -p starter-api-clean-final exec -T api composer validate --strict`: exit 0.
- `rtk docker compose -p starter-api-clean-final exec -T api composer check-platform-reqs`: exit 0.
- `rtk docker compose -p starter-api-clean-final exec -T api composer audit`: exit 0; no advisories.
- `rtk docker compose -p starter-api-clean-final exec -T api composer lint`: exit 0; 19 PHP files passed.
- `rtk docker compose -p starter-api-clean-final exec -T api composer analyse`: exit 0; Larastan level 10 across 8 PHP files with no baseline.
- `rtk docker compose -p starter-api-clean-final --profile test run --rm test`: exit 0; 5 tests and 15 assertions passed.
- Strict CSD validation and strict-with-examples adaptation passed; Python suite passed 7 tests; CSD doctor reports healthy (1.2.1).
- Pull request: [#29](https://github.com/yanpenalva/starter-kit-api/pull/29), merged into `main` on 2026-10-02.
- GitHub Actions [Quality run #1](https://github.com/yanpenalva/starter-kit-api/actions/runs/37074847582): success for implementation commit `e17de33`; the documentation follow-up triggers another run.

## Review

- Scope/plan alignment: yes; CI is minimal and local gates match the workflow.
- No code-quality suppression baseline or blanket ignore was introduced.
- Security scope is limited to ephemeral test credentials and read-only repository permissions in CI.
- Risk: medium until maintainer review; the initial remote CI run passed.
- Subtasks S1–S4 were integrated; CI references only tracked project context and runtime tests.

## Findings

No blocking local CI configuration findings. The GitHub-hosted workflow passed on the implementation commit.

## Verdict

`APPROVED WITH CONDITIONS` — the initial CI stage is merged and its checks passed; remaining CI stages from issue #24 are still outstanding.

## Additive evidence — 2026-10-03 (append-only)

- Parent-attributed remote evidence: PR [#30](https://github.com/yanpenalva/starter-kit-api/pull/30) checks (context/application/GitGuardian) passed on head 99321a9; the `main` merge run [37078252890](https://github.com/yanpenalva/starter-kit-api/actions/runs/37078252890/job/111072849711) (jobs 111072849711 and 111072850044) succeeded for context/application.
- Issue #24 remains **In progress** and progressive: this record only recognizes the integrated initial CI stage. No later CI stage is claimed, nothing is recreated, no workflow file is modified by this docs slice, and no closure is declared.
- Gate definitions for future stages live in [FEAT-4](../FEAT-4/spec.md); the initial threat model for security-related stages lives in [FEAT-22](../FEAT-22/spec.md).

## Additive evidence — 2026-10-04 (causa raiz dos 89 warnings da suíte + correção; issue API #24)

Producer self-check apenas; o parent fará revisão independente. Nenhuma supressão de warning, nenhum baseline, nenhuma alteração de `error_reporting`, nenhum teste enfraquecido.

### Sintoma (antes)

- CI `main` (run [37237765657](https://github.com/yanpenalva/starter-kit-api/actions/runs/37237765657), SHA `f5dce81`): `Tests: 89 warnings (573 assertions)` — sucesso, mas um warning **por teste** (89 = contagem de testes). Antes do PR #38: 89 warnings / 572 assertions. Texto truncado no log do Pest: `file_get_contents(/home/…`. Experimento anterior com/sem `.env.testing` foi inconclusivo — irrelevante de fato: o arquivo relevante é o `.env` base.

### Causa raiz — provada com rastro completo, sem suposição

1. **Gatilho:** `vendor/vlucas/phpdotenv/src/Store/File/Reader.php:73` — `@file_get_contents($path)`. A cada boot da aplicação (por `setUp` de cada teste, via `LoadEnvironmentVariables.php:29 → Dotenv->safeLoad`), com `APP_ENV=testing` forçado pelo phpunit.xml, o Laravel procura `.env.testing` (`is_file`, ausente no CI) e depois lê o `.env` base — **ausente no CI**. O `@` marca o E_WARNING como suprimido, mas o warning **ainda é emitido** para o handler de erros (semântica do PHP 8: `@` não impede o disparo, apenas o marca).
2. **Camada de resultado do PHPUnit (ignora o warning):** `Runner/ErrorHandler.php:111` emite `Test\TriggeredPhpWarning` com `wasSuppressed()=true`; o coletor (`Runner/TestResult/Filter/IssueFilter.php`, branch `PhpWarningTriggered`) descarta warnings suprimidos. **Prova:** `vendor/bin/phpunit` direto na mesma cópia limpa = **0 warnings** (suíte completa pré-atualização: `Tests: 89, Assertions: 560, Failures: 10`, sem warnings).
3. **Camada de exibição do Pest/Collision (conta o warning):** `vendor/nunomaduro/collision/src/Adapters/Phpunit/Printers/DefaultPrinter.php:351-357` — `testPhpWarningTriggered()` adiciona `TestResult::WARN` ao estado de exibição **sem checar `wasSuppressed()`**. Por isso o rodapé do Pest reporta `89 warnings` mesmo com o resultado real do PHPUnit limpo (o exit code continua 0 — CI verde "com warnings").
4. **Prova de equivalência dos eventos:** `--log-events-text` sob os dois runners emite linha idêntica `Test Triggered PHP Warning … (suppressed using operator) in vendor/vlucas/phpdotenv/.../Reader.php:73`; a divergência está somente na camada de agregação/exibição.
5. **Isolamento da variável (cópias limpas idênticas, sem `.env`, imagem de compose própria):**
   - sem `.env` (condição do CI): `Tests: 93 warnings (601 assertions)` na árvore final;
   - com `.env` vazio (única variável alterada): `Tests: 93 passed (601 assertions)`, **0 warnings**.

Rastro capturado por instrumentação temporária (apenas na cópia limpa em /tmp, revertida depois do diagnóstico; nunca na árvore real):

```
file_get_contents(/var/www/html/.env): Failed to open stream: No such file or directory
  at vendor/vlucas/phpdotenv/src/Store/File/Reader.php:73
  #6 LoadEnvironmentVariables->bootstrap … #9 TestCase->createApplication … setUp()
```

### Correção (raiz = ambiente de CI incompleto; passo aditivo mínimo)

`.github/workflows/quality.yml`, job `application`, logo após o checkout: **`- run: touch .env`** com comentário explicando o mecanismo. Dá ao leitor Dotenv um arquivo que existe (vazio); toda a configuração de teste continua vinda de phpunit.xml (`force=true`) e das envs do job — nenhum valor de ambiente novo, nenhuma credencial. Nada é suprimido: o E_WARNING deixa de existir na fonte. O delta é uma linha + comentário, aditivo, de fácil merge (o dono da lane de contrato pode tocar em `quality.yml` na branch dele — apenas este passo aditivo é meu).

### Delta (depois) — prova exata

| Cenário | Resultado |
| --- | --- |
| CI `main` antes (run 37237765657, `f5dce81`) | 89 warnings / 573 assertions (sucesso) |
| Cópia limpa sem `.env`, árvore final (replica CI) | **93 warnings / 601 assertions** |
| Cópia limpa com `.env` vazio (replica CI + `touch .env`) | **93 passed / 601 assertions, 0 warnings** |
| Worktree real, `docker compose --profile test run --rm test` (`composer test` → pest), projeto `starter-wave3-ptbr` | **93 passed / 601 assertions / 0 warnings, ~29.2s, exit 0** |

Gates re-executados na mesma sessão (exit 0): `composer validate --strict`; `composer check-platform-reqs`; `composer audit`; `composer lint` (Pint PASS, 79 arquivos); `composer analyse` (Larastan 10, 52 arquivos, no errors); `validate_context.py --strict`; `validate_context.py --strict --examples`; `python3 -m unittest discover -s tests -v` (7 OK); `doctor --scope project` (CSD healthy 1.2.1).

Aceite do gate 6 desta lane: **sucesso com ZERO warnings** — "success with warnings" não é mais o estado da suíte. Os 93 warnings da linha "antes" são o mesmo mecanismo (93 testes hoje vs 89 em `main`); o acréscimo de 4 testes/28 assertions pertence ao delta de pt-BR registrado em [FEAT-10](../FEAT-10/verification.md).
