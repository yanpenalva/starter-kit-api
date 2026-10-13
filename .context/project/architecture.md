# Architecture Context

- Independent REST API; initial route namespace `/api/v1`.
- Thin Controllers, Form Requests, Resources and Policies; Actions for use cases with relevant logic.
- No artificial requirement for Repository Pattern, Service Layer or DDD; decoupled from Blade, Vite and Vue components.
- Independent Vue 3/TypeScript/Quasar frontend; first-party SPA authentication via Sanctum cookies, session and CSRF.
- PostgreSQL persistence; Redis only when needed. The current API conventions and generated OpenAPI snapshot are recorded in [API contract](api.md) and `contracts/openapi.json`; `openapi.yaml` is the historical initial draft. Scramble runtime export, main-only publication, and compatibility validation are implemented under FEAT-11.
- Public registration; email verification automatically activates access permitted to the basic profile, without administrative privileges. Explicitly deactivated accounts remain blocked.
- First administrator: explicitly selected verified account promoted by a restricted operational command, transactionally and idempotently with audit.
- Optional CPF on user profiles for cadastral identification: normalize to 11 digits, validate format/check digits, enforce uniqueness when supplied. Read access for the subject and management with a specific permission; no public exposure or audit capture.
- User and role administration uses verified active session actors, enum-owned permissions, gates and policies. Current main rechecks policies inside the privilege transaction lock; the broader actor-refresh hardening in Slice 11 is still branch-only.
- Activitylog v5 writes role action codes from a closed allowlist with a minimal property envelope, a permission-gated feed, and 90-day retention. Audit-write failure rolls back privileged mutations.
- LDAP excluded; domain rules/texts come from new feature requirements rather than automatic legacy reuse.

## Accepted ADRs — API #1

[ADRs 0001–0005, 0007, and 0008](../../docs/adr/README.md) record the accepted architecture; [approval evidence](../work/FEAT-1/approval.md) and implementation work items track delivery. ADR 0006 is historically incorporated into 0001/0002/0004. Runtime implementation details live in the API/module context and owning work-item evidence, not in the original approval record.

Production domains, production infrastructure, trusted proxy ranges, and operational ownership remain `NOT FOUND`. Backup retention must be specified before audit collection in production. Permission names/matrix and endpoint contracts are recorded in the [API contract](api.md) and ADRs.

## Scaffold implementation

Laravel official scaffold source is recorded in `.scaffold-source.json`. PHP 8.5 FPM/Nginx development image and Compose define API routing; `GET /api/v1` is availability, and `/up` is framework boot health, not database readiness. PostgreSQL development/test instances are separate; Redis/Mailpit support the optional async profile. FEAT-7/8/24 contain scaffold validation evidence; no production image or deploy is implemented.
