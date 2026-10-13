# Verification — BUG-39

## Evidence

- Exact commands and checks:
  1. GitHub Actions Quality run `38331058231` (baseline SHA `203fc7a77d57c9a0418f7071c0017a40ba54f640`): application job failed at `AuditDistinctActionsTest`; coverage job stopped on the same Pest failure; contract job's deterministic double export passed, then snapshot freshness failed. Context passed.
  2. `vendor/bin/pest --configuration=/tmp/codex-api-g7-phpunit-local.xml` before correction: 1 failed / 12 passed / 46 assertions; the sole assertion showed actual `role.rename` omitted from expected action list.
  3. `vendor/bin/pest --configuration=/tmp/codex-api-g7-phpunit-local.xml` after correction: 13 passed / 46 assertions, exit 0.
  4. `composer lint` (PHP 8.5 environment loaded from `/opt/data/php/env.sh`): Pint passed, exit 0.
  5. `python3 scripts/validate_context.py --strict`: passed, exit 0.
  6. `git diff --check`: passed, exit 0.
  7. `sha256sum contracts/openapi.json`: `88bcccf993dd74915da9db2dd1a4eaaba1f4354b616054fe8b74c70042a3fb66`, matching PR #87's artifact hash; the file content and Git blob match `origin/main`.
- Scope analyzed: merge of `origin/main` at `9f3ee254a651dbd8cf61eb0fc7d0b65e928a0fa7`; allowlist assertion; official OpenAPI snapshot.
- Results and exit codes: local focused Pest 0; lint 0; context validation 0; diff check 0. Remote verification on the new PR SHA remains pending push and CI.
- Date and environment: 2026-10-13, PHP 8.5.11, Linux worktree, SQLite in-memory for focused Pest; Composer dependencies read through the supplied `vendor` symlink. No Composer install was run.
- Metrics and thresholds: CI coverage gate has an 85% total-line floor; baseline run failed before a coverage percentage was emitted. No local coverage percentage claimed.
- Limitations or `NOT APPLICABLE` rationale: focused local Pest used SQLite, while CI uses PostgreSQL 18. The snapshot was not regenerated locally: project policy reserves that action for the OpenAPI workflow on `main`; the verified producer artifact from PR #87 was integrated unchanged. Application, coverage and contract results on the pushed SHA are not yet available.

## Review

- Request and spec aligned: yes
- Plan followed: yes
- Scope drift: none beyond the explicitly requested `main` integration
- Security impact addressed: yes; the allowlist is unchanged and the test now expects the existing `role.rename` action.
- Regression risk: low for the one-line assertion correction; upstream merge remains covered by CI.
- Core policy evidence: no runtime behavior or security policy was weakened; snapshot provenance follows the main-only producer rule.
- Quality gate result: focused Pest, Pint, context validator and diff check passed; PR CI pending.
- Security/privacy result: no credentials or private data added.
- AI-use controls and human review (if applicable): user authorized merge of `main` into this branch and push only to PR #85; no PR merge authorized or performed.
- Subtask/wave integration evidence: S1 merge `f1c36b7`; S2 assertion correction; S3 local gates complete, remote gates pending.

## Findings

- Location: `tests/Feature/Admin/AuditDistinctActionsTest.php:40`
- Problem: expected action list omitted `role.rename`, already present in `Audit::allowedActions()`.
- Evidence: run #192 and local pre-fix test showed exactly this array difference.
- Required correction: add `Audit::ACTION_ROLE_RENAME`; completed.

## Verdict

`APPROVED WITH CONDITIONS` — remote CI for the pushed SHA must pass before the PR is considered ready.
