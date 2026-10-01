# Security Context

- Planned integration: first-party SPA cookies, session and CSRF through Laravel Sanctum. Cross-origin cookie/CORS policy, domains, TLS and session settings: `NOT FOUND`.
- API authorization: Spatie Laravel Permission for RBAC and Policies. Roles and permission matrix: `NOT FOUND`.
- These controls are planned, not implemented or verified. This task installs no authentication packages.
- Audit: Activitylog v5 is a backend proposal subject to ADR; event model, retention and sensitive-data policy: `NOT FOUND`.
- Secrets provider, security owner, vulnerability reporting channel, compliance requirements and operational controls: `NOT FOUND`.
- Never version secrets, credentials or sensitive data; keep test data isolated and synthetic. Follow `.context/policies/core/security-privacy.md`.
