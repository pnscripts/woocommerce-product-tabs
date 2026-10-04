<?php
/**
 * Storefront rendering on a real WooCommerce site.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Integration;

use Pnscripts\ProductTabs\Storage\GlobalTabs;

final class StorefrontTest extends IntegrationTestCase {

	/**
	 * Store tabs on a product.
	 *
	 * @param int                        $product_id Product id.
	 * @param list<array<string, mixed>> $tabs       Tabs.
	 * @param list<int>                  $hidden_globals Hidden global tabs.
	 * @param list<string>               $hidden_defaults Hidden default tabs.
	 */
	private function tabs( int $product_id, array $tabs, array $hidden_globals = array(), array $hidden_defaults = array() ): void {
		$this->plugin->product_tabs->save(
			$product_id,
			\Pnscripts\ProductTabs\Domain\TabSanitizer::product_tabs( $tabs, true ),
			$hidden_globals,
			$hidden_defaults
		);
	}

	public function test_product_tabs_order_and_reviews_last(): void {
		$id = $this->product( 'Shoe' );
		$this->tabs(
			$id,
			array(
				array(
					'title'   => 'Materials',
					'content' => 'Mesh',
				),
				array(
					'title'   => 'Care',
					'content' => 'Wash',
				),
				array(
					'title'   => 'Size',
					'content' => 'S',
				),
				array(
					'title'   => 'Hidden',
					'content' => 'H',
					'enabled' => false,
				),
				array(
					'title'   => 'Warranty',
					'content' => 'W',
				),
				array(
					'title'   => 'Recycling',
					'content' => 'R',
				),
				array(
					'title'   => 'Videos',
					'content' => 'V',
				),
			)
		);
		$tabs = $this->storefront_tabs( $id );
		$this->assertSame( array( 'description', 'materials', 'care', 'size', 'warranty', 'recycling', 'videos', 'reviews' ), array_keys( $tabs ) );
		$this->assertStringContainsString( '<h2 class="pnscripts-product-tabs__heading">Materials</h2>', $tabs['materials']['html'] );
		$this->assertStringContainsString( '<p>Mesh</p>', $tabs['materials']['html'] );
	}

	public function test_default_tabs_settings_and_per_product_hiding(): void {
		$this->settings(
			array(
				'defaults'     => array(
					'description' => array(
						'title'    => 'Overview',
						'priority' => 60,
					),
					'reviews'     => array( 'title' => 'Opinions (%d)' ),
				),
				'reviews_last' => false,
				'show_heading' => false,
			)
		);
		$id = $this->product( 'Lamp' );
		$this->tabs(
			$id,
			array(
				array(
					'title'   => 'Specs',
					'content' => 'x',
				),
			)
		);
		$tabs = $this->storefront_tabs( $id );
		$this->assertSame( array( 'specs', 'reviews', 'description' ), array_keys( $tabs ) );
		$this->assertSame( 'Overview', $tabs['description']['title'] );
		$this->assertSame( 'Opinions (0)', $tabs['reviews']['title'] );
		$this->assertStringNotContainsString( '<h2', $tabs['specs']['html'] );

		$this->tabs( $id, array(), array(), array( 'reviews' ) );
		$this->assertArrayNotHasKey( 'reviews', $this->storefront_tabs( $id ) );
	}

	public function test_global_tabs_by_category_with_subcategories_tags_and_hiding(): void {
		$parent = $this->category( 'Outdoor' );
		$child  = $this->category( 'Tents', $parent );
		$tag    = wp_insert_term( 'sale-' . wp_generate_password( 5, false ), 'product_tag' );
		$this->assertIsArray( $tag );
		$all     = $this->global_tab( 'Shipping', '<!-- wp:paragraph --><p>Ships in 24h</p><!-- /wp:paragraph -->', array( 'priority' => 22 ) );
		$outdoor = $this->global_tab(
			'Outdoor care',
			'Dry before storing.',
			array(
				'scope'      => 'terms',
				'categories' => array( $parent ),
			)
		);
		$sale    = $this->global_tab(
			'Sale terms',
			'Final sale.',
			array(
				'scope' => 'terms',
				'tags'  => array( (int) $tag['term_id'] ),
			)
		);
		$manual  = $this->global_tab( 'Manual only', 'M', array( 'scope' => 'manual' ) );

		$tent  = $this->product( 'Tent', array( $child ) );
		$plain = $this->product( 'Plain' );
		wp_set_object_terms( $plain, array( (int) $tag['term_id'] ), 'product_tag' );

		$tent_tabs = $this->storefront_tabs( $tent );
		$this->assertArrayHasKey( 'shipping', $tent_tabs );
		$this->assertArrayHasKey( 'outdoor-care', $tent_tabs );
		$this->assertArrayNotHasKey( 'sale-terms', $tent_tabs );
		$this->assertArrayNotHasKey( 'manual-only', $tent_tabs );
		$this->assertSame( 22, $tent_tabs['shipping']['priority'] );
		$this->assertMatchesRegularExpression( '#<p[^>]*>Ships in 24h</p>#', $tent_tabs['shipping']['html'] );
		$this->assertStringNotContainsString( 'wp:paragraph', $tent_tabs['shipping']['html'] );

		$plain_tabs = $this->storefront_tabs( $plain );
		$this->assertArrayHasKey( 'sale-terms', $plain_tabs );
		$this->assertArrayNotHasKey( 'outdoor-care', $plain_tabs );

		// Hide the automatic one on the tent, link the manual one.
		$this->tabs(
			$tent,
			array(
				array(
					'type'      => 'global',
					'global_id' => $manual,
				),
			),
			array( $outdoor )
		);
		$tent_tabs = $this->storefront_tabs( $tent );
		$this->assertArrayNotHasKey( 'outdoor-care', $tent_tabs );
		$this->assertArrayHasKey( 'manual-only', $tent_tabs );

		// Trashing a global tab removes it everywhere (index flushed).
		wp_trash_post( $all );
		$this->assertArrayNotHasKey( 'shipping', $this->storefront_tabs( $plain ) );
		unset( $sale );
	}

	public function test_no_queries_per_tab_after_the_index_is_built(): void {
		global $wpdb;
		for ( $i = 0; $i < 5; $i++ ) {
			$this->global_tab( 'Global ' . $i, 'Body ' . $i );
		}
		$id = $this->product( 'Query counter' );
		$this->tabs(
			$id,
			array(
				array(
					'title'   => 'One',
					'content' => '1',
				),
			)
		);
		$this->storefront_tabs( $id ); // Builds the index once.
		$this->plugin->renderer->reset();
		$this->plugin->settings->flush();
		$GLOBALS['post']    = get_post( $id );
		$GLOBALS['product'] = wc_get_product( $id );
		get_post_meta( $id ); // Primed by the single product query on a real page.
		$this->plugin->globals->flush_request_cache();
		$before = $wpdb->num_queries;
		$tabs   = $this->plugin->renderer->filter_tabs( array() );
		foreach ( $tabs as $key => $tab ) {
			if ( is_array( $tab['callback'] ) ) {
				ob_start();
				call_user_func( $tab['callback'], $key, $tab );
				ob_get_clean();
			}
		}
		$this->assertCount( 6, $tabs );
		$this->assertLessThanOrEqual( 1, $wpdb->num_queries - $before, 'At most the (non-autoloaded) index option is read.' );
	}

	public function test_faq_tab_and_schema(): void {
		$cat = $this->category( 'Socks' );
		$this->global_tab(
			'Sock FAQ',
			'',
			array(
				'type'       => 'faq',
				'scope'      => 'terms',
				'categories' => array( $cat ),
				'schema'     => 1,
				'faq'        => array(
					array(
						'q' => 'Itchy?',
						'a' => 'No, <strong>merino</strong>.',
					),
					array(
						'q' => 'Closing </script> tag?',
						'a' => 'Escaped.',
					),
				),
			)
		);
		$id = $this->product( 'Sock', array( $cat ) );
		$this->tabs(
			$id,
			array(
				array(
					'type'   => 'faq',
					'title'  => 'Product FAQ',
					'schema' => 0,
					'faq'    => array(
						array(
							'q' => 'Private?',
							'a' => 'Not in schema.',
						),
					),
				),
			)
		);
		$GLOBALS['wp_query'] = new \WP_Query(
			array(
				'p'         => $id,
				'post_type' => 'product',
			)
		);
		$GLOBALS['wp_query']->is_singular = true;
		$GLOBALS['wp_query']->is_single   = true;
		$GLOBALS['wp_query']->queried_object    = get_post( $id );
		$GLOBALS['wp_query']->queried_object_id = $id;

		$tabs = $this->storefront_tabs( $id );
		$this->assertStringContainsString( '<details class="pnscripts-product-tabs-faq__item"><summary class="pnscripts-product-tabs-faq__question">Itchy?</summary>', $tabs['sock-faq']['html'] );
		$this->assertStringContainsString( '<strong>merino</strong>', $tabs['sock-faq']['html'] );

		ob_start();
		$this->plugin->renderer->print_schema();
		$schema = (string) ob_get_clean();
		$this->assertStringContainsString( '"@type":"FAQPage"', $schema );
		$this->assertStringContainsString( 'Itchy?', $schema );
		$this->assertStringNotContainsString( 'Private?', $schema );
		$this->assertStringNotContainsString( '</script> tag', $schema );
		$this->assertSame( 1, substr_count( $schema, '</script>' ) );

		$this->settings( array( 'faq_schema' => false ) );
		$this->storefront_tabs( $id );
		ob_start();
		$this->plugin->renderer->print_schema();
		$this->assertSame( '', ob_get_clean() );
		$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
	}

	public function test_content_is_rendered_like_post_content(): void {
		$id = $this->product( 'Content' );
		$this->tabs(
			$id,
			array(
				array(
					'title'   => 'Rich',
					'content' => "Line one\n\nLine \"two\" [pnscripts_pt_test]",
				),
			)
		);
		add_shortcode( 'pnscripts_pt_test', static fn (): string => '<em>shortcode ran</em>' );
		$html = $this->storefront_tabs( $id )['rich']['html'];
		$this->assertStringContainsString( '<p>Line one</p>', $html );
		$this->assertStringContainsString( '<em>shortcode ran</em>', $html );
		$this->assertStringContainsString( '&#8220;two&#8221;', $html );
		remove_shortcode( 'pnscripts_pt_test' );
	}

	public function test_hpos_and_blocks_compatibility_is_declared(): void {
		$util = \Automattic\WooCommerce\Utilities\FeaturesUtil::class;
		$file = plugin_basename( PNSCRIPTS_PRODUCT_TABS_FILE );
		$hpos = wc_get_container()->get( \Automattic\WooCommerce\Internal\Features\FeaturesController::class )->get_compatible_plugins_for_feature( 'custom_order_tables' );
		$this->assertContains( $file, $hpos['compatible'] );
		$blocks = wc_get_container()->get( \Automattic\WooCommerce\Internal\Features\FeaturesController::class )->get_compatible_plugins_for_feature( 'cart_checkout_blocks' );
		$this->assertContains( $file, $blocks['compatible'] );
		unset( $util );
	}

	public function test_global_tab_post_type_is_private_and_uses_product_caps(): void {
		$type = get_post_type_object( GlobalTabs::POST_TYPE );
		$this->assertNotNull( $type );
		$this->assertFalse( $type->public );
		$this->assertTrue( $type->show_in_rest );
		$this->assertSame( 'edit_products', $type->cap->edit_posts );
	}
}
