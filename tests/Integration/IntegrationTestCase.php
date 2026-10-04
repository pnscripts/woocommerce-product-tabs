<?php
/**
 * Base class for tests on a real WordPress + WooCommerce site.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pnscripts\ProductTabs\Plugin;
use Pnscripts\ProductTabs\Settings;
use Pnscripts\ProductTabs\Storage\GlobalTabs;

/**
 * Helpers; each test starts without global tabs, YIKES data or custom settings.
 */
abstract class IntegrationTestCase extends TestCase {

	protected Plugin $plugin;

	protected function setUp(): void {
		parent::setUp();
		$plugin = Plugin::instance();
		$this->assertNotNull( $plugin );
		$this->plugin = $plugin;
		wp_set_current_user( 1 );
		foreach ( get_posts( array( 'post_type' => GlobalTabs::POST_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			wp_delete_post( (int) $id, true );
		}
		foreach ( get_posts( array( 'post_type' => GlobalTabs::POST_TYPE, 'post_status' => 'trash', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			wp_delete_post( (int) $id, true );
		}
		delete_post_meta_by_key( 'yikes_woo_products_tabs' );
		foreach ( array( '_pnscripts_product_tabs', '_pnscripts_product_tabs_hidden_globals', '_pnscripts_product_tabs_hidden_defaults', '_pnscripts_product_tabs_yikes_hash', '_pnscripts_product_tabs_yikes_defaults' ) as $key ) {
			delete_post_meta_by_key( $key );
		}
		delete_option( 'yikes_woo_reusable_products_tabs' );
		delete_option( 'yikes_woo_reusable_products_tabs_applied' );
		delete_option( Settings::OPTION );
		$this->reset();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['product'], $GLOBALS['post'] );
		wp_reset_postdata();
		parent::tearDown();
	}

	/**
	 * Forget request caches (settings, index, resolved tabs).
	 */
	protected function reset(): void {
		$this->plugin->settings->flush();
		$this->plugin->globals->flush();
		$this->plugin->renderer->reset();
	}

	/**
	 * Update settings.
	 *
	 * @param array<string, mixed> $values Values merged over the defaults.
	 */
	protected function settings( array $values ): void {
		update_option( Settings::OPTION, array_replace_recursive( Settings::defaults(), $values ) );
		$this->reset();
	}

	/**
	 * Create a published simple product.
	 *
	 * @param string    $name       Name.
	 * @param list<int> $categories Category ids.
	 * @param string    $content    Description.
	 */
	protected function product( string $name, array $categories = array(), string $content = 'Product description.' ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->set_description( $content );
		if ( array() !== $categories ) {
			$product->set_category_ids( $categories );
		}
		return (int) $product->save();
	}

	/**
	 * Create a product category.
	 *
	 * @param string $name   Name.
	 * @param int    $parent Parent id.
	 */
	protected function category( string $name, int $parent = 0 ): int {
		$term = wp_insert_term( $name . ' ' . wp_generate_password( 6, false ), 'product_cat', array( 'parent' => $parent ) );
		$this->assertIsArray( $term );
		clean_term_cache( (int) $term['term_id'], 'product_cat' );
		delete_option( 'product_cat_children' );
		return (int) $term['term_id'];
	}

	/**
	 * Create a global tab.
	 *
	 * @param string               $title    Title.
	 * @param string               $content  Content.
	 * @param array<string, mixed> $settings Settings (scope, categories, priority, type, faq…).
	 */
	protected function global_tab( string $title, string $content, array $settings = array() ): int {
		$id = wp_insert_post(
			array(
				'post_type'    => GlobalTabs::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
		$this->assertIsInt( $id );
		$this->plugin->globals->save_settings( $id, $settings + array( 'scope' => 'all' ), true );
		$this->reset();
		return $id;
	}

	/**
	 * Tabs WooCommerce would show on a product page: key => [title, priority, html], sorted by priority.
	 *
	 * @param int $product_id Product id.
	 * @return array<string, array{title: string, priority: int, html: string}>
	 */
	protected function storefront_tabs( int $product_id ): array {
		$this->reset();
		$GLOBALS['post'] = get_post( $product_id );
		setup_postdata( $GLOBALS['post'] );
		$GLOBALS['product'] = wc_get_product( $product_id );
		$tabs               = apply_filters( 'woocommerce_product_tabs', array() );
		$out                = array();
		foreach ( $tabs as $key => $tab ) {
			ob_start();
			if ( isset( $tab['callback'] ) && is_callable( $tab['callback'] ) ) {
				call_user_func( $tab['callback'], $key, $tab );
			}
			$out[ (string) $key ] = array(
				'title'    => (string) $tab['title'],
				'priority' => (int) $tab['priority'],
				'html'     => (string) ob_get_clean(),
			);
		}
		return $out;
	}

	/**
	 * Normalised visible text.
	 *
	 * @param string $html HTML.
	 */
	protected static function text( string $html ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( str_replace( '<', ' <', $html ) ), ENT_QUOTES, 'UTF-8' ) ) );
	}
}
