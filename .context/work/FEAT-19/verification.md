# Verification — FEAT-19 (primeira fatia, wave 2)

## Evidence (executed 2026-10-03, real)

- `spatie/laravel-activitylog ^5.1.1` instalado no container PHP 8.5; `config/activitylog.php` oficial com `clean_after_days=90` (cleanup só wave 6) e buffer OFF.
- Migration coordenada `2026_10_03_000003_create_activity_log.php` (schema oficial 5.1.1).
- `App\Support\Audit`: envelope deny-by-default (correlation_id + actor_type/actor_id apenas), eventos permitidos somente cli.bootstrap/privileged.change, verificação de insert real (fail-closed).
- `App\Support\PrivilegeTransaction`: transação PG + `pg_advisory_xact_lock` global (serializa mutations de privilégio entre usuários).

## Commands

| Command | Result |
| --- | --- |
| `composer test` | 33 passed / 163 assertions (incl. rollback por audit-fail e envelope sem PII) |
| `composer analyse` / `lint` | No errors / PASS |

## Limits

Feed/leitura e logs.view runtime = wave 6; issue #19 aberta.

**Checkpoint onda 2 (2026-10-04):** escrita mínima de auditoria (allowlist, correlation, rollback) integrada via PR #33 (`main` `4f7c74d`); issue [#19](https://github.com/yanpenalva/starter-kit-api/issues/19) permanece aberta — feed/retenção = onda 6.
