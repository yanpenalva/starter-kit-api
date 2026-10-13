# Verification — BUG-40

## Evidence

- Baseline: GitHub Actions Quality run `38331058231` (#192), PR #85 head `203fc7a77d57c9a0418f7071c0017a40ba54f640).
  - `application` failed at `tests/Feature/Admin/AuditDistinctActionsTest.php:40`: expected actions omitted the already-allowed `role.rename`.
  - `coverage` stopped on that same Pest failure. No percentage was emitted, so this was not evidence of falling below the 85% threshold.
  - `contract` passed the two-export deterministic comparison, then failed snapshot freshness at line 546. `context` passed.
- Latest `main` was merged through `21fa57335c78b447391df71f5a0ff5bc080b6c57` (PR #90; branch merge `f8960af`).
- Official contract provenance: OpenAPI Contract run `38335202904`, on `main` SHA `21fa57335c78b447391df71f5a0ff5bc080b6c57`, completed successfully. Double-export determinism passed; artifact `11784436203` was reviewed. Its SHA-256 is `3b34c5622ec19e509a8cd93c4011810ddaff1e75457fae28c0ba9661d7bfa342`; it includes current MFA schema changes but not this PR's `action` query parameter.
- Quality run `38335592082` (#214), SHA `871e0b5936b2f04825b8b9471cfe40ffba9f445b`: `application` and `coverage` passed; `context` passed; contract double-export determinism passed, but freshness failed because this branch's snapshot was still the main artifact. The generated branch export exposed the new `action` query parameter on `GET /api/v1/activity-log`.
- Branch candidate command: with the supplied `vendor` symlink, PHP 8.5 from `/opt/data/php/env.sh`, a temporary SQLite file migrated using `php artisan migrate:fresh --force`, and the worktree autoloader shim, `LC_ALL=C TZ=UTC php artisan scramble:export --path=...` was run twice. The two files were byte-identical (`159929` bytes, SHA-256 `650a0d222effb3c6240ad3fc885035c7c9dcfdfa6967c6a21e7f6c0ba9a36885`). Review against main's artifact found only the expected `action` query parameter addition.
- Focused Pest command: `vendor/bin/pest --configuration=/tmp/codex-api-g7-phpunit-worktree.xml`; result `13 passed / 46 assertions`, exit 0. SQLite in-memory was used locally. An empty temporary `.env` was created only during execution to avoid Dotenv warnings and removed immediately afterward.
- Lint command: `composer lint` with `/opt/data/php/env.sh` sourced and the supplied PHP 8.5 binary; Pint passed, exit 0.
- Context command: `python3 scripts/validate_context.py --strict`; passed, exit 0 before the latest evidence edits; rerun before final push.
- No `composer install` was run; the existing `vendor` symlink was preserved.

## Review

- Runtime behavior and authorization were not changed beyond this branch's intended audit-action filter. The test correction only adds the already-allowed `role.rename` expectation.
- The API project's candidate workflow permits branch snapshots generated from the proposed revision; the official artifact remains main-only and is run after integration.
- Local candidate export used SQLite; CI exports and coverage run against PostgreSQL 18.
- Final PR #85 CI result: pending push of the corrected candidate snapshot.
- Delivery scope: push only to `origin/feat/slice-7-audit-distinct-action-codes`; PR #85 must remain open and unmerged.

## Verdict

`APPROVED WITH CONDITIONS` — focused Pest, lint and deterministic candidate export passed. Confirm freshness and all remote PR #85 gates on the corrected pushed SHA before declaring delivery complete.
