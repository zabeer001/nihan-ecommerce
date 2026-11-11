#!/usr/bin/env bash

set -Eeuo pipefail

# Order Management deployment helper for local publishing and direct
# production deployment.
#
# Usage:
#   ./bash/cicd_bash.sh local "commit message"
#   ./bash/cicd_bash.sh production
#
# Production deployments preserve the existing database and run only
# outstanding migrations.

MODE="${1:-local}"
MODE_ARGUMENT="${2:-}"
PULL_LATEST=""
COMMIT_MESSAGE=""
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT_PROJECT_DIR="$(cd -- "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${SCRIPT_PROJECT_DIR}/.env"

read_env_value() {
    local key="$1"
    local value
    [[ -f "${ENV_FILE}" ]] || return 0
    value="$(awk -v target="${key}" 'index($0, target "=") == 1 { print substr($0, length(target) + 2); exit }' "${ENV_FILE}")"
    value="${value%$'\r'}"
    if [[ "${value}" == \"*\" && "${value}" == *\" ]]; then
        value="${value:1:${#value}-2}"
    elif [[ "${value}" == \'*\' && "${value}" == *\' ]]; then
        value="${value:1:${#value}-2}"
    fi
    printf '%s' "${value}"
}

case "${MODE}" in
    local)
        COMMIT_MESSAGE="${MODE_ARGUMENT}"
        ;;
    production|prod)
        MODE="production"
        PULL_LATEST="${MODE_ARGUMENT}"
        ;;
    -h|--help|help)
        printf '%s\n' \
            "Usage:" \
            "  $0 local \"commit message\"" \
            "  $0 production [--already-pulled]"
        exit 0
        ;;
    *)
        echo "Unknown environment: ${MODE}" >&2
        echo "Use: $0 local | production [--already-pulled]" >&2
        exit 2
        ;;
esac

if [[ "${MODE}" == "production" && -n "${PULL_LATEST}" && "${PULL_LATEST}" != "--already-pulled" ]]; then
    echo "Unknown option: ${PULL_LATEST}" >&2
    exit 2
fi

PROJECT_DIR="${SCRIPT_PROJECT_DIR}"
if [[ "${MODE}" == "production" ]]; then
    VPS_PROJECT_DIR="${VPS_PROJECT_DIR:-$(read_env_value VPS_PROJECT_DIR)}"
    if [[ -z "${VPS_PROJECT_DIR}" && "${PULL_LATEST}" == "--already-pulled" ]]; then
        VPS_PROJECT_DIR="${SCRIPT_PROJECT_DIR}"
    fi
    if [[ -z "${VPS_PROJECT_DIR}" ]]; then
        echo "Set VPS_PROJECT_DIR to the production checkout path in .env." >&2
        exit 1
    fi
    if [[ -d "${VPS_PROJECT_DIR}" ]]; then
        PROJECT_DIR="${VPS_PROJECT_DIR}"
    elif [[ "${PULL_LATEST}" == "--already-pulled" ]]; then
        echo "Production checkout does not exist: ${VPS_PROJECT_DIR}" >&2
        exit 1
    fi
fi

cd "${PROJECT_DIR}"
ENV_FILE="${PROJECT_DIR}/.env"

if [[ ! -f "${PROJECT_DIR}/.env" ]]; then
    echo "Missing ${PROJECT_DIR}/.env. Create it before deploying." >&2
    exit 1
fi

# When launched from a workstation, production mode connects to the VPS.
# On the VPS, the target directory exists and deployment continues locally.
if [[ "${MODE}" == "production" && ! -d "${VPS_PROJECT_DIR}" ]]; then
    ROOT_ACCESS_VALUE="${VPS_ROOT_ACCESS:-$(read_env_value VPS_ROOT_ACCESS)}"
    DEPLOY_PASSWORD="${VPS_PASSWORD:-$(read_env_value VPS_PASSWORD)}"
    SSH_TARGET="${ROOT_ACCESS_VALUE#ssh }"
    SSH_COMMAND=(ssh -o StrictHostKeyChecking=accept-new)

    if [[ ! "${SSH_TARGET}" =~ ^[A-Za-z0-9._-]+@[A-Za-z0-9._:-]+$ ]]; then
        echo 'Set VPS_ROOT_ACCESS in .env to "ssh user@host" for direct deployment.' >&2
        exit 1
    fi
    if [[ -n "${DEPLOY_PASSWORD}" ]]; then
        command -v sshpass >/dev/null 2>&1 || { echo "sshpass is required for password-based deployment." >&2; exit 1; }
        export SSHPASS="${DEPLOY_PASSWORD}"
        SSH_COMMAND=(sshpass -e "${SSH_COMMAND[@]}")
    fi

    VPS_APP_CONTAINER="${VPS_APP_CONTAINER:-$(read_env_value VPS_APP_CONTAINER)}"
    printf -v QUOTED_REMOTE_DIR '%q' "${VPS_PROJECT_DIR}"
    printf -v QUOTED_APP_CONTAINER '%q' "${VPS_APP_CONTAINER}"
    REMOTE_COMMAND="cd ${QUOTED_REMOTE_DIR} && git restore --source=HEAD --staged --worktree -- public/build && git clean -fd -- public/build && git pull --ff-only origin main && { test -f bash/cicd_bash.sh || { echo 'bash/cicd_bash.sh is not present on origin/main. Run the local publish command first.' >&2; exit 1; }; } && chmod +x bash/cicd_bash.sh && VPS_PROJECT_DIR=${QUOTED_REMOTE_DIR} VPS_APP_CONTAINER=${QUOTED_APP_CONTAINER} ./bash/cicd_bash.sh production --already-pulled"

    echo "==> Connecting to the production server and starting deployment"
    exec "${SSH_COMMAND[@]}" "${SSH_TARGET}" "${REMOTE_COMMAND}"
