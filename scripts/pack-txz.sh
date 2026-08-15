#!/usr/bin/env bash
# Build StorageGuard-VERSION-x86_64-1.txz (runtime only).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
VERSION="${1:-$(sed -n 's/.*ENTITY version "\([^"]*\)".*/\1/p' storageguard.plg | head -1)}"
PKG="StorageGuard-${VERSION}-x86_64-1"
STAGE=$(mktemp -d); trap 'rm -rf "$STAGE"' EXIT
DEST="$STAGE/usr/local/emhttp/plugins/StorageGuard"
mkdir -p "$DEST"
for f in StorageGuard.page default.cfg README.md \
  sg-update.php storageguard.js storageguard.css storageguard-color.js \
  get-config.php check-alerts.php sg-lib.php sg-pool-math.php
do
  cp -a "$ROOT/$f" "$DEST/$f"
done
mkdir -p "$ROOT/archive"
OUT="$ROOT/archive/${PKG}.txz"
rm -f "$OUT"
( cd "$STAGE" && tar --owner=0 --group=0 --numeric-owner -cJf "$OUT" . )
ls -la "$OUT"
echo "files: $(tar -tJf "$OUT" | wc -l)"
