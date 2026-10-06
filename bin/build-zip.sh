#!/usr/bin/env bash
# Builds the distributable plugin (build/pnscripts-tabwise/ and build/pnscripts-tabwise-<version>.zip),
# leaving out everything listed in .distignore (tests, tooling, vendor, screenshots).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="pnscripts-tabwise"
VERSION="$(sed -n 's/^ \* Version: *//p' "$ROOT/$SLUG.php" | head -1 | tr -d '[:space:]')"
OUT="$ROOT/build"
rm -rf "$OUT"
mkdir -p "$OUT/$SLUG"
rsync -a --exclude-from="$ROOT/.distignore" --exclude=/package.json --exclude=/package-lock.json "$ROOT/" "$OUT/$SLUG/"
( cd "$OUT" && zip -qr "$SLUG-$VERSION.zip" "$SLUG" )
echo "$OUT/$SLUG-$VERSION.zip ($(du -h "$OUT/$SLUG-$VERSION.zip" | cut -f1))"
find "$OUT/$SLUG" -type f | sed "s#$OUT/##" | sort
