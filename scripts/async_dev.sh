#!/bin/sh
# FEAT-20 dev-only async operations script (worker/scheduler/smoke/cleanup).
# NEVER touches the primary `starter-kit-api` compose project: everything
# runs under the isolated project `starter-wave2-async` with its own image
# tag and 127.0.0.1-only ports (18080/18025 by default).
#
# Usage:
#   ENV_FILE=/path/to/primary/.env scripts/async_dev.sh up
#   ENV_FILE=... scripts/async_dev.sh smoke
#   ENV_FILE=... scripts/async_dev.sh cleanup
#
# The env file is passed to Docker via --env-file at RUNTIME only; it is
# never read, copied or printed by this script.
set -eu

PROJECT=starter-wave2-async
IMAGE=starter-wave2-async-api:local

# F2 guard: compose.yaml pins the shared project name `starter-kit-api` (same
# as the primary checkout). This script must therefore NEVER operate on that
# project — a `down -v` here would destroy the primary dev volume.
if [ "$PROJECT" = "starter-kit-api" ]; then
    echo "refusing: PROJECT '$PROJECT' is the primary compose stack" >&2
    exit 2
fi

ENV_FILE="${ENV_FILE:?Set ENV_FILE to the runtime env file path (Docker --env-file only)}"
export API_DEV_IMAGE="$IMAGE"
export HTTP_PORT="${HTTP_PORT:-18080}"
export MAILPIT_PORT="${MAILPIT_PORT:-18025}"

compose() {
    # shellcheck disable=SC2086
    docker compose -p "$PROJECT" --env-file "$ENV_FILE" "$@"
}

case "${1:-}" in
    up)
        compose --profile async up -d --wait
        ;;
    smoke)
        # All probe/dispatch traffic goes through the worker container:
        # only the async slice carries REDIS/SMTP configuration.
        compose exec -T worker php artisan infra:probe
        echo "worker status:"
        compose ps worker scheduler
        token="$(compose exec -T worker php artisan infra:smoke-mail | sed -n 's/.*token=\([0-9a-f-]\{36\}\).*/\1/p')"
        echo "dispatched synthetic mail token=$token"
        i=0
        while [ "$i" -lt 30 ]; do
            found="$(curl -fsS "http://127.0.0.1:${MAILPIT_PORT}/api/v1/messages" | python3 -c "
import json,sys
data=json.load(sys.stdin)
msgs=[m for m in data.get('messages',[]) if '${token}' in str(m.get('Subject',''))]
for m in msgs:
    tos=','.join(t.get('Address','') for t in m.get('To',[]))
    print(f\"delivered subject={m.get('Subject','')} to={tos}\")
" 2>/dev/null || true)"
            if [ -n "$found" ]; then
                echo "$found"
                break
            fi
            i=$((i + 1))
            sleep 1
        done
        [ -n "$found" ] || { echo "FAIL: synthetic message never reached Mailpit" >&2; exit 1; }
        ;;
    cleanup)
        compose --profile async --profile test down -v --remove-orphans
        ;;
    *)
        echo "usage: $0 up|smoke|cleanup (ENV_FILE required)" >&2
        exit 2
        ;;
esac
