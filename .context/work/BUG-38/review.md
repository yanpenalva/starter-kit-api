# Review — BUG-38

## Inputs

- Request: AUTH-RETRY support bug fix
- Spec: `.context/work/BUG-38/spec.md`
- Plan: `.context/work/BUG-38/plan.md`
- Diff: Mudança de 1 linha em `tests/Feature/InfraQueueFailureTest.php` e documentação de convenções em `.context/project/conventions.md`
- Verification: `.context/work/BUG-38/verification.md`
- Applicable policies: `.context/policies/core/code-quality.md`
- Exceptions: `EXC-0001`

## Correct

- A adição de `--once` elimina a possibilidade de o worker daemon loopar e capturar o delayed job antes das asserções intermediárias.
- O teste mantém a validação real com Redis, zset de delayed jobs, intervalo de espera e gravação final em `failed_jobs`.

## Problems and risks

- Priority: Low
- Location: `tests/Feature/InfraQueueFailureTest.php`
- Evidence: CI run 37232150173
- Impact: Flakiness no CI resolvido
- Recommendation: Merge após revisão independente agendada pelo parent.

## Baseline checklist

- [x] No new critical reliability or security issue.
- [x] No unexplained complexity or duplication regression.
- [x] Tests and actual command evidence are present.
- [x] Each material check records command, scope, exit code, date and limitations.
- [x] Compatibility, data and rollback risks are addressed.
- [x] Sensitive data and AI-use controls are addressed.
- [x] Subtask boundaries, wave dependencies and integration ownership are evidenced.

## Verdict

`APPROVED`
