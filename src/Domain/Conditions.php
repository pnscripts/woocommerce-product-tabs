<?php
/**
 * Simple assignment rules of global tabs.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * "All products", "products in these categories or with these tags", or "only where added manually".
 * Advanced conditions (attributes, stock, roles, schedules) plug in through the
 * pnscripts_product_tabs_global_tab_applies filter.
 *
 * @phpstan-import-type GlobalTab from TabSanitizer
 * @phpstan-import-type Context from TabResolver
 */
final class Conditions {

	/**
	 * Whether a global tab applies to a product automatically.
	 *
	 * @param GlobalTab $global_tab Global tab.
	 * @param Context   $context Product context (categories include ancestors).
	 */
	public static function matches( array $global_tab, array $context ): bool {
		switch ( $global_tab['scope'] ) {
			case TabSanitizer::SCOPE_ALL:
				return true;
			case TabSanitizer::SCOPE_TERMS:
				return array() !== array_intersect( $global_tab['categories'], $context['categories'] )
					|| array() !== array_intersect( $global_tab['tags'], $context['tags'] );
			default:
				return false;
		}
	}
}
