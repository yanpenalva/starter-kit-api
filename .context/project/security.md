# Security Context

- Accepted first-party SPA authentication: Sanctum cookies, session and CSRF. Explicit credentialed origins, HTTPS and protected session cookies are required in production; real domains, session lifetime/store and deployment configuration: `NOT FOUND`.
- Public registration with automatic basic-profile activation after email verification; no role/permission/status assignment from untrusted registration input. Explicitly deactivated accounts remain blocked.
- Authorization: Spatie Laravel Permission and Policies, including access to the target object. Business role/permission names and matrix: `NOT FOUND` until the relevant feature.
- First administrator: restricted operational command promoting an explicitly selected verified account, idempotently and transactionally with audit. No public bootstrap endpoint or fixed credentials.
- CPF optional for cadastral identification: normalize to 11 digits, validate format/check digits, unique when supplied. Subject access and management access with a specific permission; no public exposure, logs or audit capture.
- Audit: Activitylog v5 accepted, permitted events/diffs only, 90-day event retention and automatic cleanup. Specific permission for reads; no edit/delete through the API. Audit failure rolls back privilege changes/admin bootstrap. Credentials, CPF, email, full payloads and free text are excluded; no default IP/user-agent capture.
- Identity/RBAC/audit controls are approved architecture, not implemented controls. Platform dependencies are installed for the scaffold; no identity, Sanctum, RBAC or audit package is installed.
- Backup retention/deletion, data classification, operational ownership and applicable requirements must be specified before audit collection in production. Secrets provider, reporting channel and production operational controls: `NOT FOUND`.
- LDAP excluded; new domain rules/texts are feature-driven.
- Never version secrets, credentials or sensitive data; keep test data isolated and synthetic. Follow `.context/policies/core/security-privacy.md`.

Decisions: [ADR 0004](../../docs/adr/0004-identidade-rbac.md), [ADR 0005](../../docs/adr/0005-auditoria.md). Human acceptance by yanpenalva in [FEAT-1 approval](../work/FEAT-1/approval.md). Audit dependency evidence uses concrete manifests, not a resolved application lockfile.

Scaffold defaults: APP_DEBUG=false; no public frontend/default accounts; secrets generated locally and excluded from Git. HTTP/Mailpit ports bind to localhost; databases/Redis/Mailpit/SMTP have no host ports. Test DB uses a separate PostgreSQL instance and ephemeral tmpfs. Local database users are container initialization users, for development/test only; production least-privilege provisioning remains separate. Logs use stderr; Nginx access logs are disabled. Production trusted hosts/proxies, TLS, CORS and business-input redaction remain with HTTP/security feature work.

## Error-body negative space (pt-BR API, FEAT-10 wave 3)

Rationale moved out of `bootstrap/app.php` / `app/Rules/Cpf.php` / `app/Http/Controllers/Auth/LoginController.php` code comments (comment-hygiene policy); this section is the canonical owner of the invariants:

- Every human-facing API body message is owned by `App\Support\ApiMessage`. Only messages in its `OWNED` allowlist pass verbatim into a response; any other upstream exception text (framework defaults like "This action is unauthorized." / "CSRF token mismatch.", model-binding details, English throttle texts) is replaced by the localized status message via `ApiMessage::forHttpException` → `forStatus` — unmapped statuses get the safe generic `REQUEST_FAILED`, never upstream text (fail-closed, no English/PII leakage).
- `ValidationException` and `HttpResponseException` keep the framework's own rendering (422 `{message, errors}` contract keys unchanged). `APP_DEBUG=true` disables the whole override (development behavior). Non-JSON contexts fall through to the framework.
- Machine data rides with the localized body: HTTP status and exception headers (429 `Retry-After`, `X-RateLimit-*`) are preserved exactly.
- Login enumeration: unknown email, wrong password and deactivated-with-wrong-password all return the same generic 401 `Credenciais inválidas.`; the dummy bcrypt evaluation (`DUMMY_HASH`) equalizes timing when the account does not exist; explicit 403 `Esta conta foi desativada.` only after correct credentials.
- Stateful session gate: a request reaching `EnsureStatefulSession` without a session (bypassed Origin/Referer pipeline) aborts 419 instead of crashing or skipping CSRF.
- CPF rule (`App\Rules\Cpf`): optional (null/'' passes); non-string input is a type failure; strings are digit-normalized before length/repetition/check-digit validation; leading zeros survive because the column stores text — callers normalize formatted input (`529.982.247-25`) before or after validation.
