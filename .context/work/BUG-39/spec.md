# Specification — BUG-39

## Request

- Source: explicit user request on 2026-10-13.
- Related feature context: FEAT-13 slice 4, API #13, existing PR #82.

## Objective

Generate `contracts/openapi.json` with two deterministic local exports and commit it. Rewrite historical commit `7d34e0373ebbbfe16568acaf96321d569a069875` so the flagged test value is the documented synthetic fixture, then force-push only the existing PR #82 branch with a lease.

## Scope and exclusions

- In scope: `contracts/openapi.json`, the flagged password fixture in `tests/Feature/Auth/ProfileTest.php`, and BUG-39 delivery evidence.
- Out of scope: application behavior, other branches or PRs, PR creation, merge, and GitGuardian dashboard changes.

## Security and delivery rules

- Keep the original `vendor` symlink and do not run `composer install`.
- Source `/opt/data/php/env.sh` for PHP commands.
- Use the documented value `fake-profile-password-123` in the rewritten test history.
- Use interactive rebase with merge preservation, then verify the old value is absent from the final branch history.
- Force-push only `refs/heads/feat/slice-4-self-service-profile` to `origin`, with the expected remote SHA in the lease.
- No merge is authorized.

## Acceptance criteria

- Two exports under `LC_ALL=C` and `TZ=UTC` compare byte-for-byte and produce the committed snapshot.
- `composer lint` passes.
- `ScaffoldTest` passes.
- A history-wide search on the final branch has no match for the old flagged value.
- PR #82 remains open and unmerged at the pushed head.

## Decision and exception

The explicit approvals for local snapshot export and leased force-push are recorded in [EXC-0002](../../exceptions/EXC-0002.json). The exception is limited to this PR and expires 2026-10-14.
