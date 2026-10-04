<?php
/**
 * Accordion Product Details block (block themes, WooCommerce 10+).
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Integration;

use Pnscripts\ProductTabs\Domain\TabSanitizer;
use Pnscripts\ProductTabs\Frontend\BlockCompat;

final class BlockThemeTest extends IntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'core/accordion' ) || version_compare( (string) WC_VERSION, '10.0', '<' ) ) {
			$this->markTestSkipped( 'The accordion Product Details block needs WordPress 6.9+ (core/accordion) and WooCommerce 10+; older versions use the tabbed block, covered by StorefrontTest.' );
		}
	}

	/**
	 * Render the accordion fixture for a product and return the item titles in order.
	 *
	 * @param int $product_id Product id.
	 * @return array{titles: list<string>, html: string}
	 */
	private function render( int $product_id ): array {
		$this->reset();
		$GLOBALS['post'] = get_post( $product_id );
		setup_postdata( $GLOBALS['post'] );
		$GLOBALS['product'] = wc_get_product( $product_id );
		$html               = do_blocks( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/product-details-accordion.html' ) );
		preg_match_all( '#wp-block-accordion-heading__toggle-title">([^<]*)<#', $html, $m );
		return array(
			'titles' => array_map( static fn ( string $t ): string => html_entity_decode( $t, ENT_QUOTES, 'UTF-8' ), $m[1] ),
			'html'   => $html,
		);
	}

	public function test_custom_tabs_are_placed_by_priority_and_defaults_follow_settings(): void {
		$this->settings(
			array(
				'defaults' => array(
					'description' => array( 'title' => 'Overview' ),
					'reviews'     => array( 'title' => 'Opinions (%d)' ),
				),
			)
		);
		$this->global_tab( 'First of all', 'Early', array( 'priority' => 5 ) );
		$id = $this->product( 'Accordion product' );
		$this->plugin->product_tabs->save(
			$id,
			TabSanitizer::product_tabs(
				array(
					array(
						'title'   => 'Materials & care',
						'content' => 'Wool',
					),
				),
				true
			),
			array(),
			array()
		);
		update_post_meta( $id, '_weight', '2' );
		$result = $this->render( $id );
		// The Reviews item renders empty outside a real request, and WooCommerce drops empty items; the
		// rename of Reviews is covered in test_helpers() and verified in the browser.
		$this->assertSame( array( 'First of all', 'Overview', 'Additional Information', 'Materials & care' ), $result['titles'] );
		$this->assertStringContainsString( 'Wool', $result['html'] );
		$this->assertStringNotContainsString( 'pnscripts-product-tabs__heading', $result['html'], 'No duplicate heading inside an accordion item.' );
		$this->assertSame( 1, substr_count( $result['html'], 'Materials &amp; care' ) + substr_count( $result['html'], 'Materials & care' ) );
		$this->assertFalse( $this->plugin->renderer->in_accordion_mode(), 'Accordion mode ends with the block.' );
	}

	public function test_hidden_defaults_are_removed(): void {
		$this->settings( array( 'defaults' => array( 'additional_information' => array( 'enabled' => false ) ) ) );
		$id = $this->product( 'Hidden defaults' );
		update_post_meta( $id, '_weight', '2' );
		$this->plugin->product_tabs->save( $id, array(), array(), array( 'reviews' ) );
		$this->assertSame( array( 'Description' ), $this->render( $id )['titles'] );
	}

	public function test_helpers(): void {
		$blocks = parse_blocks( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/product-details-accordion.html' ) );
		$items  = $blocks[0]['innerBlocks'][0]['innerBlocks'];
		$this->assertSame( array( 'description', 'additional_information', 'reviews' ), array_map( array( BlockCompat::class, 'default_key' ), $items ) );
		$renamed = BlockCompat::rename( $items[2], 'Say <what>' );
		$this->assertStringContainsString( 'toggle-title">Say &lt;what&gt;</span>', $renamed['innerBlocks'][0]['innerHTML'] );
		$this->assertSame( 'Reviews', BlockCompat::heading_text( $items[2]['innerBlocks'][0]['innerHTML'] ) );
		$woo = BlockCompat::item_block( array( 'blockName' => 'woocommerce/accordion-group' ), 'T', '<p>x</p>' );
		$this->assertSame( 'woocommerce/accordion-item', $woo['blockName'] );
	}
}
