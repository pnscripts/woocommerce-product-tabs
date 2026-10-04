<?php
/**
 * Tabs stored on a product.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Storage;

use Pnscripts\ProductTabs\Domain\DefaultTabs;
use Pnscripts\ProductTabs\Domain\TabSanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Three private meta keys per product (only written when the product has something to store):
 * the tab list, global tabs hidden on this product, and default tabs hidden on this product.
 * Products are posts (HPOS concerns orders only), and the single-product query primes this meta.
 *
 * @phpstan-import-type ProductTab from TabSanitizer
 */
final class ProductTabs {

	public const META_TABS            = '_pnscripts_product_tabs';
	public const META_HIDDEN_GLOBALS  = '_pnscripts_product_tabs_hidden_globals';
	public const META_HIDDEN_DEFAULTS = '_pnscripts_product_tabs_hidden_defaults';
	public const META_YIKES_HASH      = '_pnscripts_product_tabs_yikes_hash';

	/**
	 * Register the meta keys (private, not in REST).
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register meta with auth callbacks so the keys stay protected.
	 */
	public function register_meta(): void {
		foreach ( array( self::META_TABS, self::META_HIDDEN_GLOBALS, self::META_HIDDEN_DEFAULTS, self::META_YIKES_HASH ) as $key ) {
			register_post_meta(
				'product',
				$key,
				array(
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static fn ( $allowed, $meta_key, $post_id ): bool => current_user_can( 'edit_post', (int) $post_id ),
				)
			);
		}
	}

	/**
	 * Tabs of a product, in display order.
	 *
	 * @param int $product_id Product id.
	 * @return list<ProductTab>
	 */
	public function tabs( int $product_id ): array {
		return TabSanitizer::product_tabs( get_post_meta( $product_id, self::META_TABS, true ), true );
	}

	/**
	 * Global tab ids hidden on a product.
	 *
	 * @param int $product_id Product id.
	 * @return list<int>
	 */
	public function hidden_globals( int $product_id ): array {
		return TabSanitizer::id_list( get_post_meta( $product_id, self::META_HIDDEN_GLOBALS, true ) );
	}

	/**
	 * Default WooCommerce tab keys hidden on a product.
	 *
	 * @param int $product_id Product id.
	 * @return list<string>
	 */
	public function hidden_defaults( int $product_id ): array {
		return self::default_keys( get_post_meta( $product_id, self::META_HIDDEN_DEFAULTS, true ) );
	}

	/**
	 * Store the tabs of a product (already sanitised); empty data removes the meta row.
	 *
	 * @param int              $product_id      Product id.
	 * @param list<ProductTab> $tabs            Tabs.
	 * @param list<int>        $hidden_globals  Hidden global tabs.
	 * @param list<string>     $hidden_defaults Hidden default tabs.
	 */
	public function save( int $product_id, array $tabs, array $hidden_globals, array $hidden_defaults ): void {
		self::put( $product_id, self::META_TABS, $tabs );
		self::put( $product_id, self::META_HIDDEN_GLOBALS, $hidden_globals );
		self::put( $product_id, self::META_HIDDEN_DEFAULTS, self::default_keys( $hidden_defaults ) );
	}

	/**
	 * Write or delete one meta value. Slashes are added because update_post_meta() unslashes.
	 *
	 * @param int          $product_id Product id.
	 * @param string       $key        Meta key.
	 * @param array<mixed> $value      Value.
	 */
	private static function put( int $product_id, string $key, array $value ): void {
		if ( array() === $value ) {
			delete_post_meta( $product_id, $key );
			return;
		}
		update_post_meta( $product_id, $key, wp_slash( $value ) );
	}

	/**
	 * Valid default tab keys from untrusted input.
	 *
	 * @param mixed $raw Value.
	 * @return list<string>
	 */
	public static function default_keys( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_intersect( DefaultTabs::KEYS, array_map( static fn ( $v ): string => is_scalar( $v ) ? (string) $v : '', $raw ) ) );
	}
}
