#!/usr/bin/env bash
set -Eeuo pipefail

BASE_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${BASE_PATH}/.env"
BACKUP_DIR="${BACKUP_DIR:-${BASE_PATH}/storage/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

if [[ ! -f "${ENV_FILE}" ]]; then echo "Arquivo .env não encontrado" >&2; exit 1; fi
set -a
# shellcheck disable=SC1090
source "${ENV_FILE}"
set +a
mkdir -p "${BACKUP_DIR}"
STAMP="$(date +%Y%m%d_%H%M%S)"
TARGET="${BACKUP_DIR}/${DB_DATABASE}_${STAMP}.sql.gz"
TEMP="${TARGET}.tmp"

mysqldump --single-transaction --quick --routines --triggers --host="${DB_HOST}" --port="${DB_PORT:-3306}" --user="${DB_USERNAME}" --password="${DB_PASSWORD}" "${DB_DATABASE}" | gzip -9 > "${TEMP}"
mv "${TEMP}" "${TARGET}"
find "${BACKUP_DIR}" -type f -name '*.sql.gz' -mtime "+${RETENTION_DAYS}" -delete
printf 'Backup criado: %s\n' "${TARGET}"
