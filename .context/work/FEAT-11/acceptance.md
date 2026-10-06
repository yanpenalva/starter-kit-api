# Acceptance — FEAT-11 vs API issue #11 (delivered `main` 0af2755)

Source: immutable `main` at `0af27555510f2943896005c6bf7d027c2efb2433` (normal merge of PR #41). Pre-merge docs head `0394c1cf1f48026b8b36a4b06ff97f2a959e5f2a`; contract publication source `e646c34a280b851847676af290494e872edcaa9b`. The verdicts below are CSD evidence only and reuse cited parent/CI evidence rather than re-running the producer pipeline. The human owner closed API issue #11 as completed; this document does not itself close anything.

## Scope bullets (issue body)

- Install compatible Scramble and export OpenAPI from real Controllers, Form Requests and Resources: YES. `dedoc/scramble 0.13.47` pinned in `composer.lock`; deterministic export (`LC_ALL=C`, `TZ=UTC`) produced `contracts/openapi.json` (21,323 bytes).
- Document cookie/CSRF and use an appropriate security scheme; never label PAT as JWT: YES. `sessionCookie` modeled as `apiKey` in cookie with `xsrfToken`; session-based security, no Bearer/PAT scheme in the exported contract.
- Establish contracts for filters, pagination, timestamps, permissions and error format: YES. Overlay/response factory define `429` with `Retry-After` on API routes and `204` on the CSRF route; response examples preserved.
- Review docs/UI protection in production and publish a versioned artifact consumable by the frontend without repo coupling: YES. Request-time simulated production policy denies non-admin/anonymous (`403`) and allows active verified admin (`200`); the published artifact is consumed by the frontend from the pinned snapshot without a live API fetch in frontend CI.
- Add incompatible-change verification and syntactic validation in CI: YES. `scripts/contract_compat.py` with pinned `oasdiff 1.33.0`, dynamic baseline resolution and fail-closed bootstrap; wired into `.github/workflows/quality.yml`.

## Acceptance criteria (issue checkboxes)

- [x] OpenAPI valid for all created endpoints and precise security scheme. Evidence: real-document export asserted against actual routes `/api/v1`, `/api/v1/login`, `/api/v1/logout`, `/api/v1/auth/my-profile` and `/sanctum/csrf-cookie`; main CI run 37249567434 context/application/contract success.
- [x] Frontend can generate types from the published artifact. Evidence: frontend PR #26 merged (see consumer section below); published JSON SHA-256 `ed772d267e56784b9a847743575a242b049697ffe35369c541913903c0b24914` preserved end-to-end.
- [x] Documentation protected in production per policy. Evidence: framework-level request-time simulation of the real Scramble middleware and `viewApiDocs` gate with `APP_DEBUG=false`; not a real deploy, infrastructure or browser test.

## Parent review and integration evidence

- PR #41 final parent gate comment (id `6005907305`, 2026-10-05T23:56:37Z) approves head `0394c1c...`: independent Luna re-review PASS by review session `ses_ef1b3181fffe3pkzZdomyhtbtH`, with exact host allowlist and raw `DB_URL` presence in `getenv`/`ENV`/`SERVER` verified and rejections before any SQL counter advanced and no DSN exposed. URL: https://github.com/yanpenalva/starter-kit-api/pull/41#issuecomment-6005907305
- CI on `0394c1c...`: run 37388841435 context/application/contract plus GitGuardian success. The link is a durable CI reference, not a local rerun.
- Normal (non-force) merge to `main` `0af2755...` at 2026-10-05T23:56:39Z by `yanpenalva`; no force push, rule bypass or administrative override. Prior historical FAIL at `e1f4dc1...` by the same reviewer session remains recorded in `review.md` and is not erased by this PASS.
- Published OpenAPI artifact (immutable baseline, not republished): main `e646c34...`, workflow 374937885, run 37255680565, attempt 1, artifact id 11321734331, 21,323 bytes, JSON SHA-256 `ed772d267e56784b9a847743575a242b049697ffe35369c541913903c0b24914`, ZIP SHA-256 `0d019b1adfc1d9d3f3fb65f0544d15fcc0d51a7361e23508204c8e2a5646a6fe`. The stored contract is byte-identical to this artifact.

## Consumer acceptance evidence (frontend)

- Frontend PR #26 merged into frontend `main` `dae91ec05fd0524be0a50c87578418ed70f943b3`; reviewed source head `974f86243b2d475fe274f95001df748bb1b0a5cb`; merged 2026-10-06T02:21:55Z by `yanpenalva`. URL: https://github.com/yanpenalva/starter-kit-frontend/pull/26
- Independent frontend review PASS with 0 findings; the reviewer reproduced 133/133 Vitest plus 8 own probes, 18/18 Python, a 16-case receipt/pin adversarial matrix, real bundle hashes and a byte-identical Orval 8.39.0 regeneration (24 files / 710 lines).
- CI on the reviewed source head: runs 37402507064 and 37402507083; GitGuardian success. Published JSON SHA-256 `ed772d...` retained.

## Explicit non-requirements and limitations

- No browser-driven E2E. Acceptance is framework-level request-time simulation of the code policy, not a live server, proxy/CDN, cookie/session over the wire, or production secrets.
- No production deploy, infrastructure validation or production publication is claimed. The simulation-production policy documented in `release.md` is not actual deploy infrastructure.
- No GitHub approving review was fabricated. The author account never approved its own PR; producer and independent reviewer identities are recorded separately here.
- The auth/wave-3/browser-real fronts are distinct and are not closed by this acceptance; they are listed in `outcome.md`.

## Remaining tasks (distinct, not delivered or closed by this acceptance)

- Wave-3 auth lane (sessions, guards, permissions) remains a separate front; this item delivered only the OpenAPI contract/publication and docs-access policy.
- Wave-3 overall completion is not claimed by this item; the wave remains in progress.
- Browser-real E2E against a running API is not performed.
- Production deploy, infrastructure and production secrets validation remain unclaimed and require a separate human decision gate.
