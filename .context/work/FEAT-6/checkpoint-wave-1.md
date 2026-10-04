# Wave 1 checkpoint

## Gate accepted — 2026-10-03

- API foundations: PR #32, head `31a368f9caa5eb595b483ba55f7540d41a6339e7`, main merge `065ff90f69becdbeca49f144e3c6cfa70a761629`. Independent review PASS after CPF, production-fixture and tmpfs isolation corrections. PR context/application/security checks passed; main context/application passed (run 37153482590, jobs 111291963059 and 111291962959). PHP 8.5 suite: 17 tests/74 assertions, real HTTP CSRF/cookies/CORS/throttle and isolated PostgreSQL constraints/concurrency.
- SPA scaffold: PR #24, main `384b968a98506868b501ed2199829035056e21cb`; PR checks passed, main verify passed (run 37152792885, job 111289934594). SPA #5 closed and Done.
- SPA layouts/tokens: PR #25, head `184663d04b3cb79d9f10b01cd277d530de6953da`, main `f59ba30d3c035059a08f73ee22e43233ab484f7f`. Independent review PASS; PR verify/security passed; main verify passed (run 37154684969, job 111295553721). Five component tests, real local Chrome responsive/focus/font/glyph/network smoke. SPA #6 closed and Done.
- Board: 10 of the 47 planned issues Done. API #9/#10 remain In progress: RBAC/audit/seeders and production configuration are future slices. SPA journey/test/CI work remains progressive; no authentication, CRUD or real API/browser authentication E2E is claimed complete.
- Local strict CSD validation passed on integrated revisions. No force push, administrative bypass, author approving review, production deployment or real administrator promotion occurred.

## Next gate

Wave 2 may be planned after the human decision on initial role/permission/event names and bootstrap operational defaults. Actual wave-2 code still requires scoped CSD planning and independent preflight. Preserve accepted account, CPF and privacy invariants; audit diff allowlist remains empty until separately approved.
