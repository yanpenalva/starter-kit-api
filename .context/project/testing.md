# Testing Context

- Application tests use PHPUnit 12 inside PHP 8.5; initial Feature suite covers boot, JSON availability, framework health, route boundaries and PostgreSQL test isolation.
- Test service connects on an internal test-only network to its own PostgreSQL 18 container, database/user starter_api_test and ephemeral filesystem; development uses a separate service/database/credentials. No data from legacy or production.
- Run `docker compose --profile test run --rm test`; bare composer test requires correctly configured isolated test connection. PHPUnit forces test DB/name/user; Compose/CI supply its host/password.
- Boot database checks do not reset development data.
- Python context regression tests remain in tests/ and run with unittest separately.
- Initial GitHub Actions workflow defines context/application gates for PR/main; remote execution is not claimed without a push/PR.
- CI expands with owning feature tests and OpenAPI contracts.
