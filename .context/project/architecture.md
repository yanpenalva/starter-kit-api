# Architecture Context

- Independent REST API; initial route namespace `/api/v1`.
- Thin Controllers, Form Requests, Resources and Policies.
- Actions for use cases with relevant logic.
- No artificial requirement for Repository Pattern, Service Layer or DDD.
- Decoupled from Blade, Vite and Vue components.
- Independent Vue 3/TypeScript/Quasar frontend; first-party SPA authentication via cookies, session and CSRF.
- OpenAPI contracts support TypeScript type generation; detailed schemas and generator are `NOT FOUND`.
- Spatie Laravel Activitylog v5 is an audit proposal subject to an ADR.

Business domains/module boundaries, hosting, production URLs and detailed API contracts: `NOT FOUND`. The module scaffold is documentary and defines no domains. Revisit context after ADRs and the first application scaffold.
