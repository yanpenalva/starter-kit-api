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

**Review state:** draft for API #3. The limited pre-verification session rule is explicitly approved; the rest of the contract must be reviewed before implementing dependent public endpoints. API names/matrix, production domains, session configuration, and per-resource filters remain `NOT FOUND` until their owning features.
