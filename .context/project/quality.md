# Quality Configuration

Normative baseline: `.context/policies/core/code-quality.md`; governance mode starter. Configured initial application checks:

```sh
docker compose run --rm --no-deps api composer validate --strict
docker compose run --rm --no-deps api composer check-platform-reqs
docker compose run --rm --no-deps api composer audit
docker compose run --rm --no-deps api composer lint
docker compose run --rm --no-deps api composer analyse
docker compose --profile test run --rm test
```

Pint read-only; Larastan level 10 without a suppression baseline over `app/`, `bootstrap/app.php`, `routes/` and `tests/`; PHPUnit runs on isolated PostgreSQL 18. Dependency versions are in composer.lock and PHP 8.5 images are pinned by digest. Actual execution evidence: FEAT-7/8/24 verification.md. `.github/workflows/quality.yml` defines matching PR/main gates; remote CI execution is not claimed. Progressive checks in QUALITY_GATES.md.

Required CSD checks:

```sh
python3 scripts/validate_context.py --strict
python3 scripts/validate_context.py --strict --examples
python3 -m unittest discover -s tests -v
npx @owlcodium/context-spec-develop@1.2.1 doctor --scope project
```

Official validator provenance/hashes remain in `.csd-source.json`. The local adapter disables public-kit neutrality scanning and excludes installed Composer `vendor/` package documentation from project-owned link/placeholder checks; it leaves all project context, schemas, policies and active work items under validation. `--examples` warns when no example suite is installed while real work items still validate.

No automated cognitive/cyclomatic metric or duplication threshold is configured; do not claim measured values for either. CSD structural/code-review defaults still apply. Changed-feature tests are required; the CI coverage job enforces a measured floor of 100% statements via pcov over the merged report (`composer test:coverage`: one pgsql leg under `phpunit.xml`, one sqlite leg under `phpunit.sqlite.xml` — which excludes only pgsql/Compose-dependent test files, documented in that XML; no app file leaves the report — then `scripts/merge-coverage.php` and `scripts/coverage-floor.php`). Two executions are required because driver branches are mutually exclusive (`ilike` vs `like`/`json_extract`, `EmailLookup`, `LoginController`), and no line is excluded from the report: `@codeCoverageIgnore`, `<exclude>` in `<source>` and ignore lists are forbidden, so an uncovered line needs a test or a recorded human decision. Quality owner: yanpenalva for this approved plan; production operational owner NOT FOUND.
