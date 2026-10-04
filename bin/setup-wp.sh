#!/usr/bin/env bash
# Builds a disposable WordPress + WooCommerce site on SQLite (no Docker, no MySQL) for the integration
# tests and manual checks. Everything comes from wordpress.org and the official WP-CLI build.
#
#   bin/setup-wp.sh            # install into .cache/wp (WP_DIR overrides)
#   WP_VERSION=6.5 WC_VERSION=9.0.2 bin/setup-wp.sh   # minimum-version matrix
#
# The site URL is http://127.0.0.1:${WP_PORT:-8795}; start it with bin/serve.sh.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP_DIR="${WP_DIR:-$ROOT/.cache/wp}"
WP_VERSION="${WP_VERSION:-latest}"
WC_VERSION="${WC_VERSION:-latest-stable}"
WP_PORT="${WP_PORT:-8795}"
CACHE="$ROOT/.cache"
WP_CLI="$CACHE/wp-cli.phar"
SLUG="pnscripts-product-tabs"

mkdir -p "$CACHE"
if [ ! -f "$WP_CLI" ]; then
	curl -fsSL -o "$WP_CLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi
wp() { php -d memory_limit=1G "$WP_CLI" --path="$WP_DIR" --skip-themes=false "$@"; }

fetch_zip() { # $1 url, $2 target dir (parent)
	local tmp
	tmp="$(mktemp -d)"
	curl -fsSL -o "$tmp/pkg.zip" "$1"
	unzip -q -o "$tmp/pkg.zip" -d "$2"
	rm -rf "$tmp"
}

rm -rf "$WP_DIR"
mkdir -p "$WP_DIR"
wp core download --version="$WP_VERSION" --skip-content --force --quiet
mkdir -p "$WP_DIR/wp-content/plugins" "$WP_DIR/wp-content/themes" "$WP_DIR/wp-content/mu-plugins"

# SQLite Database Integration (WordPress Performance Team) as the db.php drop-in.
fetch_zip https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip "$WP_DIR/wp-content/plugins"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WP_DIR/wp-content/plugins/sqlite-database-integration#" \
	-e 's#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#' \
	"$WP_DIR/wp-content/plugins/sqlite-database-integration/db.copy" > "$WP_DIR/wp-content/db.php"

cat > "$WP_DIR/wp-config.php" <<PHP
<?php
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' );
define( 'DB_FILE', getenv( 'PNSCRIPTS_PT_DB_FILE' ) ?: '.ht.sqlite' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
define( 'DISABLE_WP_CRON', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'AUTH_KEY', 'local-test-only' );
define( 'SECURE_AUTH_KEY', 'local-test-only' );
define( 'LOGGED_IN_KEY', 'local-test-only' );
define( 'NONCE_KEY', 'local-test-only' );
define( 'AUTH_SALT', 'local-test-only' );
define( 'SECURE_AUTH_SALT', 'local-test-only' );
define( 'LOGGED_IN_SALT', 'local-test-only' );
define( 'NONCE_SALT', 'local-test-only' );
\$table_prefix = 'wp_';
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
PHP

# Local test credentials only; the site listens on 127.0.0.1.
wp core install --url="http://127.0.0.1:$WP_PORT" --title="PN Product Tabs dev" \
	--admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email --quiet
# WP-CLI derives a /wp sub-path from the folder name; pin both URLs to the server root.
wp option update siteurl "http://127.0.0.1:$WP_PORT" --quiet
wp option update home "http://127.0.0.1:$WP_PORT" --quiet

if [ "$WC_VERSION" = "latest-stable" ]; then
	fetch_zip https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip "$WP_DIR/wp-content/plugins"
else
	fetch_zip "https://downloads.wordpress.org/plugin/woocommerce.$WC_VERSION.zip" "$WP_DIR/wp-content/plugins"
fi
fetch_zip https://downloads.wordpress.org/theme/storefront.latest-stable.zip "$WP_DIR/wp-content/themes"
fetch_zip https://downloads.wordpress.org/theme/twentytwentyfive.latest-stable.zip "$WP_DIR/wp-content/themes"
if [ "${WITH_YIKES:-1}" = "1" ]; then
	fetch_zip https://downloads.wordpress.org/plugin/yikes-inc-easy-custom-woocommerce-product-tabs.latest-stable.zip "$WP_DIR/wp-content/plugins"
fi

# The plugin under test, symlinked so edits are live.
ln -sfn "$ROOT" "$WP_DIR/wp-content/plugins/$SLUG"

wp theme activate storefront --quiet
wp plugin activate woocommerce --quiet
wp option update woocommerce_coming_soon no --quiet || true
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json --quiet || true
wp rewrite structure '/%postname%/' --quiet
if [ "${WITH_YIKES:-1}" = "1" ]; then
	wp plugin activate yikes-inc-easy-custom-woocommerce-product-tabs --quiet
fi
if [ "${SKIP_PLUGIN:-0}" != "1" ]; then wp plugin activate "$SLUG" --quiet; fi

# Pristine copy for the integration tests (they run on a throw-away copy of it).
cp "$WP_DIR/wp-content/database/.ht.sqlite" "$WP_DIR/wp-content/database/clean.sqlite"

echo "WordPress $(wp core version) + WooCommerce $(wp plugin get woocommerce --field=version) ready in $WP_DIR"
