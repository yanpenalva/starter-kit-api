# Review — BUG-39

Reviewer: Codex (implementation review); independent reviewer: NOT FOUND.

## Findings

No remaining blocking findings in the changed scope.

- Enrollment validates and rechecks the current password before generating or storing an MFA secret.
- MFA-required login logs out the web guard and invalidates the prior session before creating a challenge-only session.
- Every API route currently using `auth:sanctum` passes through `EnsureMfaChallenge`; the middleware requires a matching verified-user marker for admins with enabled MFA.
- Recovery codes use Laravel's one-way hasher, and the dump test checks that every returned code is absent from persisted JSON while each stored hash verifies its code.
- Recovery-code consumption reads and removes under a transaction while locking the user's row. The PostgreSQL race test asserts one successful consumer.
- The changed controller, middleware, service, route groups, tests and auth docs were checked together; no generated OpenAPI snapshot or external interface was changed.

## Limits

The full local Pest suite cannot pass under the SQLite-only setup because repository and infrastructure tests require PostgreSQL and CI services. The targeted MFA suite passes locally; the concurrent row-lock test will run against PostgreSQL in CI. No PR or merge is part of this delivery.
