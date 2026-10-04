#!/usr/bin/env bash
# Serve the disposable site with PHP's built-in server (127.0.0.1 only).
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP_DIR="${WP_DIR:-$ROOT/.cache/wp}"
cat > "$ROOT/.cache/router.php" <<'PHP'
<?php
// Static files are served directly; everything else goes through WordPress.
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = $_SERVER['DOCUMENT_ROOT'] . $path;
if ( $path !== '/' && is_file( $file ) && ! str_ends_with( $file, '.php' ) ) {
	return false;
}
if ( is_dir( $file ) && is_file( rtrim( $file, '/' ) . '/index.php' ) ) {
	$file = rtrim( $file, '/' ) . '/index.php';
}
if ( str_ends_with( $file, '.php' ) && is_file( $file ) ) {
	$_SERVER['SCRIPT_FILENAME'] = $file;
	$_SERVER['SCRIPT_NAME']     = substr( $file, strlen( $_SERVER['DOCUMENT_ROOT'] ) );
	chdir( dirname( $file ) );
	require $file;
	return true;
}
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . '/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
PHP
exec php -d memory_limit=512M -S "127.0.0.1:${WP_PORT:-8795}" -t "$WP_DIR" "$ROOT/.cache/router.php"
