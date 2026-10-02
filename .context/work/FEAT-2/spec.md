# Specification — FEAT-2

## Scope

- Statically inspect the legacy API, data definitions, seeders, mail, tests, and SPA integration at the pinned commit.
- Record behavior separately from greenfield decisions and identify unsafe or domain-specific behavior that must not be reproduced.
- Identify only the contracts needed for the first vertical identity slice; defer feature detail to its owning issues.

## Out of scope

- Any edit, install, migration, test execution, or database operation in `starter-kit-laravue`.
- Copying source code, user data, credentials, or history into this repository.
- Reimplementing legacy functionality as part of the inventory.

## Rules and security

- Treat `starter-kit-laravue` as read-only evidence, not as normative context.
- Use the accepted ADRs for Sanctum session authentication, automatic activation after email verification, optional CPF, explicitly selected permissions, and allowlisted audit diffs.
- Preserve `NOT FOUND` for behavior that static source inspection cannot establish.

## Acceptance

- [x] Traceable matrix covers authentication, account/verification, users, roles/permissions, email, and audit.
- [x] Domain-specific, dead/unrouted, unsafe, and still-unknown behavior is identified.
- [x] No legacy installation, runtime command, mutation, or migration is performed.
- [ ] Independent review confirms the inventory and disposition before this work item is closed.
