# HTTP API Contract

This file defines the canonical HTTP behavior and compatibility policy. The generated OpenAPI snapshot is [`../../contracts/openapi.json`](../../contracts/openapi.json); `.context/project/openapi.yaml` remains the historical initial draft and is not a substitute for the generated contract. Authentication, administration, audit, Scramble export, artifact publication, and compatibility checks are implemented and have owning work-item evidence.

This documentation branch is based on the updated main that includes PRs #79, #83, and #86. Their role-grant, active-session, and custom-role changes are reflected in the committed API snapshot. The branch leaves `contracts/openapi.json` unchanged. ADRs 0010–0012 record the related decisions and identify implementation and integration evidence by slice.

## Transport and responses

- Serve JSON under `/api/v1`; require JSON request bodies for operations that accept a body and return `application/json` for API responses.
- Give each endpoint an explicit Form Request for input validation and a Resource for its output; Resources enumerate public fields and never serialize an Eloquent model directly. Keep Controllers thin and put use-case logic in an Action only when it needs its own boundary or meaningful orchestration.
- A single resource response uses a `data` object. Collections use `data` plus `links` and `meta`; do not expose SPA component or Quasar table shapes.
- Use 200 for a returned representation, 201 for a created resource, and 204 when an operation has no response body.
- Validation errors use HTTP 422 with a JSON object containing `message` and field-keyed `errors`. Error messages are display text, not stable machine identifiers. Do not include input values, credentials, or sensitive data in errors.
- Use 401 for missing/invalid authentication, 403 for an authenticated but explicitly blocked account or denied action, 404 for an unavailable resource, 409 for a state conflict, 419 for invalid/missing CSRF, and 429 for throttling. Authentication errors must not reveal whether an account exists. A throttled response includes `Retry-After` when Laravel provides it.
- Timestamps are RFC 3339 UTC strings. The authentication profile omits CPF; credentials and sensitive inputs never appear in public errors or logs.

## Authentication contract

- HTTP authentication uses Sanctum stateful sessions, session cookies, and CSRF only. The API does not issue or accept bearer, personal access, JWT, or integration API tokens.
- Successful POST /api/v1/login returns 204; the client then loads GET /api/v1/auth/my-profile. Login creates a session and never returns an access token.
- POST /api/v1/logout invalidates the current session and returns 204; no separate integration-token authorization exists.
- GET /api/v1/auth/sessions lists only the signed-in user's sessions. DELETE /api/v1/auth/sessions/{id} revokes one of that user's sessions; DELETE /api/v1/auth/sessions revokes every other session and keeps the current one. Both DELETE routes allow 10 requests per minute per user and then return 429.
- Active-session listing/revocation and invalidation after password reset or account suspension require `SESSION_DRIVER=database`. `sessions.id` remains Laravel's opaque string ID; `sessions.user_id` matches the bigint `users.id`.
- A newly registered, not-yet-verified user may establish a limited session and read a minimal profile for verification. Business routes remain denied until email verification automatically activates the basic profile. An explicitly deactivated account receives 403. This distinction was approved by yanpenalva on 2026-10-02 and clarifies API #13 against the accepted ADR 0004.
- The authenticated session is the sole authority for the profile subject; client-supplied IDs, roles, or permissions never select the user or grant authority. The profile endpoint reloads the session user and already-loaded RBAC relations before eligibility checks and serialization, so reads reflect persisted identity, role, permission, and deactivation changes. Before verification, roles and permissions are empty. CPF, credentials, and remember tokens are excluded; administrative CPF access follows ADR 0004.
- Production CORS origins, session domain, lifetime, SameSite policy, and TLS termination are `NOT FOUND` and must be resolved before production auth deployment.

## SPA origins and email links

- Configure trusted SPA origins and API URLs explicitly per environment. Credentialed CORS must use exact allowed origins; do not derive trust from the request `Host` header. Production values remain `NOT FOUND`.
- Verification and password-reset messages link to routes on the configured SPA origin and use the API's callback/validation flow. Construct the final URL before signing or encoding it; never alter a signed URL afterward. Exact routes, payloads, expiry, and replay behavior belong to API #14 and #15.
- Do not put passwords, CPF, or reusable credentials into links, logs, analytics, or public error responses. The mail transport and production delivery policy remain owned by the corresponding feature.

## Administration, filters, and pagination

