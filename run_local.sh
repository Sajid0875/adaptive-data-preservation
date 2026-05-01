#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

DB_NAME=${DB_NAME:-universe_preservation}
HOST=${DB_HOST:-127.0.0.1}
PORT=${DB_PORT:-5432}
USER_NAME=${DB_USER:-${USER:-${LOGNAME:-}}}

if [[ -z "${USER_NAME}" ]]; then
  USER_NAME="postgres"
fi

log() {
  printf "%s\n" "$*"
}

try_psql() {
  PGPASSWORD="${DB_PASS:-}" psql -h "$HOST" -p "$PORT" -U "$USER_NAME" "$@"
}

ensure_postgres_running() {
  if try_psql -d postgres -c "SELECT 1" >/dev/null 2>&1; then
    return 0
  fi

  if command -v brew >/dev/null 2>&1; then
    if brew services list 2>/dev/null | grep -q "postgresql@15"; then
      log "Starting PostgreSQL via Homebrew services (postgresql@15)..."
      brew services start postgresql@15 >/dev/null || true
      sleep 0.8
    elif brew services list 2>/dev/null | grep -q "postgresql"; then
      log "Starting PostgreSQL via Homebrew services (postgresql)..."
      brew services start postgresql >/dev/null || true
      sleep 0.8
    fi
  fi

  if ! try_psql -d postgres -c "SELECT 1" >/dev/null 2>&1; then
    log ""
    log "ERROR: Can't connect to PostgreSQL."
    log "Tried: psql -h $HOST -p $PORT -U $USER_NAME -d postgres"
    log "Fix: start Postgres, or set DB_USER/DB_PASS/DB_HOST/DB_PORT env vars."
    exit 1
  fi
}

ensure_db_exists() {
  local exists
  exists=$(try_psql -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" 2>/dev/null || true)
  if [[ "$exists" != "1" ]]; then
    log "Creating database: $DB_NAME"
    createdb -h "$HOST" -p "$PORT" -U "$USER_NAME" "$DB_NAME"
  fi
}

ensure_schema_loaded() {
  local has_universes
  has_universes=$(try_psql -d "$DB_NAME" -tAc "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='universes'" 2>/dev/null || true)

  if [[ "$has_universes" != "1" ]]; then
    log "Loading schema + functions + sample data..."
    try_psql -d "$DB_NAME" -f database/01_schema.sql
    try_psql -d "$DB_NAME" -f database/02_functions_triggers_views.sql
    try_psql -d "$DB_NAME" -f database/03_sample_data.sql
  fi
}

start_php_server() {
  local port=8000
  if command -v lsof >/dev/null 2>&1; then
    lsof -ti tcp:"$port" | xargs -r kill -9 >/dev/null 2>&1 || true
  fi

  log "Starting PHP server on http://127.0.0.1:${port}/"
  nohup env APP_DEBUG=1 php -S 127.0.0.1:"$port" -t . >/tmp/php8000.log 2>&1 &
  echo $! >/tmp/php8000.pid

  sleep 0.5
}

open_browser() {
  local url="http://127.0.0.1:8000/"
  if command -v open >/dev/null 2>&1; then
    open "$url" || true
  fi
  log "Open: $url"
  log "Login: admin / admin123"
  log "Server log: /tmp/php8000.log"
}

ensure_postgres_running
ensure_db_exists
ensure_schema_loaded
start_php_server
open_browser
