# Architecture Context

- Independent REST API; initial route namespace `/api/v1`.
- Thin Controllers, Form Requests, Resources and Policies; Actions for use cases with relevant logic.
- No artificial requirement for Repository Pattern, Service Layer or DDD; decoupled from Blade, Vite and Vue components.
- Independent Vue 3/TypeScript/Quasar frontend; first-party SPA authentication via Sanctum cookies, session and CSRF.
- PostgreSQL persistence; Redis only when needed. OpenAPI contracts support TypeScript type generation; detailed schemas and generator are `NOT FOUND`.
- Public registration; email verification automatically activates access permitted to the basic profile, without administrative privileges. Explicitly deactivated accounts remain blocked.
- First administrator: explicitly selected verified account promoted by a restricted operational command, transactionally and idempotently with audit.
- Optional CPF on user profiles for cadastral identification: normalize to 11 digits, validate format/check digits, enforce uniqueness when supplied. Read access for the subject and management with a specific permission; no public exposure or audit capture.
- Activitylog v5 is the accepted audit choice: explicit events/permitted diffs, 90-day event retention, specific read permission, no API edit/delete. Audit-write failure rolls back privilege changes/admin bootstrap.
- LDAP excluded; domain rules/texts come from new feature requirements rather than automatic legacy reuse.

## Accepted ADRs — API #1

[Six ADRs](../../docs/adr/README.md) accepted by yanpenalva on 2026-10-01; [approval evidence](../work/FEAT-1/approval.md), [work item](../work/FEAT-1/work-item.json). Platform scaffold implements API-only boot and HTTP availability; identity, Sanctum, RBAC, CPF and audit requirements remain unimplemented. ADR 0006 is historically incorporated into 0001/0002/0004.

Business domains/module boundaries, production domains, infrastructure, final package/database versions, business permission names/matrix and detailed API contracts: `NOT FOUND`; define in the corresponding deliveries. Backup retention and operational ownership must be specified before audit collection in production. Basic scaffold is not blocked by those later implementation decisions. Revisit context after the first application scaffold.

## Scaffold implementation

Laravel official scaffold source recorded in `.scaffold-source.json`. PHP 8.5 FPM/Nginx development image and Compose define API-only routing: GET `/api/v1` returns availability JSON; `/up` is framework boot health, not database readiness. Separate PostgreSQL development/test instances; Redis/Mailpit optional async profile, no workers/scheduler or application integration yet. FEAT-7/8/24 contain validation evidence. No production image/deploy implemented.
