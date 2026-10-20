# CSD to engineering handoff: illustrative users filter

## Scope

This page illustrates one handoff between a workflow owner (Context Spec Develop, CSD) and an
engineering standard (strict-code). It is documentation only: it implements no feature, creates
no work item and records no execution evidence. Repository facts come from files and configuration;
upstream ownership comes from the published contract, not from an installed package. Stack versions
are not restated here; `composer.json` and `composer.lock` are authoritative.

## Owners

| Concern | Owner | Where it lives here |
| --- | --- | --- |
| Intake, classification, specification, gates, delivery evidence | CSD workflow | `.context/workflows/core.md`; `.context/templates/common/spec.md` |
| Engineering criteria: patterns, quality, security, review | strict-code, external | `NOT FOUND`; no strict-code skill or package is installed in this checkout |
| Domain facts, architecture, accepted decisions | Project documentation | `.context/project/`; accepted ADRs under `docs/adr/` |
| Executable commands and measured thresholds | Project configuration | `composer.json`, `phpstan.neon`, `pint.json`, `.github/workflows/quality.yml` |
| Continuity across sessions | ai-memory, continuity only | `.context/tooling/ai-memory.md` |

One decision owner per stage: CSD alone classifies work and advances gates; engineering review applies
strict-code criteria to the approved specification and cannot approve product scope. This page does
not define a second policy or a second workflow.

## External pointers

Pointers are prose, not a resolver or schema:

```text
External spec: .context/work/FEAT-<issue>/spec.md; owner: CSD product workflow; revision: NOT FOUND
Engineering source: NOT FOUND; owner: external engineering standard; revision: NOT FOUND
```

`.context/project/conventions.md:4` assigns one GitHub Issue per implementation task and the
`FEAT-<issue>` identifier. An unresolved locator stays `NOT FOUND`; it never authorizes guessed
requirements or skipped review.

## Walkthrough: filter the users listing

Illustrative request: "filter the users listing by search term, status and role". No issue,
specification or work item for it exists in this checkout; `.context/work/` holds only `.gitkeep`.

1. Intake: CSD records track `product`, type `feature`, owner, risk and phase before implementation
   (`.context/workflows/core.md:18-20`). This page is not an approved request.
2. Specify: the specification records objective, scope, acceptance criteria and unknowns
   (`.context/templates/common/spec.md`). Acceptance for this slice: an authorized reader receives
   HTTP 200 with only matching users; an unknown filter key is ignored; `status` accepts only
   `active` or `deactivated`.
3. Plan: confirmed touchpoints.
   - `app/Http/Requests/Users/IndexUsersRequest.php:28-38` validates the allowlisted query keys.
   - `app/Http/Requests/Users/IndexUsersRequest.php:40-52` forwards only present filter values.
   - `app/Support/Filters/UserFilters.php:18-37` composes search, status and role predicates.
   - `app/Support/Filters/UserFilters.php:42-57` selects `ilike` or `like` per driver and escapes
     wildcards.
   - `app/Actions/Users/IndexUsers.php:17-22` applies the filters and paginates.
   - `app/Http/Controllers/Users/UserController.php:44-52` authorizes through the FormRequest and
     returns the resource collection.
   - Tests: `tests/Feature/Support/FiltersTest.php:30-76`.
   - Contract: response shape and route are unchanged (`routes/api/users.php:10-19`), so no OpenAPI
     snapshot change is planned.
4. Execute and test: add a focused failing test first, then the smallest implementation.
   `composer test` is the behavior gate, and no hook runs it.
5. Verify and review: run the automated checks below and review the allowlist and driver guard by
   hand. A finding returns to execution with an explicit correction scope; review cannot accept
   product scope.

## Automated and manual checks

| Scope | Command | Automation | Evidence |
| --- | --- | --- | --- |
| Format | `composer lint` | pre-commit hook and application CI | `.githooks/pre-commit:3`; `.github/workflows/quality.yml:103`; `composer.json:50`; `pint.json:2` |
| Static analysis | `composer analyse` | pre-push hook, skippable, and application CI | `.githooks/pre-push:5-9,17`; `.github/workflows/quality.yml:104`; `phpstan.neon:5-10` |
| Behavior | `composer test` | CI only; not run by hooks | `composer.json:43`; `phpunit.xml:5-9`; `.github/workflows/quality.yml:105` |
| Merged coverage | `composer test:coverage` | CI coverage job only | `composer.json:44-48`; `.github/workflows/quality.yml:108,167,172` |
| Changed-code coverage | No configured check | Manual review; threshold `NOT FOUND` | `.context/project/quality.md:27` |
| Context and documentation | `python3 scripts/validate_context.py --strict` | pre-commit when `.md`, `.json` or `.context/` is staged | `.githooks/pre-commit:4-5`; `scripts/validate_context.py` |
| Structural, complexity, duplication | No configured measurement | Manual review; values `NOT FOUND` | `.context/project/quality.md:27` |

The configured coverage floor is 80% of merged lines, not 100%: `scripts/coverage-floor.php` receives
`80` in `composer.json:48`, and the CI job is labeled "min 80%" (`.github/workflows/quality.yml:108`).
The supplied runtime image installs no coverage driver (`Dockerfile:7,17-19`), while CI enables pcov
through `shivammathur/setup-php` (`.github/workflows/quality.yml:167`); coverage is therefore a CI
measurement and does not run from the local image by default.

## Limitations

- Illustrative only. No feature is implemented and no work item is shipped with this page.
- strict-code is not installed here. Its package name, skill identifier, installation command and
  configuration key are `NOT FOUND`; no pointer above invents one.
- The installed CSD material, including `.context/policies/core/code-quality.md`, is a legacy bundled
  copy pending the upstream distribution migration. It is not edited here and remains transitional,
  not the final policy source.
- The installed startup prompt is reported to contain an unsafe authorization sentence. Repair belongs
  upstream; this repository does not hand-edit `.context/prompts/start-conversation.md` or the
  installed skill.
- The 80% floor is merged global coverage. No changed-code threshold or approved exception exists, and
  global coverage does not prove that changed lines are covered.
- `FEAT-23` and `FEAT-24` records are absent from this checkout. That absence is not evidence that the
  work never happened.
- No ADR is accepted by this page: ADR 0013 remains proposed, and users-filter vocabulary needs
  ADR 0009 review before a real implementation.
- Named quality, production and release owners are `NOT FOUND`; this page authorizes no release or
  production action.
