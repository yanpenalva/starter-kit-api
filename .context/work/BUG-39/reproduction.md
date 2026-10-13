# Reproduction — BUG-39

## Observed behaviors before the fix

- `POST /api/v1/auth/mfa/enroll` accepted an authenticated administrator without rechecking the current password.
- A confirmed-MFA admin login created a pending MFA marker but left any prior `web` guard authentication in the session. Protected Sanctum routes had no shared check for a completed challenge, so an already authenticated session could continue using them.
- Recovery codes were stored through Laravel's reversible `encrypted:array` cast, making plaintext recoverable from the database dump.
- The challenge controller compared a recovery code and then saved the remaining list without a transaction or row lock, allowing two concurrent requests to accept the same code.

## Focused reproduction coverage

`tests/Feature/Auth/AdminMfaTest.php` covers missing/wrong enrollment password, encrypted-at-rest dump contents, an authenticated prior session during MFA login, a session predating MFA activation, and a successful challenge opening protected routes.

`tests/Feature/Auth/MfaRecoveryCodeConcurrencyTest.php` starts two PostgreSQL workers against one persisted recovery-code hash and asserts exactly one consumer succeeds. It skips on local SQLite because that driver does not provide PostgreSQL row-lock semantics; CI runs this case with PostgreSQL 18.

The focused feature suite was first run against these tests before implementation and failed on the four reported behaviors. After implementation the focused MFA tests pass on SQLite; the PostgreSQL race case is skipped locally and remains part of the CI database gate.
