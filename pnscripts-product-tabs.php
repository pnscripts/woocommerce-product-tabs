<?php
/**
 * Plugin Name:          PN Scripts Product Tabs – Custom Tabs & FAQ
 * Plugin URI:           https://pnscripts.com/marketplace/pn-product-tabs
 * Description:          Custom product tabs per product, reusable global tabs by category or tag, FAQ tabs with optional FAQPage schema, rename, reorder or hide the default tabs, and a one-click importer from YIKES Custom Product Tabs. Works in classic and block themes.
 * Version:              1.0.2
 * Requires at least:    6.5
 * Requires PHP:         8.1
 * Requires Plugins:     woocommerce
 * WC requires at least: 9.0
 * WC tested up to:      11.1
 * Author:               PN Scripts
 * Author URI:           https://pnscripts.com
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          pnscripts-product-tabs
 * Domain Path:          /languages
 *
 * @package Pnscripts\ProductTabs
 */

/*
 * Copyright (C) 2026 ПН СКРИПТС ЕООД (PN Scripts)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation; either version 2 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'PNSCRIPTS_PRODUCT_TABS_VERSION', '1.0.2' );
define( 'PNSCRIPTS_PRODUCT_TABS_FILE', __FILE__ );
define( 'PNSCRIPTS_PRODUCT_TABS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PNSCRIPTS_PRODUCT_TABS_URL', plugin_dir_url( __FILE__ ) );
define( 'PNSCRIPTS_PRODUCT_TABS_MIN_WC', '9.0' );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Pnscripts\\ProductTabs\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = PNSCRIPTS_PRODUCT_TABS_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( \Pnscripts\ProductTabs\Lifecycle::class, 'activate' ) );

add_action( 'before_woocommerce_init', array( \Pnscripts\ProductTabs\Lifecycle::class, 'declare_compatibility' ) );
add_action( 'plugins_loaded', array( \Pnscripts\ProductTabs\Plugin::class, 'boot' ), 20 );
