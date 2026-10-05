# Publication Evidence — FEAT-11

## Verified workflow artifact

- Source commit: `e646c34a280b851847676af290494e872edcaa9b` on `main`.
- Workflow: `OpenAPI Contract`, `.github/workflows/openapi.yml`, ID `374937885`, state `active`; inspected at the source commit. Workflow is limited to `workflow_dispatch`, guards `refs/heads/main`, grants `contents: read`, pins Scramble `v0.13.47`, performs two byte-identical exports, and names artifact using full commit SHA.
- Dispatch: `workflow_dispatch`, reason `Publicação controlada para verificação de proveniência do contrato FEAT-11`.
- Run: [37255680565](https://github.com/yanpenalva/starter-kit-api/actions/runs/37255680565), created `2026-10-05T02:30:35Z`, attempt 1, conclusion `success`, head SHA exactly `e646c34a280b851847676af290494e872edcaa9b`. All steps in its sole job completed successfully.
- Artifact: ID `11321734331`, name `openapi-e646c34a280b851847676af290494e872edcaa9b`, 2044 bytes, created `2026-10-05T02:31:08Z`, not expired, expires `2027-01-03T02:30:36Z`. Provider digest: `sha256:0d019b1adfc1d9d3f3fb65f0544d15fcc0d51a7361e23508204c8e2a5646a6fe`.
- Downloaded archive SHA-256 independently recomputed to the same digest. Archive contained one root `openapi.json`; extraction used `scripts/contract_extract.py` safety checks with 10 MiB total and 64 KiB member limits, and rejected duplicate JSON keys and external `$ref` values.
- Extracted contract: 21,323 bytes, SHA-256 `ed772d267e56784b9a847743575a242b049697ffe35369c541913903c0b24914`; matches `contracts/openapi.json` at the source commit byte-for-byte by size and digest.
- Receipt: `/tmp/opencode/luna-contract-publication/worker-artifacts/run-37255680565/receipt.json`. Import file and raw provider archive are alongside it as `openapi.json` and `artifact.zip`. `python3 scripts/contract_cli.py --artifact-file /tmp/opencode/luna-contract-publication/worker-artifacts/run-37255680565/openapi.json --receipt-file /tmp/opencode/luna-contract-publication/worker-artifacts/run-37255680565/receipt.json --expected-sha e646c34a280b851847676af290494e872edcaa9b --mode download-fresh --expected-workflow-id 374937885 --allowed-events workflow_dispatch` returned SUCCESS.
- Context preflight: `python3 scripts/validate_context.py --strict` and `python3 scripts/validate_context.py --strict --examples` both passed before this documentation update; second emitted expected warning that no example suite is installed.

## Scope and remaining acceptance evidence

This records successful generation and authenticated GitHub Actions artifact retrieval, plus test evidence that configured docs UI/JSON middleware denies unauthorized roles when its lazy environment check evaluates as `production`. It does not establish frontend type generation/consumption, a fresh production boot, live production routing/deployment, browser E2E, production publication or completion of API #11. Issue #11 remains open and active; parent owns final integration and closure after remaining acceptance gates are evidenced.

Issue progress comment: https://github.com/yanpenalva/starter-kit-api/issues/11#issuecomment-5987126007. Issues #10 and #24 were observed open on 2026-10-05 and were not changed. Issue #38 is already closed by PR #38 and is outside this FEAT-11 publication scope.

Documentation delivery branch: `docs/wave-3-contract-published` → `main`, remote `origin`; roadmap delivery authorization EXC-0001 applies. No application deployment or production operation occurred.
