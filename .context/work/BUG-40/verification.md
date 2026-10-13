# Verification — BUG-40

## Evidence

- Exact commands and checks:
  1. GitHub Actions Quality run `38331058231` (baseline SHA `203fc7a77d57c9a0418f7071c0017a40ba54f640`): application job failed at `AuditDistinctActionsTest`; coverage job stopped on the same Pest failure; contract job's deterministic double export passed, then snapshot freshness failed. Context passed.
  2. `vendor/bin/pest --configuration=/tmp/codex-api-g7-phpunit-local.xml` before correction: 1 failed / 12 passed / 46 assertions; the sole assertion showed actual `role.rename` omitted from expected action list.
  3. `vendor/bin/pest --configuration=/tmp/codex-api-g7-phpunit-local.xml` after correction and latest-main merge: 13 passed / 46 assertions, exit 0.
  4. `composer lint` (PHP 8.5 environment loaded from `/opt/data/php/env.sh`): Pint passed, exit 0.
  5. `python3 scripts/validate_context.py --strict`: passed, exit 0.
  6. `git diff --check`: passed, exit 0.
  7. `sha256sum contracts/openapi.json`: `4bfd37b84fdc0cf2969814639ade82b8cdbf90a6505208bd39b70eefa3346709`, equal to the snapshot in `origin/main` at `0955e1e` (PR #84 after #87).
  8. GitHub Actions Quality run `38333315743` for main SHA `0955e1eedf322cb5d1dcf258ba14c5e568cd4bc5`: `application`, `coverage`, `contract`, and `context` all passed, including double-export determinism and committed snapshot freshness.
- Scope analyzed: initial merge of `origin/main` at `9f3ee254a651dbd8cf61eb0fc7d0b65e928a0fa7`; allowlist assertion; final merge of `origin/main` at `0955e1eedf322cb5d1dcf258ba14c5e568cd4bc5`.
- Results and exit codes: local focused Pest 0; lint 0; context validation 0; diff check 0. Final PR #85 Quality run is not yet available.
- Date and environment: 2026-10-13, PHP 8.5.11, Linux worktree, SQLite in-memory for focused Pest; Composer dependencies read through the supplied `vendor` symlink. No Composer install was run.
- Metrics and thresholds: CI coverage gate has an 85% total-line floor; baseline run failed before a coverage percentage was emitted. No local coverage percentage claimed.
- Limitations or `NOT APPLICABLE` rationale: focused local Pest used SQLite, while CI uses PostgreSQL 18. The snapshot was not regenerated locally: project policy reserves that action for the OpenAPI workflow on `main`; the snapshot from current `main` is integrated unchanged. Main Quality #209 passed all gates; PR #85's final SHA still needs its own CI result.

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
- Subtask/wave integration evidence: S1 merges `f1c36b7` and `6ba1a69`; S2 assertion correction; S3 local gates complete, final PR CI pending.

## Findings

- Location: `tests/Feature/Admin/AuditDistinctActionsTest.php:40`
- Problem: expected action list omitted `role.rename`, already present in `Audit::allowedActions()`.
- Evidence: run #192 and local pre-fix test showed exactly this array difference.
- Required correction: add `Audit::ACTION_ROLE_RENAME`; completed.

## Verdict

`APPROVED WITH CONDITIONS` — remote CI for the pushed SHA must pass before the PR is considered ready.
