# Acceptance — FEAT-9 vs API issue #9 slices delivered in `main` 1390009

Source: immutable `main` at `1390009d37f6a3493b21be7d83dbd32ae355d579`. GitHub issue #9 stays OPEN (progressive); closure is the parent's act. RBAC catalogue and activity-feed slices remain tracked in FEAT-16 and FEAT-19 (both still active). Verdicts below cover the FEAT-9-owned slices only.

## Criterion 1 — `migrate:fresh --seed` twice on the isolated test database, idempotent seed

YES. `database/seeders/DatabaseSeeder.php` is the only sanctioned seed entrypoint and calls exclusively `RbacCatalogueSeeder`. Proven by `DefaultDatabaseSeederTest` with fail-closed guards (testing env, pgsql, exact `starter_api_test` database, null `database.pgsql.url`, `TEST_TOKEN` parallelism guard): fresh seed executed twice standalone yields 8 permissions, 2 roles, 0 users, 0 assignments. Wave-2 gate evidence: 57 passed, 293 assertions; PR #35 CI 3/3 success; parent merge `5604bf5`; main CI success.

## Criterion 2 — no real fixed data, no real CPF, no hardcoded admin password

YES. The default seeder creates catalogue rows only: 0 users, 0 assignments, no admin promotion, no default credentials. `RbacCatalogueSeeder` enforces the invariant at runtime; the test asserts the counts. Factories generate fake data for development and tests only.

## Criterion 3 — uniqueness, relationship, and index tests

YES. `UserSchemaTest` covers the users table, optional CPF with partial unique index `users_cpf_unique`, and the required concurrency duplicate-insert test. The former case-insensitive-email gap is now resolved by FEAT-13 in this same `main`: expression unique index `users_email_lower_unique` on `lower(btrim(email, E' \t\n\r\x0B'))`, fail-closed pre-migration collision check, parameterized normalized lookup in `LoginController`, and `EmailUniquenessConstraintTest` (whitespace and case variants, legacy padded-record lookup without rewriting the stored record). Original `users_email_unique` index preserved; no account data rewritten or deleted.

## Non-scope notes

- No sessions table exists by design; test env uses the array session driver, dev uses file. `ScaffoldTest` asserts the dev database stays absent.
- Production deployment, proxy configuration, and Unicode-collation edge behavior are documented limits, not approvals.
