# Discovery

## Problem and evidence

The active-session capability is already present on `feat/slice-5-active-sessions`, but its pending work needs non-vacuous coverage for revoked cookies, password-reset outcomes, HTTP suspension, and bulk revocation ownership. The branch also needs its database-session requirement, shared DELETE throttle, API message, and key-type contract recorded together.

The user explicitly scoped this continuation and authorized lint, analysis, Pest, then commit and push on this branch. Existing uncommitted work is part of the accepted starting point.

The user also explicitly authorized generating `contracts/openapi.json` from two byte-identical local Scramble exports under `LC_ALL=C`, following slices 1/6/7. This applies to this snapshot only; do not hand-edit it.

## Audience and expected value

Signed-in API users need to see and revoke their own sessions and know that a password reset or account suspension invalidates existing cookies. The measurable completion signal is the focused behavior suite and required quality gates passing with the API contract documenting the database session driver.

## Constraints

- Preserve the existing worktree changes and remain in `/opt/data/cache/scratch/api-fixwork2`.
- Do not add credentials or real values to `.env.example` or `compose.yaml`; do not run Composer install.
- Keep session IDs as Laravel's opaque string keys and keep `sessions.user_id` aligned with the bigint user primary key.
- Update existing PR #83 only, after gates pass. Do not create another PR or merge #83.

Related API contract: Issue #13 and `.context/project/api.md`.