- User and role administration uses the session-only Sanctum guard, permission gates, and policies. Mutating Actions repeat the relevant policy inside `PrivilegeTransaction`; PostgreSQL serializes these mutations with its advisory transaction lock. Slice 11's broader actor-refresh hardening remains separate from the current main implementation, so do not assume every action reloads its actor before the policy check.
- Non-admin actors may assign only roles whose permissions are all included in their own effective permissions; the role set is checked as a whole. Role detail and custom-role rename are available. Built-in `admin` and `user` names remain reserved, role permission assignments survive a rename, and deleting an assigned role is denied.
- `GET /api/v1/roles/{id}` requires `roles.view`; `PATCH /api/v1/roles/{id}` and `DELETE /api/v1/roles/{id}` require `roles.manage`. Rename accepts a unique name up to 255 characters. The `admin` role cannot be renamed or deleted, and assigned roles cannot be deleted.
- The audit feed filters by allowlisted event and subject types, date range, and causer; it does not accept an action-code filter. Audit payload properties and serialized descriptions are restricted by ADR 0005; audit-write failure rolls back the privileged mutation.
- Collections accept `page` (positive integer, default 1) and `per_page` (integer 1–100, default 15). Resource collections use the Laravel `data`/`links`/`meta` envelope. The generated contract documents nullable navigation URLs/pages and the standard pagination metadata; use the snapshot for the exact shape.
- Filters are endpoint-specific: users accept `search`, `status`, and `role`; roles accept `search`; the audit feed accepts `event`, `subject_type`, `from`, `to`, and `causer_id`. Requests validate accepted values and ignore unrecognized query keys; clients never choose SQL columns or operators.
- Ordering is server-defined and stable: users and roles by ascending `id`, audit entries by descending `id`. The API does not accept client-selected `sort` or `direction` fields.

## Availability and compatibility

- `GET /api/v1` is a minimal availability response. Laravel's `/up` is liveness: it only indicates that the application can answer. No readiness endpoint or database-readiness contract exists yet; do not expose connection, host, or infrastructure details.
- Adding an endpoint or optional response field is compatible. Removing or renaming a field, making an optional input required, or changing status/authentication semantics is breaking and requires a new major API path or an explicit migration plan.
- OpenAPI 3.1 is generated by Scramble `0.13.47` from implemented Controllers, Form Requests, Resources, and the contract transformer. `Quality` runs deterministic export, snapshot freshness, structural validation, and breaking-change comparison with pinned `oasdiff 1.33.0`.
- `.github/workflows/openapi.yml` is a manual producer guarded to `main`; it exports twice and publishes `openapi-<full-sha>` for 90 days. A feature branch may generate a local candidate snapshot for review and the `Quality` gate, but that candidate is not the official artifact. After integration, run the producer on `main` and inspect its artifact; versioned snapshot changes remain part of a reviewed change.
- The [contract release guide](../../docs/contract-release.md) records the operator steps, and the [provenance recipe](../../docs/openapi-contract-recipe.md) covers artifact verification; CI and producer definitions remain the executable source for workflow behavior.

## Evidence and decisions

