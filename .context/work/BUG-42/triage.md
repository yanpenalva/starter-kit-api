# Support Triage — BUG-42 Security dependency refresh (November 2026)

## Classification

- Type: bug
- Reporter: yanpenalva
- Triage owner: yanpenalva
- First observed: 2026-11-20

## Impact

- Affected users or systems: Application service dependencies and framework components
- Current impact: 9 security advisories across 6 packages in `composer.lock` (Laravel framework, Symfony http-foundation, http-kernel, mime, routing, yaml)
- Scope and frequency: Static dependencies locked in `composer.lock`
- Severity: sev3

## Immediate decision

- Containment required: no
- Escalation required: no
- Chosen path and reason: Perform in-place dependency refresh within declared compatible constraints, updating `laravel/framework` to selected locked version `13.43.0` and `symfony/*` to `v8.1.10`/`v8.1.11`. Keep `dedoc/scramble` exact pin at `0.13.49`. Minimal test-harness adjustment in `tests/TestCase.php` to guard teardown connection cleanup on strict DB mocks. The user explicitly waived a GitHub Issue and granted direct task-specific approval in this conversation on 2026-11-20 for isolated branch delivery, commits, push, PR merge into main, and auxiliary branch deletion (formalized under exception EXC-0002). Delivery branch: `security/dependency-refresh-2026-11`, target: `main`, remote: `origin`.
