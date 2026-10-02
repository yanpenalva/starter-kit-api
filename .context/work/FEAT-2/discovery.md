# Discovery — FEAT-2

## User and operator problem

The new API needs evidence about useful legacy behavior without inheriting domain-specific rules or unsafe implementation choices. The legacy repository is a read-only reference; this greenfield delivery does not migrate historical data or modify that repository.

## Evidence and constraints

- GitHub Issue [API #2](https://github.com/yanpenalva/starter-kit-api/issues/2) defines a bounded static inventory.
- Source is pinned to [`starter-kit-laravue` commit `377cd6ee45edad68bfadeefc32fb3762fc4a3552`](https://github.com/yanpenalva/starter-kit-laravue/tree/377cd6ee45edad68bfadeefc32fb3762fc4a3552).
- Inspection used tracked source files only. No dependency installation, migrations, tests, database access, or source changes were performed in the legacy repository.
- The accepted ADRs and the user's decisions in this conversation govern greenfield behavior where legacy behavior conflicts.

## Audience and expected value

API and SPA maintainers need traceable source references and explicit include/exclude decisions. The inventory reduces accidental copying of legacy authentication, CPF, role, audit, pagination, and email behavior.

## Hypothesis

If feature work starts from a bounded, cited inventory, engineers can reuse proven concepts while avoiding legacy-specific rules and known security hazards.

## Success signal

Each requested area (authentication, account/verification, users, roles/permissions, email, audit) has source evidence, observed behavior, greenfield disposition, and a clear `NOT FOUND` where static inspection cannot prove runtime behavior.