- API Issue [#3](https://github.com/yanpenalva/starter-kit-api/issues/3) defines the initial conventions and login/profile surface.
- API Issue [#13](https://github.com/yanpenalva/starter-kit-api/issues/13) defines the exact login, profile, logout, session, and failure-status paths.
- Accepted architecture: [ADR 0002](../../docs/adr/0002-autenticacao-spa.md), [ADR 0003](../../docs/adr/0003-persistencia-contratos.md), and [ADR 0004](../../docs/adr/0004-identidade-rbac.md), with current successor records [ADR 0010](../../docs/adr/0010-autenticacao-http-somente-sessao.md), [ADR 0011](../../docs/adr/0011-rbac-auditoria-e-roles-customizadas.md), and [ADR 0012](../../docs/adr/0012-contrato-openapi-e-release.md).
- Laravel 13 documents Sanctum SPA authentication as cookie/session based and separate from personal access token authentication: [Sanctum SPA authentication](https://laravel.com/framework/docs/13.x/sanctum#spa-authentication).
- Scramble documents OpenAPI 3.1 generation and its current Composer manifest declares Laravel 13 compatibility: [Scramble](https://github.com/dedoc/scramble), [composer.json](https://github.com/dedoc/scramble/blob/main/composer.json).

**Review state (historical):** initial conventions were accepted and integrated via [PR #30](https://github.com/yanpenalva/starter-kit-api/pull/30); API #11 contract generation and compatibility are recorded as completed in [FEAT-11](../work/FEAT-11/work-item.json). The limited pre-verification session rule remains approved. Production CORS origins, session domain/lifetime, TLS termination, and production proxy configuration remain `NOT FOUND` until deployment planning.

## Private OpenAPI transport policy (approved 2026-10-03)

yanpenalva approved this policy via the question tool ("Aprovar transporte") on 2026-10-03. Routine SHA/workflow pins need no new human choice; CI credential provision remains a human decision. The API producer exists in .github/workflows/openapi.yml and is limited to workflow_dispatch on main. Quality validates the committed snapshot; the API producer has no cross-repository write permission.

### Producer (API repository; API #11)

- Generate the OpenAPI document only from real implemented Controllers, Form Requests and Resources, in a successful trusted run on `main` at an exact full revision SHA that is already reviewed and integrated under the recorded delivery authorization.
- Identify the run by an allowlist of trusted workflow id/path/triggering event; verify the artifact by id, name and digest.

### Consumer (frontend repository)

- Consume only a durably committed OpenAPI document plus generated types and a provenance receipt. Frontend CI runs on every PR build/test with NO API credential, including fork and Dependabot builds.
- Normal CI never downloads the artifact; this avoids per-PR retention failures and keeps credential-free builds.

### Synchronization and credentials

- Synchronization uses the local orchestrator with the existing authorized `gh` CLI as the active transport path for private repositories; this avoids provisioning a new cross-repository PAT for the consumer. The consumer commits the OpenAPI spec, generated TypeScript types, and provenance receipt directly into its repository. Default CI builds (PR, forks, Dependabot) run entirely credential-free.
- The built-in GitHub token (`GITHUB_TOKEN` `contents:read`) is repository-scoped and cannot cross repositories. Fine-grained PAT with `Actions:read` is not required under the local orchestrator transport route.
- Out of scope: reading source contents, classic PATs, new identities, ruleset changes, auto tags and bot PR settings. Candidate contract outputs follow the normal branch commit/PR route by the orchestrator.

### Fail-closed verification and records

- Caller inputs: the expected API SHA taken from a prior reviewed merge (exact 40-character hex SHA from `main`), and a logical contract version (not a Git tag or `latest`). Verification fails closed unless: the run completed successfully; the head SHA matches exactly; the workflow name, path, and event are allowlisted and trusted; the artifact is not expired; the archive digest matches; and the OpenAPI file SHA-256 is recomputed independently. Absent or expired artifacts fail at first fetch (fetch-time enforcement). Never use `latest`, never widen the check, never fall back to manually maintained DTOs.
- Dual-digest separation (ARCHIVE digest ≠ FILE hash): The archive digest (`archive_sha256`, ZIP from GitHub Actions provider) and the extracted OpenAPI file hash (`artifact_file_sha256`) are stored separately in the receipt and both validated. Never copy the ZIP archive digest as the OpenAPI file hash. The file hash is independently recomputed from the extracted spec file stream.
- Record per delivery: API repository SHA, workflow run id, path and workflow SHA, artifact id, archive digest (`archive_sha256`), file hash (`artifact_file_sha256`), contract version, generator version (`dedoc/scramble 0.13.47`), plus the SPA paired SHA and provenance. The archive digest proves transfer integrity, not authorship attestation.
- Receipt metadata authentication vs attestation: The receipt authenticates GitHub Actions workflow run and artifact metadata; it does NOT implement cryptographic attestation or signing (no signing/notary is implemented at Stage A).
- Explicit expiration policy (fetch-time enforcement): Two operational modes are supported in `scripts/contract_cli.py`:
  1. `--mode download-fresh`: enforced during the initial fetch by the local orchestrator, applying `max_age` (default 90 days matching GitHub Actions retention) against a reliable UTC clock. Timestamps in the future beyond a 300-second skew tolerance fail closed immediately.
  2. `--mode verify-committed`: used during consumer CI runs over durably committed contract files. No age expiration is evaluated, ensuring approved, committed specifications do not break CI randomly.
- Recovery is limited to an API-owner rerun (Actions:write via the API-authorized identity, never the SPA token) or a newly explicit reviewed SHA. Never pretend that a workflow dispatch can reproduce an old `main` historical SHA arbitrarily.
- Stage B deterministic export requirement: the producer export pipeline must execute under `LC_ALL=C` and enforce 2x idempotent export verification before generating the artifact hash.
- Contract scope discipline: the generated artifact reflects real implemented routes. `/up` is an operational liveness probe, not an application contract; it may be included in the document but does not promise database or dependency readiness.

### Scope limits and current assumptions

- Retention: the producer artifact retention is 90 days; absent or expired artifacts fail the first fetch closed.
- Production and backups are out of scope.
- Scramble version pin: `dedoc/scramble 0.13.47` installed (exact pin; lock ref `d1597812fa8ce40a257d83c527bec28cb60de7a5`). FEAT-11 Stage B and its consumer acceptance are recorded complete; the later envelope refinements tracked by Slice 9 are not in this main snapshot and require a reviewed snapshot update after integration.
- Producer workflow: `.github/workflows/openapi.yml` (`name: OpenAPI Contract`) accepts only `workflow_dispatch` on `main`, enforces `LC_ALL=C` and two byte-identical exports, and uploads `openapi-<fullSHA>` for 90 days. The receipt is constructed by the operator after trusted workflow/artifact metadata and raw ZIP verification; it is not placed inside the ZIP being verified.
- At this branch's base, `origin/main` includes PRs #79, #83, and #86 and their reviewed snapshot updates. The snapshot remains producer-owned: a feature branch may propose a candidate for review, but cannot publish the official artifact. The contract uses session-cookie and XSRF schemes only; Bearer/PAT/JWT are forbidden.
- Sources: [fine-grained PAT permissions](https://docs.github.com/en/rest/authentication/permissions-required-for-fine-grained-personal-access-tokens), [GITHUB_TOKEN security](https://docs.github.com/en/actions/concepts/security/github_token), [security hardening](https://docs.github.com/en/actions/reference/security/secure-use), [workflow runs API](https://docs.github.com/en/rest/actions/workflow-runs), [artifact retention](https://docs.github.com/en/organizations/managing-organization-settings/configuring-the-retention-period-for-github-actions-artifacts-and-logs-in-your-organization), [gh run download](https://cli.github.com/manual/gh_run_download).
