# Discovery — FEAT-3

## User and operator problem

The API and independently deployed SPA need a small stable contract before the first identity slice. Without it, each client may guess response envelopes, pagination, error handling, CSRF behavior, and profile fields.

## Evidence

- GitHub [API Issue #3](https://github.com/yanpenalva/starter-kit-api/issues/3) defines the initial HTTP conventions and incremental OpenAPI scope.
- API [Issue #13](https://github.com/yanpenalva/starter-kit-api/issues/13) supplies the first-party login, profile, logout, and status-code paths.
- Accepted ADRs establish Laravel API/SPA separation, Sanctum session cookies and CSRF, no SPA personal access tokens, email verification activation, and CPF privacy.
- Static inspection of the legacy project is captured in [FEAT-2](../FEAT-2/legacy-inventory.md); its API and Quasar formats are not copied.
- Laravel's current Sanctum documentation separates cookie/session SPA authentication from personal access tokens. Scramble currently declares Laravel 13 compatibility and OpenAPI 3.1 generation; the package and generated artifact remain API #11 work.

## Audience and expected value

API and SPA engineers need a framework-neutral contract they can consume independently. Success means the login/profile path, error statuses, security boundaries, and pagination rules are explicit before either implementation begins.

## Hypothesis

If both sides review the same endpoint contract before implementation, the SPA and API will agree on session, errors, and response fields without a Quasar-specific coupling.

## Constraints and open decisions

- No login, registration, or other identity endpoint exists yet.
- Production domains, CORS origins, session lifetime/domain, permission names, and generated OpenAPI pipeline remain `NOT FOUND` until their owning issues.
- The user clarified that an unverified account may keep a limited session for logout, minimal profile, and verification; business resources remain denied until verification. An explicitly deactivated account receives 403.
