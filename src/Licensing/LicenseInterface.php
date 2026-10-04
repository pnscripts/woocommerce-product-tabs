<?php
/**
 * Licence abstraction for a future commercial add-on.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * A licence never gates features of this (free) plugin; an add-on can provide its own implementation
 * through the pnscripts_product_tabs_license filter to unlock the add-on's own features and updates.
 */
interface LicenseInterface {

	/**
	 * Plan identifier, "free" for this plugin.
	 */
	public function plan(): string;

	/**
	 * Whether a paid licence is active.
	 */
	public function is_active(): bool;

	/**
	 * Whether an add-on feature is available (always false in the free plugin).
	 *
	 * @param string $feature Feature id, e.g. "advanced_conditions", "bulk_edit", "csv", "templates", "schedule".
	 */
	public function has_feature( string $feature ): bool;
}
