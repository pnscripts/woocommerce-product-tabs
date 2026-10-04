<?php
/**
 * Tests for TabSanitizer.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Unit;

use Pnscripts\ProductTabs\Domain\TabSanitizer;

final class TabSanitizerTest extends TestCase {

	public function test_content_tab_is_normalised(): void {
		$tabs = TabSanitizer::product_tabs(
			array(
				array(
					'id'      => 'tabc1',
					'type'    => 'content',
					'title'   => '  Size <b>guide</b> ',
					'content' => '<p>Hi</p>',
					'enabled' => '1',
				),
			),
			true
		);
		$this->assertSame(
			array(
				'id'        => 'tabc1',
				'type'      => 'content',
				'title'     => 'Size guide',
				'content'   => '<p>Hi</p>',
				'faq'       => array(),
				'schema'    => false,
				'global_id' => 0,
				'enabled'   => true,
				'origin'    => '',
			),
			$tabs[0]
		);
	}

	public function test_empty_rows_and_non_arrays_are_dropped(): void {
		$tabs = TabSanitizer::product_tabs(
			array(
				'junk',
				array(
					'type'  => 'content',
					'title' => '',
				),
				array(
					'type'      => 'global',
					'global_id' => 0,
				),
				array( 'title' => 'Kept' ),
			),
			true
		);
		$this->assertCount( 1, $tabs );
		$this->assertSame( 'Kept', $tabs[0]['title'] );
		$this->assertSame( 'content', $tabs[0]['type'] );
		$this->assertMatchesRegularExpression( '/^t[0-9a-f]{10}$/', $tabs[0]['id'] );
	}

	public function test_non_array_input_gives_empty_list(): void {
		$this->assertSame( array(), TabSanitizer::product_tabs( 'a:1:{}', true ) );
		$this->assertSame( array(), TabSanitizer::product_tabs( null, true ) );
	}

	public function test_html_is_filtered_without_unfiltered_html(): void {
		$raw = array(
			array(
				'title'   => 'X',
				'content' => '<p>ok</p><script>alert(1)</script>',
			),
		);
		$this->assertSame( '<p>ok</p>', TabSanitizer::product_tabs( $raw, false )[0]['content'] );
		$this->assertSame( '<p>ok</p><script>alert(1)</script>', TabSanitizer::product_tabs( $raw, true )[0]['content'] );
	}

	public function test_unknown_type_falls_back_to_content(): void {
		$tab = TabSanitizer::product_tab(
			array(
				'type'  => 'evil',
				'title' => 'A',
			),
			true
		);
		$this->assertNotNull( $tab );
		$this->assertSame( 'content', $tab['type'] );
	}

	public function test_global_tab_keeps_only_the_link(): void {
		$tab = TabSanitizer::product_tab(
			array(
				'type'      => 'global',
				'global_id' => '42',
				'title'     => 'ignored',
				'content'   => 'ignored',
				'enabled'   => '0',
			),
			true
		);
		$this->assertNotNull( $tab );
		$this->assertSame( 42, $tab['global_id'] );
		$this->assertSame( '', $tab['title'] );
		$this->assertSame( '', $tab['content'] );
		$this->assertSame( 'g42', $tab['id'] );
		$this->assertFalse( $tab['enabled'] );
	}

	public function test_faq_tab(): void {
		$tab = TabSanitizer::product_tab(
			array(
				'type'   => 'faq',
				'title'  => 'FAQ',
				'schema' => '0',
				'faq'    => array(
					array(
						'q' => 'Q1?',
						'a' => 'A1',
					),
					array(
						'q' => '',
						'a' => 'no question',
					),
					'junk',
				),
			),
			false
		);
		$this->assertNotNull( $tab );
		$this->assertSame(
			array(
				array(
					'q' => 'Q1?',
					'a' => 'A1',
				),
			),
			$tab['faq']
		);
		$this->assertFalse( $tab['schema'] );
		$this->assertSame( '', $tab['content'] );
	}

	public function test_duplicate_ids_are_made_unique(): void {
		$tabs = TabSanitizer::product_tabs(
			array(
				array(
					'id'    => 'same',
					'title' => 'A',
				),
				array(
					'id'    => 'same',
					'title' => 'B',
				),
			),
			true
		);
		$this->assertNotSame( $tabs[0]['id'], $tabs[1]['id'] );
	}

	public function test_invalid_ids_and_origins_are_replaced(): void {
		$tab = TabSanitizer::product_tab(
			array(
				'id'     => '"><script>',
				'title'  => 'A',
				'origin' => 'yikes:3',
			),
			true
		);
		$this->assertNotNull( $tab );
		$this->assertMatchesRegularExpression( '/^[a-z0-9_-]+$/', $tab['id'] );
		$this->assertSame( 'yikes:3', $tab['origin'] );

		$bad = TabSanitizer::product_tab(
			array(
				'title'  => 'A',
				'origin' => '<b>',
			),
			true
		);
		$this->assertNotNull( $bad );
		$this->assertSame( '', $bad['origin'] );
	}

	public function test_tab_count_is_capped(): void {
		$raw = array();
		for ( $i = 0; $i < TabSanitizer::MAX_TABS + 10; $i++ ) {
			$raw[] = array( 'title' => 'T' . $i );
		}
		$this->assertCount( TabSanitizer::MAX_TABS, TabSanitizer::product_tabs( $raw, true ) );
	}

	public function test_scalars(): void {
		$this->assertTrue( TabSanitizer::bool( 'yes' ) );
		$this->assertTrue( TabSanitizer::bool( 1 ) );
		$this->assertFalse( TabSanitizer::bool( 'no' ) );
		$this->assertFalse( TabSanitizer::bool( array() ) );
		$this->assertSame( 999, TabSanitizer::priority( '5000', 25 ) );
		$this->assertSame( 0, TabSanitizer::priority( -3, 25 ) );
		$this->assertSame( 25, TabSanitizer::priority( 'abc', 25 ) );
		$this->assertSame( 'manual', TabSanitizer::scope( 'everything' ) );
		$this->assertSame( 'terms', TabSanitizer::scope( 'terms' ) );
		$this->assertSame( 'content', TabSanitizer::global_type( 'global' ) );
		$this->assertSame( array( 3, 5 ), TabSanitizer::id_list( array( '3', 'x', 5, 3, -1, 0 ) ) );
	}
}
