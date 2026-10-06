#!/usr/bin/env bash
# Regenerates languages/*.pot, the bundled bg_BG/de_DE/pl_PL .po files and their .mo files.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WP_CLI="$ROOT/.cache/wp-cli.phar"
[ -f "$WP_CLI" ] || curl -fsSL -o "$WP_CLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
"$ROOT/bin/build-zip.sh" > /dev/null
php -d memory_limit=1G "$WP_CLI" i18n make-pot "$ROOT/build/pnscripts-tabwise" "$ROOT/languages/pnscripts-tabwise.pot" \
	--domain=pnscripts-tabwise --slug=pnscripts-tabwise --package-name="PN Scripts Tabwise" \
	--headers='{"Report-Msgid-Bugs-To":"https://github.com/pnscripts/woocommerce-product-tabs/issues"}' --exclude=vendor,tests
python3 "$ROOT/bin/i18n/translations.py" "$ROOT"
php -d memory_limit=1G "$WP_CLI" i18n make-mo "$ROOT/languages"
php -d memory_limit=1G "$WP_CLI" i18n make-php "$ROOT/languages"
