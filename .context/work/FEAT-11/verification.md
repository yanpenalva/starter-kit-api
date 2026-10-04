# Verification — FEAT-11

Data de execução: 2026-10-04 · Worktree persistente exclusivo: `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree` · Branch: `feat/wave-3-openapi-prep` (base `origin/main` @ `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe`, PR #36 merge).

## Gates CSD executados (evidência real)

| Gate | Comando | Exit Code | Resultado e contagens |
| --- | --- | --- | --- |
| 1. Validador estrito | `python3 scripts/validate_context.py --strict` | 0 | `Context validation passed.` (0 erros) |
| 2. Validador com exemplos | `python3 scripts/validate_context.py --strict --examples` | 0 | `WARNING: No example suite is installed; active work items are still validated.` / `Context validation passed.` (0 erros) |
| 3. Unittests Python | `python3 -m unittest discover -s tests -v` | 0 | **47 passed, 0 failed** em 1.126s (7 testes de validação CSD + 40 testes dedicados de proveniência de contrato) |
| 4. CSD Doctor | `npx -y @owlcodium/context-spec-develop@1.2.1 doctor --scope project` | 0 | `CSD is healthy (1.2.1)` (0 achados) |

## Detalhamento dos testes da ferramenta de proveniência pós-revisão Oracle (S1–S9)

Suite em `tests/tools/test_verify_contract_artifact.py` (40 testes unitários):
1. **Identidade e allowlist (S1)**: `test_workflow_name_mismatch_fails_closed`, `test_workflow_path_mismatch_fails_closed`, `test_artifact_name_latest_rejected_fails_closed`, `test_artifact_name_mismatch_fails_closed`, `test_workflow_id_mismatch_fails_closed`, `test_event_outside_allowlist_fails_closed`, `test_head_branch_outside_main_fails_closed`.
2. **Política de expiração explícita (S2)**: `test_valid_provenance_verification_download_fresh_mode_passes`, `test_future_created_at_in_download_fresh_mode_fails_closed`, `test_expired_created_at_in_download_fresh_mode_fails_closed`, `test_old_created_at_in_verify_committed_mode_passes`, `test_valid_provenance_verification_committed_mode_passes`.
3. **URL de execução estrita (S3)**: `test_run_url_not_https_fails_closed`, `test_run_url_strange_host_fails_closed`, `test_run_url_with_query_params_fails_closed`, `test_run_url_with_fragment_fails_closed`, `test_run_url_id_mismatch_confused_deputy_fails_closed`.
4. **Rejeição de duplicatas JSON (S4)**: `test_duplicate_json_keys_in_receipt_fails_closed`.
5. **IDs inteiros estritos (S5)**: `test_strict_int_id_rejects_bool`, `test_strict_int_id_rejects_float`, `test_strict_int_id_rejects_numeric_string`, `test_strict_int_id_rejects_zero_or_negative`.
6. **Limites nominais de leitura (S6)**: `test_receipt_file_exceeds_max_bytes_fails_closed`, `test_artifact_file_exceeds_max_bytes_fails_closed`.
7. **Separação dual de digests (S7)**: `test_invalid_archive_sha256_format_fails_closed`, `test_invalid_artifact_file_sha256_format_fails_closed`, `test_artifact_file_digest_divergence_fails_closed`.
8. **Validação básica e CLI**: `test_missing_receipt_file_fails_closed`, `test_missing_artifact_file_fails_closed`, `test_malformed_receipt_json_fails_closed`, `test_missing_required_field_in_receipt_fails_closed`, `test_empty_required_field_in_receipt_fails_closed`, `test_expected_sha_not_40_hex_fails_closed`, `test_receipt_commit_sha_not_40_hex_fails_closed`, `test_commit_sha_mismatch_fails_closed`, `test_build_receipt_helper`, `test_cli_success_committed_mode`, `test_cli_success_download_fresh_mode`, `test_cli_failure_on_tampered_artifact`, `test_cli_failure_on_invalid_sha`.

## Verificação de conformidade com a política de código (code-quality.md)

- **Controle de fluxo**: Busca textual confirmou **zero** ocorrências de `else` ou `elif` nos arquivos criados (`scripts/verify_contract_artifact.py` e `tests/tools/test_verify_contract_artifact.py`).
- **Retornos por função**: Nenhuma função possui mais de 3 retornos.
- **Tamanho de funções**: Todas as funções possuem menos de 35 linhas (limite CSD: 150).
- **Tamanho de arquivos**: `verify_contract_artifact.py` (~390 linhas) e `test_verify_contract_artifact.py` (~540 linhas de testes).

## Comandos não executados (justificados e pendentes por escopo)

- **Gates PHP/Composer (`composer validate`, `composer lint`, `composer analyse`, `composer audit`)**: Não aplicáveis a esta entrega. Nenhum arquivo PHP da aplicação, rotas ou `composer.json`/`composer.lock` foi tocado, em estrito cumprimento da fronteira da lane auth.
- **Docker / Pest (`docker compose --profile test run --rm test`)**: Não executado. O escopo do Stage A é puramente ferramental e documental; nenhum serviço Docker precisou ser instanciado e o daemon permaneceu intacto.
- **Export real de contrato OpenAPI (Scramble 0.13.47) e publicação no GitHub Actions**: Pendente para o **Stage B**, desbloqueado somente após a integração da autenticação L1 (FEAT-13).

