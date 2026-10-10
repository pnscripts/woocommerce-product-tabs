#!/usr/bin/env bash
# Regenerates languages/*.pot, the bundled bg_BG/de_DE/pl_PL .po files and their .mo files.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WP_CLI="$ROOT/.cache/wp-cli.phar"
[ -f "$WP_CLI" ] || curl -fsSL -o "$WP_CLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
"$ROOT/bin/build-zip.sh" > /dev/null
php -d memory_limit=1G "$WP_CLI" i18n make-pot "$ROOT/build/pnscripts-tabcrest" "$ROOT/languages/pnscripts-tabcrest.pot" \
	--domain=pnscripts-tabcrest --slug=pnscripts-tabcrest --package-name="PN Scripts Tabcrest" \
	--headers='{"Report-Msgid-Bugs-To":"https://github.com/pnscripts/pnscripts-tabcrest/issues"}' --exclude=vendor,tests
python3 "$ROOT/bin/i18n/translations.py" "$ROOT"
php -d memory_limit=1G "$WP_CLI" i18n make-mo "$ROOT/languages"
php -d memory_limit=1G "$WP_CLI" i18n make-php "$ROOT/languages"
