#!/usr/bin/env bash
set -Eeuo pipefail

BASE_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${BASE_PATH}/.env"
if [[ ! -f "${ENV_FILE}" ]]; then echo "Arquivo .env não encontrado" >&2; exit 1; fi
set -a
# shellcheck disable=SC1090
source "${ENV_FILE}"
set +a

curl --fail-with-body --silent --show-error \
  --request POST "${APP_URL}/api/v1/internal/outbox/process" \
  --header "X-Worker-Secret: ${OUTBOX_WORKER_SECRET}" \
  --header "Accept: application/json" \
  --data '{}'
