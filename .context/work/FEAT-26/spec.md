# Specification

## Scope

- Add `POST` and `DELETE` user administration; accept `name`, `email`, `cpf`, `password`, and `ativo`; creation does not sign in or assign roles.
- Add `POST` and `DELETE` role administration; role names are unique; deleting an assigned role returns 422.
- Require `users.manage` / `roles.manage` and a verified, active actor. Never delete the current actor or the last active admin.
- Permission definitions remain enum/seeder-owned; there is no permission CRUD API.
- Populate the audit `causer_id` from the approved actor identifier, and redact properties/description at resource serialization.

## Acceptance criteria

- Anonymous access returns 401; missing permissions and ineligible actors return 403.
- Invalid or duplicate inputs return 422; valid records persist with the expected status and no unintended role assignment/session switch.
- Self and last-admin deletion are denied; assigned roles are retained.
- A mutation produced through `Audit::record` is retrievable by responsible-user filter.
- Properties and free-text descriptions stored outside `Audit::record` cannot leak PII through the feed.
- OpenAPI snapshot changes are produced only by the canonical producer on `main`.
