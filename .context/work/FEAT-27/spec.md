# Specification

## Objective

Complete and verify the active-session listing, revocation, and lifecycle invalidation already started on this branch.

## Acceptance criteria

- A revoked database session cookie can no longer authenticate; a separate valid session remains usable.
- A successful password reset removes every session for that user and leaves other users' sessions intact. A failed reset leaves sessions unchanged.
- Suspending an account through its HTTP endpoint removes its sessions and makes the prior cookie unauthenticated.
- Bulk revocation removes the requesting user's other sessions, keeps the current session, and leaves another user's sessions intact.
- Both session DELETE routes share a per-user limit of 10 requests per minute.
- Session-not-found errors use `ApiMessage`; routes share the account-status middleware group.
- `SESSION_DRIVER=database` is documented and configured without adding credentials. `sessions.user_id` matches the type of `users.id`; `sessions.id` stays a string.
- `ScaffoldTest` inventories all three registered active-session routes, including both methods on the collection URI.
- `contracts/openapi.json` reflects the active-session endpoints as `sessionCookie`-protected, documents XSRF on DELETE and their error responses, and matches two byte-identical exports run under `LC_ALL=C`.
- `composer lint`, `composer analyse`, Pest, and strict context validation results are recorded before Git finalization.
- The user-authorized local Scramble export is deterministic across two `LC_ALL=C` runs; use its output for the snapshot without hand-editing.

## Out of scope

Production deployment and production session-domain/TLS decisions. Only existing PR #83 may be updated; creating another PR and merging #83 are out of scope. The local-export authorization applies only to this snapshot and does not change the general producer workflow.

## Security and compatibility

Cookie/session-only Sanctum authentication remains in force. Session reads and deletes stay scoped to the authenticated user. DELETE responses remain 204, foreign or missing IDs share the same 404 message, and rate-limit overflow remains 429.
