# Reproduction — BUG-42

## Observed behavior

- Running `composer audit --locked` reports 9 security vulnerability advisories affecting 6 packages:
  - `laravel/framework` (13.39.0): PKSA-yxbx-3c9s-cjt9 (CVE-2026-128083)
  - `symfony/http-foundation` (v8.1.9): PKSA-nxjs-s6hn-vb6m (CVE-2026-121619), PKSA-24ph-xkrh-y79j (CVE-2026-118514), PKSA-pr6q-2w5s-kzzf, PKSA-b2gg-vk1g-6d5q
  - `symfony/http-kernel` (v8.1.9): PKSA-3gxv-7xhq-mgmz (CVE-2026-123339)
  - `symfony/mime` (v8.1.7): PKSA-wtxr-p26d-nn42 (CVE-2026-45070)
  - `symfony/routing` (v8.1.9): PKSA-y8c5-1b7f-r1gj (CVE-2026-118516)
  - `symfony/yaml` (v8.1.9): PKSA-nktx-f1n1-b4yd (CVE-2026-123792)

## Expected behavior

- `composer audit` reports 0 security vulnerability advisories.
- Dependencies are refreshed within declared compatible constraints in `composer.json` to selected locked version `laravel/framework 13.43.0` with 0 security advisories reported by `composer audit`.
- `dedoc/scramble` pin remains unchanged at `0.13.49`.
- Platform requirements, lint, analysis, and test suites pass without regression.

## Steps and evidence

1. Execute `docker compose run --rm --no-deps api composer audit --locked`.
2. Observe 9 reported advisories affecting 6 packages.

Evidence:
- Initial audit execution in worktree container against current `composer.lock`.

## Scope

- Reproducibility: 100% deterministic against `composer.lock`.
- Affected versions/environments: All environments running the locked dependency tree from `main`.
- Suspected boundary: `composer.lock`.
