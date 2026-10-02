# Specification — FEAT-3

## Scope

- Record v1 transport, response, error, pagination, filtering, timestamp, liveness, and compatibility conventions.
- Add a draft OpenAPI 3.1 contract for the existing availability route and the login/profile/logout surface from API #13.
- Document the Sanctum CSRF flow and distinguish session-cookie authentication from personal access tokens.
- Keep schema generation/publication in API #11, which depends on API #3 and the implementation issues.

## Out of scope

- Installing Scramble, implementing identity endpoints, adding migrations, or generating runtime API documentation before real Controllers, Requests, and Resources exist.
- Selecting production domains, CORS origins, or permission names.
- Defining contracts for registration, password recovery, users, roles, audit, or other future endpoints.

## Accepted behavior and constraints

- JSON under `/api/v1`; Laravel Resources use `data` for a single resource and `data`/`links`/`meta` for collections.
- Login returns 204 and never issues a SPA PAT; profile is fetched separately.
- Missing/invalid auth 401; explicitly deactivated account or denied action 403; CSRF 419; validation 422; throttling 429.
- Unverified users may establish a limited session, read a minimal profile, log out, and complete verification. Business routes remain unavailable until verification automatically activates the basic profile.
- CPF is omitted from the initial minimal authentication profile; any later CPF response must follow the holder/specific-permission access in ADR 0004. Credentials and sensitive inputs are excluded from public errors.
- Pagination defaults to page 1 / 25 entries, capped at 100; filter/sort fields are explicitly allowlisted per endpoint.

## Acceptance

- [x] Compact v1 HTTP conventions are recorded in `.context/project/api.md`.
- [x] Initial login/profile OpenAPI contract exists before SPA authentication work.
- [x] Contract reflects the user's decision about the unverified session and contains no Bearer/PAT scheme.
- [ ] Human review approves the complete public contract before API #13 endpoints are merged.
- [ ] API #11 later generates and validates the document from implemented Laravel endpoints and publishes a frontend-consumable artifact.
