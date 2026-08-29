#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

RUNTIME_DIR="$ROOT/.runtime"
TOKEN_FILE="$RUNTIME_DIR/jellyfin-token"

cleanup() {
  rm -rf "$RUNTIME_DIR"
  docker compose -f docker-compose.test.yml down -v >/dev/null 2>&1 || true
}
trap cleanup EXIT

rm -rf "$RUNTIME_DIR"
mkdir -p "$RUNTIME_DIR"
docker compose -f docker-compose.test.yml down -v >/dev/null 2>&1 || true
docker compose -f docker-compose.test.yml up -d mariadb jellyfin

export CAPTAINFIN_TEST_JELLYFIN_URL="http://127.0.0.1:18096"
export CAPTAINFIN_TEST_JELLYFIN_TOKEN_FILE="$TOKEN_FILE"
php scripts/bootstrap-jellyfin-test.php
export CAPTAINFIN_TEST_JELLYFIN_HOST="127.0.0.1"
export CAPTAINFIN_TEST_JELLYFIN_PORT="18096"
export CAPTAINFIN_TEST_JELLYFIN_API_KEY="$(tr -d '\r\n' < "$TOKEN_FILE")"

vendor/bin/phpunit tests/Runtime
