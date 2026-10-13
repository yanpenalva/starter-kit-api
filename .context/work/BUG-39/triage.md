# Support Triage — BUG-39

## Classification

- Type: reproducible security bug found during review of optional admin MFA.
- Reporter/evidence: user supplied four blocking review findings on 2026-10-12.
- Triage owner: yanpenalva.
- Active production impact: none reported; this is pre-release branch work.
- GitHub issue: NOT FOUND in the repository issue listing; no issue was created.

## Impact

- Affected surface: login, protected session routes, admin MFA enrollment and one-time recovery codes.
- Risk: high; incomplete MFA enforcement could leave existing sessions authenticated and recovery codes exposed or reusable.
- Containment: none required; work is confined to `feat/slice-6-admin-mfa-optional`.

## Delivery authorization

- User explicitly authorized commit and push after lint, analyse and Pest pass.
- Branch: `feat/slice-6-admin-mfa-optional`; target: `main`; remote: `origin`.
- Do not create a PR or merge. `EXC-0001` is recorded for the roadmap branch delivery authorization.

## Follow-up triage — contract freshness (2026-10-13)

- Reporter/evidence: user reports PR #84 still fails the Contract gate after snapshot refresh commit `ebb58a6`.
- Latest branch run observed through GitHub Actions API because `gh` is not installed: run `38331391758`, head `ebb58a61d086bf4e07c8668b75778317429675bd`, conclusion `failure`.
- Failing job/step: `Contract generation and compatibility` / `Committed snapshot freshness gate`. The two generated exports compared equal; the committed snapshot comparison reported `storage/app/openapi-ci1.json contracts/openapi.json differ: char 36653, line 847`.
- Application, coverage, context, lint, analysis and test jobs in that run passed. Active production impact: none reported; PR #84 is open pre-release work.
- The user directs merging updated `main` containing PR #87 before correcting this mismatch. Fetched merge commit: `9f3ee254a651dbd8cf61eb0fc7d0b65e928a0fa7`.
- After that merge, two parsed JSON nodes differ: `POST /api/v1/login` lacks the generated `200` response `{ requires_mfa: boolean }`, and `GET /api/v1/auth/mfa/status` declares `mfa_enabled` as `string` instead of `boolean`. The older run's first mismatch at line 847 is where the generated login `200` response appears before the snapshot's `403` response.
- The `ebb58a6` head is a merge commit, and its contract still differed from the exporter. A direct Artisan invocation also resolves `AppServiceProvider` from `/opt/data/work/starter-kit-api` because `vendor` is a symlink whose Composer autoload map is rooted there. Inference: a prior plain local export through that symlink may have used a different worktree; this correction explicitly routes application autoloading to this checkout.
