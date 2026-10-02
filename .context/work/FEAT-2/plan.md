# Plan — FEAT-2

1. Inspect the pinned source routes, requests, actions, models, migrations, policies, mail, tests, seeders, and SPA auth state without executing the legacy application.
2. Record an evidence matrix with explicit greenfield dispositions and open facts.
3. Compare every conflicting legacy behavior with accepted ADRs and Issues #13–#19; preserve the ADR decision where issue descriptions are stale.
4. Validate CSD context and documentation links. Keep the original repository unchanged.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | Codex | none | Pinned-source behavior matrix and static-inspection limits | 1 | project,architecture,security |
| S2 | yanpenalva | S1 | Independent review approves inventory before issue closure | 2 | architecture,security |

FEAT-3 may consume this inventory as evidence, but its contract remains governed by accepted ADRs and API #13.

## Data, rollback, and operational impact

No runtime data, API route, schema, or dependency changes. The inventory is additive and reversible. There is no data migration or operational rollout.
