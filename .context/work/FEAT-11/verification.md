# Verification — FEAT-11

Data de execução: 2026-10-04 · Worktree persistente exclusivo: `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree` · Branch: `feat/wave-3-openapi-prep` (base `origin/main` @ `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe`, PR #36 merge).

## Gates CSD executados (evidência real)

| Gate | Comando | Exit Code | Resultado e contagens |
| --- | --- | --- | --- |
| 1. Validador estrito | `python3 scripts/validate_context.py --strict` | 0 | `Context validation passed.` (0 erros) |
| 2. Validador com exemplos | `python3 scripts/validate_context.py --strict --examples` | 0 | `WARNING: No example suite is installed; active work items are still validated.` / `Context validation passed.` (0 erros) |
| 3. Unittests Python | `python3 -m unittest discover -s tests -v` | 0 | **53 passed, 0 failed** em 1.357s (7 testes de validação CSD em `tests/test_context_validation.py` + 46 testes em `tests/tools/test_verify_contract_artifact.py`) |
| 4. CSD Doctor | `npx -y @owlcodium/context-spec-develop@1.2.1 doctor --scope project` | 0 | `CSD is healthy (1.2.1)` (0 achados) |

## Auditoria Estrutural AST dos Módulos de Produção (code-quality.md)

Auditoria realizada via módulo padrão `ast` do Python nos 3 arquivos de produção em `scripts/`:

| Arquivo de produção | Linhas físicas (teto: 500) | Funções / Métodos declarados (teto: 15) | Tamanho máx. de função (teto: 150) | Retornos máx. por função (teto: 3) | `else` / `elif` encontrados | Parâmetros legados preservados (>7) |
| --- | --- | --- | --- | --- | --- | --- |
| `scripts/verify_contract_artifact.py` | **433** | **14** (+ 1 classe de erro, 0 métodos) | 73 (`verify_provenance`) | 1 | **0** | `validate_receipt_identity` (8), `verify_provenance` (13) |
| `scripts/contract_receipt.py` | **66** | **1** (`build_receipt`) | 51 (`build_receipt`) | 1 | **0** | `build_receipt` (13) |
| `scripts/contract_cli.py` | **183** | **3** (`parse_args`, `run_cli`, `main`) | 109 (`parse_args`) | 3 (`run_cli`) | **0** | Nenhum (>7) |

### Correção de registros e alinhamento de contagens
- No commit `33cbcab` anterior à resolução R1, a documentação continha registros desatualizados citando ~390 linhas de script e ~540 de testes (valores reais naquele momento: 542 linhas físicas de script, violando o teto de 500 linhas da CSD, e 619 linhas de testes com 40 casos).
- Após a decomposição de responsabilidades de R1, os arquivos de produção medem exatamente: `scripts/verify_contract_artifact.py` (433 linhas), `scripts/contract_receipt.py` (66 linhas) e `scripts/contract_cli.py` (183 linhas), todos rigorosamente abaixo do teto de 500 linhas da CSD sem nenhuma solicitação de exceção.
- O arquivo de testes `tests/tools/test_verify_contract_artifact.py` possui 763 linhas e contém 46 métodos de teste.

## Detalhamento da Suíte de Testes (tests/tools/test_verify_contract_artifact.py)

Suite com 46 testes unitários e de integração de processo (todos verdes):

1. **Identidade e allowlist (S1)**: `test_workflow_name_mismatch_fails_closed`, `test_workflow_path_mismatch_fails_closed`, `test_artifact_name_latest_rejected_fails_closed`, `test_artifact_name_mismatch_fails_closed`, `test_workflow_id_mismatch_fails_closed`, `test_event_outside_allowlist_fails_closed`, `test_head_branch_outside_main_fails_closed`.
2. **Política de expiração explícita (S2)**: `test_valid_provenance_verification_download_fresh_mode_passes`, `test_future_created_at_in_download_fresh_mode_fails_closed`, `test_expired_created_at_in_download_fresh_mode_fails_closed`, `test_old_created_at_in_verify_committed_mode_passes`, `test_valid_provenance_verification_committed_mode_passes`.
3. **URL de execução estrita (S3)**: `test_run_url_not_https_fails_closed`, `test_run_url_strange_host_fails_closed`, `test_run_url_with_query_params_fails_closed`, `test_run_url_with_fragment_fails_closed`, `test_run_url_id_mismatch_confused_deputy_fails_closed`.
4. **Rejeição de duplicatas JSON (S4)**: `test_duplicate_json_keys_in_receipt_fails_closed`.
5. **IDs inteiros estritos (S5)**: `test_strict_int_id_rejects_bool`, `test_strict_int_id_rejects_float`, `test_strict_int_id_rejects_numeric_string`, `test_strict_int_id_rejects_zero_or_negative`.
6. **Limites nominais de leitura (S6)**: `test_receipt_file_exceeds_max_bytes_fails_closed`, `test_artifact_file_exceeds_max_bytes_fails_closed`.
7. **Separação dual de digests (S7)**: `test_invalid_archive_sha256_format_fails_closed`, `test_invalid_artifact_file_sha256_format_fails_closed`, `test_artifact_file_digest_divergence_fails_closed`.
8. **Validação básica e CLI in-process**: `test_missing_receipt_file_fails_closed`, `test_missing_artifact_file_fails_closed`, `test_malformed_receipt_json_fails_closed`, `test_missing_required_field_in_receipt_fails_closed`, `test_empty_required_field_in_receipt_fails_closed`, `test_expected_sha_not_40_hex_fails_closed`, `test_receipt_commit_sha_not_40_hex_fails_closed`, `test_commit_sha_mismatch_fails_closed`, `test_build_receipt_helper`, `test_cli_success_committed_mode`, `test_cli_success_download_fresh_mode`, `test_cli_failure_on_tampered_artifact`, `test_cli_failure_on_invalid_sha`.
9. **Regressões de carregamento, importação e processos CLI (R1)**:
   - `test_direct_script_process_exit_0_across_unrelated_cwd`: Execução como processo subprocess a partir de CWD temporário arbitrário confirmando código de saída 0 e mensagem de sucesso.
   - `test_direct_script_process_exit_1_on_verification_failure`: Execução direta a partir de CWD temporário com divergência de SHA confirmando código de saída 1 (fail-closed).
   - `test_direct_script_process_exit_2_on_invalid_arguments`: Execução com flag inválida confirmando código de saída 2 de análise do argparse.
   - `test_module_execution_m_flag_exit_0`: Execução via `python3 -m scripts.verify_contract_artifact` confirmando código de saída 0.
   - `test_contract_cli_script_process_exit_0`: Execução direta do script irmão `scripts/contract_cli.py` a partir de CWD arbitrário confirmando código 0.
   - `test_direct_sibling_imports_and_loading_order`: Validação de importação direta de `scripts.contract_receipt` e `scripts.contract_cli` e independência de ordem em subprocessos sem deadlocks circulares.

## Suíte Adversarial Independente (Evidência Histórica do Revisor)

- **Localização do harness**: `/tmp/opencode/adv_fe11/adv_test.py` (25 testes adversariais independentes de segurança e limites).
- **Resultado da execução**: **25 passed, 0 failed** em 0.026s.
- Registrado exclusivamente como evidência histórica de integridade funcional. Nenhuma autoaprovação é reivindicada.

## Comandos não executados (justificados e pendentes por escopo)

- **Gates PHP/Composer (`composer validate`, `composer lint`, `composer analyse`, `composer audit`)**: Não aplicáveis a esta entrega. Nenhum arquivo PHP da aplicação, rotas ou `composer.json`/`composer.lock` foi tocado, em estrito cumprimento da fronteira da lane auth.
- **Docker / Pest (`docker compose --profile test run --rm test`)**: Não executado. O escopo do Stage A é puramente ferramental e documental; nenhum serviço Docker precisou ser instanciado e o daemon permaneceu intacto.
- **Export real de contrato OpenAPI (Scramble 0.13.47) e publicação no GitHub Actions**: Pendente para o **Stage B**, desbloqueado somente após o congelamento da autenticação L1 (FEAT-13).
