#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

cleanup() {
  docker compose -f docker-compose.test.yml down -v >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker compose -f docker-compose.test.yml up -d mariadb jellyfin

echo "MariaDB/Jellyfin test services started."
echo "Jellyfin first-run setup currently requires provisioning an API key before API runtime tests can execute."
echo "Once CAPTAINFIN_TEST_JELLYFIN_API_KEY is exported, run: vendor/bin/phpunit tests/Runtime"
