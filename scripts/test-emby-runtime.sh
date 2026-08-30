#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

RUNTIME_DIR="$ROOT/.runtime"
TOKEN_FILE="$RUNTIME_DIR/emby-token"
BASE_URL_FILE="$RUNTIME_DIR/emby-base-url"

cleanup() {
  rm -rf "$RUNTIME_DIR"
  docker compose -f docker-compose.emby-test.yml down -v >/dev/null 2>&1 || true
}
trap cleanup EXIT

rm -rf "$RUNTIME_DIR"
mkdir -p "$RUNTIME_DIR"
docker compose -f docker-compose.emby-test.yml down -v >/dev/null 2>&1 || true
docker compose -f docker-compose.emby-test.yml up -d mariadb emby

# Emby can finish its first-run API bootstrap before MariaDB has completed
# InnoDB initialization. Gate SQL explicitly so the mounted lifecycle evidence
# never depends on relative container startup speed.
for _ in $(seq 1 60); do
  if docker compose -f docker-compose.emby-test.yml exec -T mariadb \
      healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
if ! docker compose -f docker-compose.emby-test.yml exec -T mariadb \
    healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
  echo "MariaDB did not become ready for the Emby runtime suite." >&2
  exit 1
fi

export CAPTAINFIN_TEST_EMBY_ORIGIN="http://127.0.0.1:18097"
export CAPTAINFIN_TEST_EMBY_TOKEN_FILE="$TOKEN_FILE"
export CAPTAINFIN_TEST_EMBY_BASE_URL_FILE="$BASE_URL_FILE"
php scripts/bootstrap-emby-test.php
export CAPTAINFIN_TEST_EMBY_URL="$(tr -d '\r\n' < "$BASE_URL_FILE")"
export CAPTAINFIN_TEST_EMBY_API_KEY="$(tr -d '\r\n' < "$TOKEN_FILE")"

vendor/bin/phpunit tests/Runtime/EmbyLifecycleRuntimeTest.php
