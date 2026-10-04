# HTTP API Contract Draft

This file and [`openapi.yaml`](openapi.yaml) are the canonical API contract. They define the proposed v1 conventions and the initial login/profile surface before those endpoints exist. Runtime authentication, Scramble generation, artifact publication, and CI compatibility checks remain unimplemented and belong to API Issues #11 and #13.

## Transport and responses

- Serve JSON under `/api/v1`; require JSON request bodies for operations that accept a body and return `application/json` for API responses.
- Give each endpoint an explicit Form Request for input validation and a Resource for its output; Resources enumerate public fields and never serialize an Eloquent model directly. Keep Controllers thin and put use-case logic in an Action only when it needs its own boundary or meaningful orchestration.
- A single resource response uses a `data` object. Collections use `data` plus `links` and `meta`; do not expose SPA component or Quasar table shapes.
- Use 200 for a returned representation, 201 for a created resource, and 204 when an operation has no response body.
- Validation errors use HTTP 422 with a JSON object containing `message` and field-keyed `errors`. Error messages are display text, not stable machine identifiers. Do not include input values, credentials, or sensitive data in errors.
- Use 401 for missing/invalid authentication, 403 for an authenticated but explicitly blocked account or denied action, 404 for an unavailable resource, 409 for a state conflict, 419 for invalid/missing CSRF, and 429 for throttling. Authentication errors must not reveal whether an account exists. A throttled response includes `Retry-After` when Laravel provides it.
- Timestamps are RFC 3339 UTC strings. This initial profile omits CPF; credentials and sensitive inputs never appear in public errors or logs.

## Authentication contract

- The first-party SPA uses Sanctum stateful session cookies and CSRF. It calls `GET /sanctum/csrf-cookie` before state-changing requests and sends the XSRF token in `X-XSRF-TOKEN` with credentials enabled.
- Successful `POST /api/v1/login` returns 204; the client then loads `GET /api/v1/auth/my-profile`. The SPA login does not issue or return a personal access token.
- `POST /api/v1/logout` invalidates the current session and returns 204. It does not revoke separately authorized integration tokens.
- A newly registered, not-yet-verified user may establish a limited session and read a minimal profile for verification. Business routes remain denied until email verification automatically activates the basic profile. An explicitly deactivated account receives 403. This distinction was approved by yanpenalva on 2026-10-02 and clarifies API #13 against the accepted ADR 0004.
- An authenticated profile contains only the current user's identity plus effective role and permission identifiers. Before verification, roles and permissions are empty and the identity is limited to the fields in the OpenAPI schema. CPF is omitted from this initial minimal auth profile; any future CPF response must follow the holder/specific-permission access in ADR 0004, never public exposure. Permission names remain owned by API #16.
- Production CORS origins, session domain, lifetime, SameSite policy, and TLS termination are `NOT FOUND` and must be resolved before production auth deployment.

## SPA origins and email links

- Configure trusted SPA origins and API URLs explicitly per environment. Credentialed CORS must use exact allowed origins; do not derive trust from the request `Host` header. Production values remain `NOT FOUND`.
- Verification and password-reset messages link to routes on the configured SPA origin and use the API's callback/validation flow. Construct the final URL before signing or encoding it; never alter a signed URL afterward. Exact routes, payloads, expiry, and replay behavior belong to API #14 and #15.
- Do not put passwords, CPF, or reusable credentials into links, logs, analytics, or public error responses. The mail transport and production delivery policy remain owned by the corresponding feature.

## Filters and pagination

- A collection may accept `page` (positive integer, default 1) and `per_page` (integer 1–100, default 25). Responses include `meta.current_page`, `meta.per_page`, `meta.total`, and `meta.last_page`.
- Each endpoint declares its own filter and sort allowlist. Unknown fields are rejected with 422; clients never choose SQL columns or operators.
- Sort uses a documented `sort` field allowlist and `direction=asc|desc`; absent values use the endpoint's stable default order.

## Availability and compatibility

- `GET /api/v1` is a minimal availability response. Laravel's `/up` is liveness: it only indicates that the application can answer. No readiness endpoint or database-readiness contract exists yet; do not expose connection, host, or infrastructure details.
- Adding an endpoint or optional response field is compatible. Removing or renaming a field, making an optional input required, or changing status/authentication semantics is breaking and requires a new major API path or an explicit migration plan.
- API #11 owns generated-schema comparison in CI before frontend integration; no compatibility gate is configured in this documentation-only delivery.
- OpenAPI 3.1 is the contract format. Scramble should generate the published document from implemented Controllers, Form Requests, and Resources under API #11; this initial file is a manually reviewed pre-implementation contract, not generated runtime evidence.

