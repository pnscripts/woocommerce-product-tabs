<?php
/**
 * Integration bootstrap: loads a real WordPress + WooCommerce (+ YIKES Custom Product Tabs) site built by
 * bin/setup-wp.sh, on a throw-away copy of its pristine SQLite database.
 *
 *   WP_DIR=.cache/wp-test bin/setup-wp.sh && composer test:integration
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

$root   = dirname( __DIR__, 2 );
$wp_dir = getenv( 'WP_DIR' ) ?: $root . '/.cache/wp-test';
if ( ! is_file( $wp_dir . '/wp-load.php' ) ) {
	fwrite( STDERR, "No WordPress in $wp_dir. Run: WP_DIR=$wp_dir bin/setup-wp.sh\n" );
	exit( 1 );
}
$db_dir = $wp_dir . '/wp-content/database/';
if ( ! is_file( $db_dir . 'clean.sqlite' ) ) {
	fwrite( STDERR, "Missing pristine database {$db_dir}clean.sqlite (re-run bin/setup-wp.sh).\n" );
	exit( 1 );
}
copy( $db_dir . 'clean.sqlite', $db_dir . '.ht.test.sqlite' );
putenv( 'PNSCRIPTS_PT_DB_FILE=.ht.test.sqlite' );
register_shutdown_function(
	static function () use ( $db_dir ): void {
		foreach ( glob( $db_dir . '.ht.test.sqlite*' ) ?: array() as $file ) {
			unlink( $file );
		}
	}
);

$_SERVER['HTTP_HOST']   = '127.0.0.1';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', false );

require $root . '/vendor/autoload.php';
require $wp_dir . '/wp-load.php';

if ( ! class_exists( \Pnscripts\ProductTabs\Plugin::class ) || null === \Pnscripts\ProductTabs\Plugin::instance() ) {
	fwrite( STDERR, "PN Scripts Product Tabs is not active on the test site.\n" );
	exit( 1 );
}
