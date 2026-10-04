<?php
/**
 * REST controller of global tabs: the block editor needs the REST API, the public does not.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Same as the core posts controller, but reading requires the capability to edit products, so tabs that
 * are not shown anywhere yet (manual placement) are not listed to visitors.
 */
final class GlobalTabsRestController extends \WP_REST_Posts_Controller {

	/**
	 * Listing permission.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->editors_only() ?? parent::get_items_permissions_check( $request );
	}

	/**
	 * Single item permission.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->editors_only() ?? parent::get_item_permissions_check( $request );
	}

	/**
	 * Error for users who cannot edit products, null otherwise.
	 */
	private function editors_only(): ?\WP_Error {
		if ( current_user_can( 'edit_products' ) ) {
			return null;
		}
		return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view global product tabs.', 'pnscripts-product-tabs' ), array( 'status' => rest_authorization_required_code() ) );
	}
}
