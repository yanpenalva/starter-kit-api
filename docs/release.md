# 🚢 API Image Release, Deploy and Rollback

Releases are immutable OCI images tagged `api-<UTC-date>-<12-char-commit-sha>` in GHCR. Never move or overwrite a release tag. The deploy workflow builds and pushes the image, switches the remote Compose project to that tag, runs database migrations, restarts the API/worker/scheduler and runs the reusable login → my-profile → logout smoke test.

## One-time environment configuration

Configure GitHub production environment secrets `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS` and `DEPLOY_PATH`; configure repository secrets `SMOKE_EMAIL` and `SMOKE_PASSWORD`, plus repository variables `API_BASE_URL` and `SMOKE_ORIGIN`. `SMOKE_ORIGIN` must be an origin allowed by the API's stateful session/CORS configuration. Provision the smoke account in the target environment. The deployment host must have Docker Compose v2, the release Compose file and application configuration in `DEPLOY_PATH`. Its external `.env` must define `APP_KEY`, `APP_URL`, `RELEASE_DB_CREDENTIAL`, and optionally `RELEASE_POSTGRES_DB`, `RELEASE_POSTGRES_USER`, `API_PORT`; keep these values out of Git. The remote host must be able to pull the GHCR package.

The workflow runs on `workflow_dispatch` (with API base URL and smoke origin) and `v*` tags. A `v*` Git tag is a trigger only; the deployed image always receives the generated immutable `api-<date>-<sha>` tag, and the workflow refuses to overwrite a tag already present in GHCR. Configure GitHub's production environment approval gate before using this workflow. Production release authorization remains with the human approver.

## Deploy and rollback

The deploy job sets `API_IMAGE` to the image built for that workflow run and applies it with `docker compose --profile release up -d`. To roll back, set `API_IMAGE` in the deployment host's `.env` to the previous immutable `api-<date>-<sha>` image, then run:

```sh
docker compose --profile release up -d app worker-release scheduler-release
```

Run the reusable smoke workflow against the rolled-back URL and observe the environment before closing the release. Do not delete database volumes during deploy or rollback. A migration that is not backward-compatible requires a separately approved data rollback plan.

## Ephemeral local release stack

Use a unique Compose project name and an isolated `.env` containing disposable `APP_KEY`, `APP_URL`, `RELEASE_DB_CREDENTIAL` and `API_IMAGE` values. Build/publish the image in CI or on a Docker-enabled host; this repository's current development host has no Docker daemon. The example generates a fresh project name so the volume deletion on teardown cannot select another worktree's data.

```sh
project="starter-release-test-$(date +%s)"
docker compose -p "$project" --profile release up -d --wait postgres-release redis-release
docker compose -p "$project" --profile release run --rm --no-deps app php artisan migrate --force
docker compose -p "$project" --profile release up -d app worker-release scheduler-release
docker compose -p "$project" --profile release down --volumes
```

The stack and its database volume are removed by the final command. Keep the generated project name private to this test run and never use `down --volumes` with the shared development project.

Readiness is exposed publicly at `GET /api/v1/health`. It returns HTTP 200 when `database`, `redis` and the recent `failed_jobs` check are healthy, and HTTP 503 with boolean per-check status otherwise. The recent failure window defaults to five minutes and is configurable with `HEALTH_FAILED_JOBS_WINDOW_MINUTES`.

## TLS termination and log format

TLS terminates at the edge (load balancer / CDN / ingress), never in the API container: the release profile serves plain HTTP on 8080 behind the proxy, and HSTS is enforced by the API security headers so browsers upgrade. Do not expose the release `API_PORT` directly to the internet without a TLS-terminating proxy in front.

Release logs are JSON on stderr (`LOG_CHANNEL=stderr` with `Monolog\Formatter\JsonFormatter`), ready for a centralized collector (CloudWatch, Stackdriver, Loki). Local development keeps human-readable single-file logs.
