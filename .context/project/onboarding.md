# Onboarding Evidence

## Sources and decisions

- User task and https://github.com/yanpenalva/starter-kit-api/issues/5; bounded inspection found no application files or existing context.
- User confirmed bootstrap for both repositories and the generic documentary module scaffold.
- CSD npm 1.2.1; official source tag `v1.2.1`, immutable commit `6ca620e0491e94e0836bda29cd55b334b4f12aec`.
- Official project installer with `--agents codex`; bootstrap discovery/preview and application; module preview/application.
- API main matched origin/main at `31060fa4e6041c9048cd9d6f2e7571e26fa423cd`. Frontend had no commits or remote main; user declined creating it.
- Single-agent Codex execution, with independent human review and human release approval; future Git mode remains confirm_each.
- No application dependencies, scaffold, credentials or production infrastructure created. No artificial work item.

## Pending facts

Business domains, detailed contracts, owners, production infrastructure/domains, SLA, observability, application commands, configured thresholds, CI and deployed versions: `NOT FOUND`.
Backend Activitylog v5 remains an ADR proposal. Frontend detailed architecture is pending Issue #1. No material decision blocks context initialization.

## Validation

Validation executed on 2026-10-01:

| Command | Observed result |
| --- | --- |
| `python3 scripts/validate_context.py --strict` | Exit 0; Context validation passed |
| `python3 scripts/validate_context.py --strict --examples` | Exit 0; Context validation passed; explicit warning that no example suite is installed |
| `python3 -m unittest discover -s tests -v` | Exit 0; 6 tests passed |
| `npx @owlcodium/context-spec-develop@1.2.1 doctor --scope project` | Exit 0; CSD is healthy (1.2.1) |
| `npx @owlcodium/context-spec-develop@1.2.1 discover --root .` | Exit 0; resolved .context; no missing capabilities/conflicts; onboarding_required false |

The Node commands used the offline npm cache after obtaining the published package; no local source checkout was used.
Regression tests verify supplementary source SHA-256 integrity, application stack acceptance, optional examples, rejection of missing files/placeholders/broken links/invalid JSON, forbidden force push, invalid active work items and an invalid installed example suite.

Upstream validator applicability limitation: its unmodified public-package marker scan rejects Laravel/Vue terms in adopting application context. The local wrapper skips only that package-publication check; no structural/governance/work-item check is removed. `--examples` reports absence rather than requiring synthetic examples. Review these adaptations in the PR; the official upstream validator itself is preserved and hashed.

No application tests were run: no application scaffold exists. Context/test-adapter validation does not establish Laravel, PHP, package or SPA runtime compatibility.
