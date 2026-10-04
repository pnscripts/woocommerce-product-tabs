<?php
/**
 * Activation and WooCommerce feature declarations.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Static lifecycle callbacks.
 */
final class Lifecycle {

	/**
	 * Declare compatibility with High-Performance Order Storage and the cart/checkout blocks
	 * (the plugin never reads or writes orders and adds nothing to cart or checkout).
	 */
	public static function declare_compatibility(): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', PNSCRIPTS_PRODUCT_TABS_FILE, true );
			FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', PNSCRIPTS_PRODUCT_TABS_FILE, true );
		}
	}

	/**
	 * Activation: store default settings once (never overwrites existing ones).
	 */
	public static function activate(): void {
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', true );
		}
		add_option( 'pnscripts_product_tabs_installed_at', time(), '', false );
	}
}
