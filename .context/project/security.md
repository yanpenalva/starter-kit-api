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

Scaffold defaults: APP_DEBUG=false; no public frontend/default accounts; secrets generated locally and excluded from Git. HTTP/Mailpit ports bind to localhost; databases/Redis/SMTP have no host ports. Test DB uses a separate PostgreSQL instance and ephemeral tmpfs. Local database users are container initialization users, for development/test only; production least-privilege provisioning remains separate. Logs use stderr; Nginx access logs are disabled. Production trusted hosts/proxies, TLS, CORS and business-input redaction remain with HTTP/security feature work.
