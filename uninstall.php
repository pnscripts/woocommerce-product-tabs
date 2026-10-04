<?php
/**
 * Uninstall: removes data only when the store opted in (Products → Tab settings → Uninstall).
 * YIKES Custom Product Tabs data is never touched.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove this plugin's data from the current site when opted in.
 */
function pnscripts_product_tabs_uninstall_site(): void {
	$settings = get_option( 'pnscripts_product_tabs_settings', array() );
	if ( ! is_array( $settings ) || empty( $settings['remove_data'] ) ) {
		return;
	}

	$post_ids = get_posts(
		array(
			'post_type'        => 'pnscripts_ptab',
			'post_status'      => 'any,trash,auto-draft',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}

	$keys = array(
		'_pnscripts_product_tabs',
		'_pnscripts_product_tabs_hidden_globals',
		'_pnscripts_product_tabs_hidden_defaults',
		'_pnscripts_product_tabs_yikes_hash',
		'_pnscripts_product_tabs_yikes_defaults',
	);
	foreach ( $keys as $key ) {
		// Bulk delete across all products; delete_post_meta_by_key() runs one query per key.
		delete_post_meta_by_key( $key );
	}
	delete_metadata( 'user', 0, 'pnscripts_product_tabs_dismiss_yikes', '', true );

	foreach ( array( 'pnscripts_product_tabs_settings', 'pnscripts_product_tabs_index', 'pnscripts_product_tabs_yikes_last_run', 'pnscripts_product_tabs_installed_at' ) as $option ) {
		delete_option( $option );
	}
}

if ( is_multisite() ) {
	$pnscripts_product_tabs_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $pnscripts_product_tabs_sites as $pnscripts_product_tabs_site ) {
		switch_to_blog( (int) $pnscripts_product_tabs_site );
		pnscripts_product_tabs_uninstall_site();
		restore_current_blog();
	}
} else {
	pnscripts_product_tabs_uninstall_site();
}