## Evidence and decisions

- API Issue [#3](https://github.com/yanpenalva/starter-kit-api/issues/3) defines the initial conventions and login/profile surface.
- API Issue [#13](https://github.com/yanpenalva/starter-kit-api/issues/13) defines the exact login, profile, logout, session, and failure-status paths.
- Accepted architecture: [ADR 0002](../../docs/adr/0002-autenticacao-spa.md), [ADR 0003](../../docs/adr/0003-persistencia-contratos.md), and [ADR 0004](../../docs/adr/0004-identidade-rbac.md).
- Laravel 13 documents Sanctum SPA authentication as cookie/session based and separate from personal access token authentication: [Sanctum SPA authentication](https://laravel.com/framework/docs/13.x/sanctum#spa-authentication).
- Scramble documents OpenAPI 3.1 generation and its current Composer manifest declares Laravel 13 compatibility: [Scramble](https://github.com/dedoc/scramble), [composer.json](https://github.com/dedoc/scramble/blob/main/composer.json).

**Review state (historical snapshot):** draft for API #3 at authoring time. **Superseded 2026-10-03:** the initial conventions were accepted by the human and integrated via [PR #30](https://github.com/yanpenalva/starter-kit-api/pull/30) (merged 2026-10-02T23:34:49Z; closure of API #3 confirmed by yanpenalva in the PR; [FEAT-3](../work/FEAT-3/work-item.json) closed as a receipt — the acceptance is not reopened). The limited pre-verification session rule remains explicitly approved. Still genuinely open: generated publication, endpoint-level contracts and compatibility CI belong to API #11 and the owning implementation issues; API names/matrix, production domains, session configuration, and per-resource filters remain `NOT FOUND` until their owning features.

## Private OpenAPI transport policy (approved 2026-10-03)

yanpenalva approved this policy via the question tool ("Aprovar transporte") on 2026-10-03. Routine SHA/workflow pins need no new human choice; CI credential provision remains a human decision. Producer and consumer workflows do not exist yet; this slice is documentation only.

### Producer (API repository; API #11, wave 3)

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

- Caller inputs: the expected API SHA taken from a prior reviewed merge (exact 40-character hex SHA from `main`), not a Git tag or `latest`.
- Implemented in `scripts/verify_contract_artifact.py`: fails closed unless the artifact originates from `main`, the commit SHA matches exactly, the triggering workflow event is in the allowlist (`workflow_dispatch`, `release`, `workflow_run`), all required receipt metadata fields are present and well-formed (`workflow_name`, `workflow_id`, `run_id`, `run_url`, `artifact_id`, `artifact_name`, `commit_sha`, `head_branch`, `event`, `created_at`, `artifact_sha256`), and the SHA-256 digest of the artifact file recomputed independently matches the receipt digest.
- Receipt metadata authentication vs attestation: The receipt authenticates GitHub Actions workflow run and artifact metadata; it does NOT implement cryptographic attestation or signing (no signing/notary is implemented at Stage A).
- Stage B deterministic export requirement: the producer export pipeline must execute under `LC_ALL=C` and enforce 2x idempotent export verification before generating the artifact hash.
- Contract scope discipline: Stage A does not generate a fake live contract; `/up` is an operational liveness probe, not an application contract. When real controllers are implemented in Stage B, a transformer overlay will document real routes (with `/up` optional).

### Scope limits and current assumptions

- Retention: the 90-day default is proposed as the technical default under existing policy; absent/expired artifacts fail the gate.
- Production and backups are out of scope.
- Guarded draft workflow: `.github/workflows/openapi.yml` exists as a guarded draft that fails explicitly if triggered before Stage B (awaiting L1 auth freeze and Scramble installation).
- Sources: [fine-grained PAT permissions](https://docs.github.com/en/rest/authentication/permissions-required-for-fine-grained-personal-access-tokens), [GITHUB_TOKEN security](https://docs.github.com/en/actions/concepts/security/github_token), [security hardening](https://docs.github.com/en/actions/reference/security/secure-use), [workflow runs API](https://docs.github.com/en/rest/actions/workflow-runs), [artifact retention](https://docs.github.com/en/organizations/managing-organization-settings/configuring-the-retention-period-for-github-actions-artifacts-and-logs-in-your-organization), [gh run download](https://cli.github.com/manual/gh_run_download).

