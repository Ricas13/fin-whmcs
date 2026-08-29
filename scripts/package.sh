#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-dev}"
OUT="$ROOT/build"
STAGE="$OUT/captainfin-whmcs-$VERSION"

rm -rf "$STAGE"
mkdir -p "$STAGE"
php "$ROOT/scripts/validate-package.php"
cp -R "$ROOT/modules" "$STAGE/modules"

mkdir -p "$OUT"
(
  cd "$OUT"
  rm -f "captainfin-whmcs-$VERSION.zip"
  zip -qr "captainfin-whmcs-$VERSION.zip" "captainfin-whmcs-$VERSION"
)
rm -rf "$STAGE"

echo "$OUT/captainfin-whmcs-$VERSION.zip"
