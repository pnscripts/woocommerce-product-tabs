#!/usr/bin/env bash
# WP-CLI against the disposable site built by bin/setup-wp.sh.
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
exec php -d memory_limit=1G "$ROOT/.cache/wp-cli.phar" --path="${WP_DIR:-$ROOT/.cache/wp}" "$@"
