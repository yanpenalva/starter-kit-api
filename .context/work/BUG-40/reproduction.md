# Reproduction — BUG-40

## Observed behavior

- O run Quality #192 falha em `application` e `coverage` no teste `audit defines distinct action codes allowlist`, em `tests/Feature/Admin/AuditDistinctActionsTest.php:40`. A lista esperada omite `Audit::ACTION_ROLE_RENAME`, que já pertence à allowlist do código após a integração de role renaming.
- O gate `contract` exporta duas vezes de forma byte-idêntica, mas o snapshot commitado difere do export na linha 546.
- O gate `context` passou.

## Expected behavior

- A expectativa do teste corresponde exatamente à allowlist vigente.
- A branch contém o snapshot versionado e determinístico produzido no fluxo oficial em `main`.
- Os gates `application`, `coverage` e `contract` passam na revisão atualizada da PR #85.

## Steps and evidence

1. Consultar a PR #85 e o run Quality `38331058231` no GitHub Actions.
2. Consultar os jobs `application` (`115080648821`), `Coverage gate (pcov, min 85%)` (`115080648682`) e `Contract generation and compatibility` (`115080648839`).

Evidence: https://github.com/yanpenalva/starter-kit-api/actions/runs/38331058231

## Scope

- Reproducibility: determinística nesse SHA.
- Affected versions/environments: CI da PR #85, PHP 8.5, PostgreSQL 18.
- Suspected boundary: expectativa da allowlist e snapshot OpenAPI desatualizados após integração da `main`.