fi

command -v docker >/dev/null 2>&1 || { echo "Docker is required." >&2; exit 1; }

app() {
    docker exec "${APP_CONTAINER}" "$@"
}

require_running_container() {
    if [[ -z "${APP_CONTAINER}" ]]; then
        echo "No Laravel container found for Compose service ${APP_SERVICE}. Start the app service first." >&2
        exit 1
    fi
    if [[ "$(docker inspect -f '{{.State.Running}}' "${APP_CONTAINER}" 2>/dev/null || true)" != "true" ]]; then
        echo "The Docker container ${APP_CONTAINER} is not running." >&2
        exit 1
    fi
}

start_production_database() {
    local compose_file="${PROJECT_DIR}/docker-compose.prod.yml"
    local mysql_container_id=""
    local mysql_status=""

    if [[ ! -f "${compose_file}" ]]; then
        echo "Missing ${compose_file}." >&2
        exit 1
    fi

    docker compose -f "${compose_file}" up -d mysql
    mysql_container_id="$(docker compose -f "${compose_file}" ps -q mysql)"

    if [[ -z "${mysql_container_id}" ]]; then
        echo "The production MySQL container could not be created or started." >&2
        exit 1
    fi

    for _ in {1..30}; do
        mysql_status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else if .State.Running}}running{{else}}stopped{{end}}' "${mysql_container_id}" 2>/dev/null || true)"
        if [[ "${mysql_status}" == "healthy" || "${mysql_status}" == "running" ]]; then
            return 0
        fi
        if [[ "${mysql_status}" == "stopped" ]]; then
            break
        fi
        sleep 2
    done

    echo "MySQL did not become healthy (status: ${mysql_status:-unknown})." >&2
    docker logs --tail 80 "${mysql_container_id}" >&2 || true
    exit 1
}

step() {
    echo
    echo "==> $*"
}

if [[ "${MODE}" == "local" ]]; then
    if [[ "$(git branch --show-current)" != "main" ]]; then
        echo "Local publishing must be run from the main branch." >&2
        exit 1
    fi

    APP_SERVICE="laravel.test"
    APP_CONTAINER="${LOCAL_CONTAINER:-$(read_env_value LOCAL_CONTAINER)}"
    APP_CONTAINER="${APP_CONTAINER:-$(docker compose ps -q "${APP_SERVICE}")}"
    require_running_container

    step "Building production frontend assets"
    app npm run build

    step "Staging local changes"
    git add .

    if git diff --cached --quiet; then
        echo "No staged changes to commit. Continuing with push."
    else
        if [[ -z "${COMMIT_MESSAGE}" && -t 0 ]]; then
            read -r -p "Commit message: " COMMIT_MESSAGE
        fi
        if [[ -z "${COMMIT_MESSAGE}" ]]; then
            echo "A commit message is required." >&2
            echo "Use: $0 local \"your commit message\"" >&2
            exit 1
        fi

        step "Committing local changes"
        git commit -m "${COMMIT_MESSAGE}"
    fi

    step "Pushing main to origin"
    git push origin main

    step "Local publish completed successfully"
    exit 0
fi

if [[ "${PULL_LATEST}" != "--already-pulled" ]]; then
    step "Removing stale production frontend assets"
    git restore --source=HEAD --staged --worktree -- public/build
    git clean -fd -- public/build

    step "Pulling main from origin"
    git pull --ff-only origin main
fi

APP_SERVICE="laravel.test2"
APP_CONTAINER="${VPS_APP_CONTAINER:-$(read_env_value VPS_APP_CONTAINER)}"
APP_CONTAINER="${APP_CONTAINER:-$(docker compose -f docker-compose.prod.yml ps -q "${APP_SERVICE}")}"
require_running_container

step "Starting production database"
start_production_database

if ! app getent hosts mysql >/dev/null 2>&1; then
    echo "The Laravel container cannot resolve the MySQL service hostname." >&2
    echo "Ensure Laravel and MySQL are attached to the same Docker Compose network." >&2
    exit 1
fi

PRODUCTION_APP_KEY="$(read_env_value APP_KEY)"
if [[ -z "${PRODUCTION_APP_KEY}" ]]; then
    echo "APP_KEY is missing from ${PROJECT_DIR}/.env. Deployment stopped before clearing caches." >&2
    echo "Configure a persistent production APP_KEY, then run the deployment again." >&2
    exit 1
fi

step "Installing production PHP dependencies"
app composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

step "Running outstanding production migrations"
app php artisan migrate --force

step "Rebuilding Laravel caches"
app php artisan optimize

step "Deployment completed successfully (${MODE})"
