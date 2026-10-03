# starter-kit-api

Independent Laravel 13 API for the Starter Kit ecosystem, running on PHP 8.5 through Docker Compose. No identity, RBAC, audit or business endpoints are implemented yet.
Known stack and pending decisions: [.context/project/stack.md](.context/project/stack.md) and [.context/project/architecture.md](.context/project/architecture.md).

Accepted architecture for [issue #1](https://github.com/yanpenalva/starter-kit-api/issues/1): [incremental ADRs](docs/adr/README.md), approved by yanpenalva on 2026-10-01 ([evidence](.context/work/FEAT-1/approval.md)). Architecture acceptance is distinct from runtime implementation: this delivery implements the platform scaffold only. ADR 0006 has been incorporated into ADRs 0001/0002/0004 with its historical reference preserved.

## Local API setup

Requirements: Linux, Docker Engine with Docker Compose, Python 3, and network access for image/package downloads. PHP 8.5 and Composer run inside Docker; host PHP is not used. The development image is not a production image.

From a fresh checkout:

```sh
python3 scripts/setup_local_env.py
docker compose build api
docker compose run --rm --no-deps api composer install --no-interaction --prefer-dist
docker compose run --rm --no-deps api php artisan key:generate --no-interaction
docker compose up -d --wait
curl --fail http://localhost:8080/api/v1
curl --fail http://localhost:8080/up
```

The environment script creates `.env` once with distinct random local database passwords and the current Linux UID/GID. It refuses to overwrite an existing configuration. No secrets are printed or versioned. Set `HTTP_PORT` in `.env` to change the default HTTP port; `APP_DEBUG` stays false. PHP-FPM uses the same UID/GID as the checkout so generated files remain owned by the developer.

`GET /api/v1` returns `{"status":"ok"}`. `/up` is Laravel's framework health endpoint; it verifies application boot, not dependency readiness. `/` has no frontend. API errors render JSON; authentication and its middleware will be implemented in their own issues. There are no business migrations or default users/admin accounts.

Development migration command, when migrations are introduced:

```sh
docker compose exec api php artisan migrate --no-interaction
```

This command targets development, not the legacy or production database. Do not use `migrate:fresh` on development. Tests use a separate PostgreSQL container, an internal test-only network, distinct credentials and an ephemeral filesystem; the test service overrides the connection so it cannot use development defaults.

## Application quality gates

```sh
docker compose run --rm --no-deps api composer validate --strict
docker compose run --rm --no-deps api composer check-platform-reqs
docker compose run --rm --no-deps api composer audit
docker compose run --rm --no-deps api composer lint
docker compose run --rm --no-deps api composer analyse
docker compose --profile test run --rm test
```

`composer test` runs Pest; use the Compose `test` service to supply its isolated database. Pint is read-only through `composer lint`; Larastan runs at level 10 without a suppression baseline over application code, bootstrap, routes and PHP tests. The initial suite covers boot, JSON availability, framework health, route boundaries and PostgreSQL test isolation. Python context tests remain under `tests/` and run separately. [QUALITY_GATES.md](QUALITY_GATES.md) defines initial and progressive checks; CI runs these gates on PRs and `main`.

## Optional services and local operations

```sh
docker compose --profile async up -d --wait redis mailpit
docker compose ps
docker compose logs --tail=100 api nginx
docker compose down
```

Mailpit UI is available only on localhost, default port 8025 (`MAILPIT_PORT` overrides it). Redis and SMTP have no host ports. The scaffold still uses synchronous queues and the log mailer; enabling containers does not enable application integrations. No workers/scheduler are installed in this stage. Logs go to stderr; Nginx access logs are disabled to avoid capturing query strings. Never add credentials or personal data to logs. Correlation IDs/redaction for business inputs belong to the HTTP/observability feature deliveries.

`docker compose down` preserves the development database volume. Do not use `down --volumes` as a routine reset. The test database's tmpfs is disposable when its container is removed. On database authentication errors, check the local `.env` against the credentials used when the development volume was initialized; do not silently reset that volume. On permission errors, regenerate only UID/GID configuration as needed, not secrets or existing data. Docker daemon access is required; failures before image/container execution are environment limitations, not passing checks.

Scaffold provenance: [.scaffold-source.json](.scaffold-source.json). Work items: [.context/work/FEAT-7](.context/work/FEAT-7/work-item.json), [FEAT-8](.context/work/FEAT-8/work-item.json), [FEAT-24](.context/work/FEAT-24/work-item.json). Runtime dependencies are MIT/open-source as identified in their manifests; the application itself has no public distribution license selected (Composer's private-project `proprietary` marker is not a new license grant).

## CSD onboarding

Read [AGENTS.md](AGENTS.md), invoke `$csd` in Codex, and describe the intended outcome. Context is canonical under `.context/`.
Project onboarding follows `.context/prompts/initialize-project.md` and creates no work item. Unknown facts are `NOT FOUND`; revisit context after ADRs and scaffold.

Prerequisites for CSD tooling: Node.js >=22, npm and Python 3. Install and inspect the fixed version in this repository:

```sh
npx @owlcodium/context-spec-develop@1.2.1 install --scope project --agents codex
npx @owlcodium/context-spec-develop@1.2.1 discover --root .
npx @owlcodium/context-spec-develop@1.2.1 doctor --scope project
python3 scripts/validate_context.py --strict
python3 scripts/validate_context.py --strict --examples
python3 -m unittest discover -s tests -v
```

The committed context is the reproducible project snapshot. Installation restores harness activation; it does not overwrite project facts.
For a new adopting repository, review `bootstrap --root . --dry-run` before `bootstrap --root . --yes`, then run onboarding. Module scaffold is generic and already approved here.
Version is recorded in `.agents/.csd-lock.json` and `.context/config.json`; `.csd-bootstrap.json` records bootstrap ownership hashes.

## Kit packaging and validation

The npm 1.2.1 bootstrap omits workflows, reference adapters and the validator required by its own contract. Only these prerequisites and the kit license were supplemented from official tag `v1.2.1`, commit `6ca620e0491e94e0836bda29cd55b334b4f12aec`.
Provenance and hashes: [.csd-source.json](.csd-source.json). No local CSD checkout was used.
The upstream validator remains byte-for-byte preserved; [quality context](.context/project/quality.md) documents the two consuming-project adaptations and regression checks. `LICENSE` covers the CSD material; application licensing is `NOT FOUND`.

## Reviewed upgrades

Check npm release/version and official documentation, then review an upgrade PR. Preview before applying:

```sh
npx @owlcodium/context-spec-develop@1.2.1 update --scope project --dry-run
npx @owlcodium/context-spec-develop@1.2.1 update --scope project --context --dry-run
```

For an approved newer version, replace the explicit version in those commands, review diffs, then apply harness update and context update (`--context --yes`) separately. Never use `--force` to bypass local conflicts. Project facts, modules and work items remain project-owned. Review the shortened AGENTS entrypoint/orchestration changes as intentional conflicts; verify supplementary files and validator against the same immutable release before updating their hashes. Run all validation again.

## Issues and delivery

Roadmap ponta a ponta para distribuir entregas via CSD: [ordem, paralelismo e complexidades](.context/project/roadmap.md).

Onboarding: https://github.com/yanpenalva/starter-kit-api/issues/5. No artificial CSD work item represents initialization.
Future tasks follow [issue/work-item/PR conventions](.context/project/conventions.md) and [delivery authorization](.context/project/delivery.md).
Actual onboarding evidence and unresolved decisions: [.context/project/onboarding.md](.context/project/onboarding.md).
