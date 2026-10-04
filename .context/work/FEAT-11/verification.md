# Verification — FEAT-11

Data de execução: 2026-10-04 · Worktree persistente exclusivo: `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree` · Branch: `feat/wave-3-openapi-prep` (base `origin/main` @ `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe`, PR #36 merge).

## Gates CSD executados (evidência real)

| Gate | Comando | Exit Code | Resultado e contagens |
| --- | --- | --- | --- |
| 1. Validador estrito | `python3 scripts/validate_context.py --strict` | 0 | `Context validation passed.` (0 erros) |
| 2. Validador com exemplos | `python3 scripts/validate_context.py --strict --examples` | 0 | `WARNING: No example suite is installed; active work items are still validated.` / `Context validation passed.` (0 erros) |
| 3. Unittests Python | `python3 -m unittest discover -s tests -v` | 0 | **59 passed, 0 failed** em 1.581s (7 testes de validação CSD em `tests/test_context_validation.py` + 52 testes em `tests/tools/test_verify_contract_artifact.py`) |
| 4. CSD Doctor | `npx -y @owlcodium/context-spec-develop@1.2.1 doctor --scope project` | 0 | `CSD is healthy (1.2.1)` (0 achados) |

## Auditoria Estrutural AST dos Módulos de Produção (code-quality.md)

Auditoria estrutural executada via módulo padrão `ast` do Python nos 4 arquivos de produção em `scripts/`:

| Arquivo de produção | Linhas físicas (teto: 500) | Funções / Métodos declarados (teto: 15) | Classes declaradas | Callbacks (lambdas) | Máx. parâmetros declarados (teto: 7) | Tamanho máx. de função (teto: 150) | Retornos máx. por função (teto: 3) | `else` / `elif` encontrados |
| --- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| `scripts/contract_core.py` | **390** | **14** | 2 (`ProvenanceVerificationError`, `VerificationPolicy`) | 1 (`compute_file_sha256`) | **5** (`verify_provenance`) | 65 (`verify_provenance`) | 1 | **0** |
| `scripts/contract_receipt.py` | **84** | **2** (`_load_core`, `build_receipt`) | 1 (`ReceiptRecord`) | 0 | **1** (`build_receipt`) | 21 (`build_receipt`) | 3 (`_load_core`) | **0** |
| `scripts/contract_cli.py` | **185** | **4** (`_load_core`, `parse_args`, `run_cli`, `main`) | 0 | 0 | **1** (`parse_args`, `run_cli`) | 84 (`parse_args`) | 3 (`_load_core`, `run_cli`) | **0** |
| `scripts/verify_contract_artifact.py` | **160** | **1** (`_load_sibling`) | 0 | 0 | **1** (`_load_sibling`) | 14 (`_load_sibling`) | 3 (`_load_sibling`) | **0** |

### Resolução do Requisito Normativo CSD MUST <= 7 Parâmetros Declarados
- **Eliminação Total de Parâmetros > 7**: Todas as funções declaradas em código de produção agora possuem `<= 5` parâmetros (rigorosamente `<= 7`). A alegação prévia de que eram "interfaces públicas externas publicadas" foi formalmente retratada e a refatoração foi autorizada explicitamente pelo usuário.
- **Transparência de Construtores**: Os objetos `@dataclass(frozen=True)` (`VerificationPolicy` e `ReceiptRecord`) utilizam os construtores padrão gerados pelo Python, aceitos pelo usuário sem declaração manual de `__init__` > 7.
- **Invariantes Semânticos Preservados**: A ordem de validação de `verify_provenance` (leitura dos bytes do receipt antes do SHA esperado, resolução do nome do artefato default, validação de identidade, URL de execução, expiração e integridade do digest) e a formatação de mensagens/erros foram 100% mantidas.
- O arquivo de testes `tests/tools/test_verify_contract_artifact.py` possui **824 linhas físicas** e **52 métodos de teste** automatizados.

## Detalhamento da Suíte de Testes (tests/tools/test_verify_contract_artifact.py)

Suite composta por 52 testes unitários e de integração de processo (todos verdes):

1. **Identidade e allowlist (S1)**: `test_workflow_name_mismatch_fails_closed`, `test_workflow_path_mismatch_fails_closed`, `test_artifact_name_latest_rejected_fails_closed`, `test_artifact_name_mismatch_fails_closed`, `test_workflow_id_mismatch_fails_closed`, `test_event_outside_allowlist_fails_closed`, `test_head_branch_outside_main_fails_closed`, `test_validate_receipt_identity_direct_call_passes`.
2. **Política de expiração explícita (S2)**: `test_valid_provenance_verification_download_fresh_mode_passes`, `test_future_created_at_in_download_fresh_mode_fails_closed`, `test_expired_created_at_in_download_fresh_mode_fails_closed`, `test_old_created_at_in_verify_committed_mode_passes`, `test_valid_provenance_verification_committed_mode_passes`.
3. **URL de execução estrita (S3)**: `test_run_url_not_https_fails_closed`, `test_run_url_strange_host_fails_closed`, `test_run_url_with_query_params_fails_closed`, `test_run_url_with_fragment_fails_closed`, `test_run_url_id_mismatch_confused_deputy_fails_closed`.
4. **Rejeição de duplicatas JSON (S4)**: `test_duplicate_json_keys_in_receipt_fails_closed`.
5. **IDs inteiros estritos (S5)**: `test_strict_int_id_rejects_bool`, `test_strict_int_id_rejects_float`, `test_strict_int_id_rejects_numeric_string`, `test_strict_int_id_rejects_zero_or_negative`.
6. **Limites nominais de leitura (S6)**: `test_receipt_file_exceeds_max_bytes_fails_closed`, `test_artifact_file_exceeds_max_bytes_fails_closed`.
7. **Separação dual de digests (S7)**: `test_invalid_archive_sha256_format_fails_closed`, `test_invalid_artifact_file_sha256_format_fails_closed`, `test_artifact_file_digest_divergence_fails_closed`.
8. **Validação básica, helpers tipados e CLI in-process**: `test_missing_receipt_file_fails_closed`, `test_missing_artifact_file_fails_closed`, `test_malformed_receipt_json_fails_closed`, `test_missing_required_field_in_receipt_fails_closed`, `test_empty_required_field_in_receipt_fails_closed`, `test_expected_sha_not_40_hex_fails_closed`, `test_receipt_commit_sha_not_40_hex_fails_closed`, `test_commit_sha_mismatch_fails_closed`, `test_build_receipt_helper`, `test_frozen_policy_and_receipt_record_immutability`, `test_cli_success_committed_mode`, `test_cli_success_download_fresh_mode`, `test_cli_failure_on_tampered_artifact`, `test_cli_failure_on_invalid_sha`, `test_cli_failure_on_unexpected_error_exit_2`.
9. **Carregamento dinâmico, processos CLI e isolamento**:
   - `test_direct_script_process_exit_0_across_unrelated_cwd`: Execução como processo subprocess a partir de CWD temporário arbitrário confirmando código de saída 0 e mensagem de sucesso.
   - `test_direct_script_process_exit_1_on_verification_failure`: Execução direta a partir de CWD temporário com divergência de SHA confirmando código de saída 1 (fail-closed).
   - `test_direct_script_process_exit_2_on_invalid_arguments`: Execução com flag inválida confirmando código de saída 2 de análise do argparse.
   - `test_direct_script_process_exit_2_on_unexpected_error`: Execução com arquivo receipt contendo bytes não-UTF-8 arbitrários confirmando que a cláusula `except Exception:` em `run_cli` emite `FAIL CLOSED: Unexpected error:` e retorna exit code 2.
   - `test_module_execution_m_flag_exit_0`: Execução via `python3 -m scripts.verify_contract_artifact` confirmando código de saída 0.
   - `test_contract_cli_script_process_exit_0`: Execução direta do script irmão `scripts/contract_cli.py` a partir de CWD arbitrário confirmando código 0.
   - `test_direct_sibling_imports_and_loading_order`: Validação de importação direta de `scripts.contract_receipt`, `scripts.contract_cli` e `scripts.verify_contract_artifact` em ordens alternadas sem bloqueios mútuos.
   - `test_spec_from_file_location_isolated_subprocess`: Carregamento do script via `importlib.util.spec_from_file_location` a partir de subprocesso em CWD externo com remoção explícita do repositório de `sys.path`, comprovando funcionamento íntegro sem `ModuleNotFoundError`.
   - `test_no_double_module_execution_and_identity`: Confirmação de que a invocação direta do script executa a fachada unicamente uma vez, sem dupla instanciação de `sys.modules` e com estabilidade da classe `ProvenanceVerificationError`.

## Suíte Adversarial Independente (Evidência Histórica do Revisor)

- **Localização do harness**: `/tmp/opencode/adv_fe11/adv_test.py` (25 testes adversariais independentes de segurança e limites).
- **Resultado da execução independente prévia**: **25 passed, 0 failed** em 0.027s (avaliado em `af10a42` e `78c3f57`).
- **Nota de Escopo**: O harness original utiliza as assinaturas antigas de 13 argumentos. O implementador **não alterou nem reexecutou** `/tmp/opencode/adv_fe11/adv_test.py`, mantendo-o intacto para adaptação e execução pelo revisor independente em sua própria sessão temporária.

## Comandos não executados (justificados e pendentes por escopo)

- **Gates PHP/Composer (`composer validate`, `composer lint`, `composer analyse`, `composer audit`)**: Não aplicáveis a esta entrega. Nenhum arquivo PHP da aplicação, rotas ou `composer.json`/`composer.lock` foi tocado, em estrito cumprimento da fronteira da lane auth.
- **Docker / Pest (`docker compose --profile test run --rm test`)**: Não executado. O escopo do Stage A é puramente ferramental e documental; nenhum serviço Docker precisou ser instanciado e o daemon permaneceu intacto.
- **Export real de contrato OpenAPI (Scramble 0.13.47) e publicação no GitHub Actions**: Pendente para o **Stage B**, desbloqueado somente após o congelamento da autenticação L1 (FEAT-13).

## Stage B evidence (2026-10-04, unreviewed, compose project starter-wave3-contract)

- `composer validate --strict`: exit 0 (`./composer.json is valid`).
- `composer check-platform-reqs`: exit 0 (PHP 8.5.11, all extensions success).
- `composer audit`: exit 0 (no advisories).
- `composer lint`: exit 0 (79 files PASS, Pint).
- `composer analyse`: exit 0 (Larastan level 10, no errors).
- `vendor/bin/pest`: exit 0 (96 passed, 621 assertions; 89 base FEAT-13 + 6 docs + 1 snapshot).
- `python3 -m unittest discover -s tests`: exit 0 (77 passed: 59 base Stage A + 18 Stage B).
- `validate_context.py --strict` / `--strict --examples`: exit 0.
- `csd doctor --scope project`: exit 0 (1.2.1 healthy).
- Export determinism: `LC_ALL=C TZ=UTC php artisan scramble:export` 2x byte-identical, SHA256 be6e4acd79e8bb9383b39b1e698ba9e3e147cda3c3cf12db223e82cdeee88b8d, 20447 bytes.
- Generator: dedoc/scramble v0.13.47 (lock d1597812fa8ce40a257d83c527bec28cb60de7a5).
- Stage A PASS at `edbbe2f` preserved; Stage B PENDING independent review; no autopass, no production/browser/attestation claim.
