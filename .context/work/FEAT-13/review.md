# Review — FEAT-13

## Independent Review & Scope Resolution Status

- **Status:** **PENDING** Independent Oracle / Parent Review.
- **Oracle Review (ora2) at commit `c6577fe`:**
  - Evaluated candidate at `c6577fe84172d5caac7889aa2c3583d2052ab5ec` (82 tests, 526 assertions).
  - Outcome: PASS CONDITIONAL on fixing findings M1 and M2, alongside recommendations L3 and L4.
  - **M1 (Sanctum PAT Token Fallback 500):** Resolved. Added `DisallowHeaderAuthentication` middleware rejecting non-empty `Authorization` header with 401 fail-closed. Proved via `DB::listen` that 0 queries are executed against `personal_access_tokens`.
  - **M2 (PostgreSQL vs PHP Whitespace Trimming Asymmetry):** Resolved. Aligned database expression index and collision check with PHP `trim()` using `lower(btrim(email, E' \t\n\r\x0B'))`. Updated `LoginController` to query with matching expression, and added tests covering whitespace and case variants.
  - **L3 (Redundant Middleware Registration):** Resolved. Removed manual `api` group override in `bootstrap/app.php`, relying on `$middleware->statefulApi()` and `$middleware->throttleApi()`.
  - **L4 (Trusted Proxies Limitation Documentation):** Resolved. Documented in `auth.md` that trusted proxies are empty by default and must be explicitly configured in production reverse-proxy environments.
- **Ownership Resolution (ScaffoldTest):** Candidate commit `c4eacbfbad11b909bcb0259772f622c0c8f9bdf7` included a 7-line edit to `tests/Feature/ScaffoldTest.php` updating the strict public surface route assertion to include `api/v1/login`, `api/v1/auth/my-profile`, and `api/v1/logout`. While initially flagged as an ownership boundary BLOCKER due to falling outside `tests/Feature/Auth/**`, this has now been explicitly authorized by parent decision as a relevant API #13 ownership resolution. The diff against base `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` was verified to add strictly those three routes with zero removals or weakening. The blocker is **RESOLVED**.
- **Candidate Delivery Mode:** Candidate branch `feat/wave-3-api-auth` targeting `main` on origin `git@github.com:yanpenalva/starter-kit-api.git`.
- **Review Authority:** Independent technical oracle/parent review remains PENDING before integration. AGY does not self-approve. Wave-3 browser E2E gate is NOT covered and NOT claimed PASS.
- **Merge/PR Authority:** Strictly NO PR/merge per user instructions. EXC-0001 authorized for candidate branch commit/push only.
