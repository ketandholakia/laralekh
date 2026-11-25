#!/usr/bin/env bash
# Production MySQL 8.4 backup script for LaraLekh.
# Preserves InnoDB point-in-time consistency, all immutability triggers,
# routines, and events, and writes a SHA-256 verification manifest.
set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:?DB_DATABASE must be set}"
DB_USERNAME="${DB_USERNAME:?DB_USERNAME must be set}"
DB_PASSWORD="${DB_PASSWORD:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/laravix}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT_DIR="${BACKUP_DIR%/}/daily"
mkdir -p "$OUT_DIR"

DUMP_FILE="${OUT_DIR}/${DB_DATABASE}_${TIMESTAMP}.sql.gz"
SHA_FILE="${DUMP_FILE}.sha256"

MYSQL_AUTH_ARGS=(-h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME")
if [ -n "$DB_PASSWORD" ]; then
  export MYSQL_PWD="$DB_PASSWORD"
fi

mysqldump "${MYSQL_AUTH_ARGS[@]}" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  --hex-blob \
  --set-gtid-purged=OFF \
  "$DB_DATABASE" | gzip -9 > "$DUMP_FILE"

sha256sum "$DUMP_FILE" > "$SHA_FILE"
chmod 600 "$DUMP_FILE" "$SHA_FILE"

# Prune daily backups older than RETENTION_DAYS
find "$OUT_DIR" -type f -name "${DB_DATABASE}_*.sql.gz*" -mtime "+${RETENTION_DAYS}" -delete

echo "Backup completed: ${DUMP_FILE}"
echo "Checksum manifest: ${SHA_FILE}"
