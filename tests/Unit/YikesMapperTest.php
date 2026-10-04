<?php
/**
 * Tests for YikesMapper against data written by YIKES 1.8.6 itself (tests/fixtures/yikes-1.8.6.json).
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Unit;

use Pnscripts\ProductTabs\Import\YikesMapper;

final class YikesMapperTest extends TestCase {

	/**
	 * Fixture.
	 *
	 * @var array<string, mixed>
	 */
	private array $fixture;

	protected function setUp(): void {
		parent::setUp();
		$json          = (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/yikes-1.8.6.json' );
		$this->fixture = json_decode( $json, true );
	}

	private function mapper(): YikesMapper {
		return new YikesMapper( self::slugify() );
	}

	/**
	 * Saved tabs of the fixture.
	 *
	 * @return array<int, array{yikes_id: int, title: string, content: string, scope: string, categories: list<int>, tags: list<int>}>
	 */
	private function saved(): array {
		$warnings = array();
		return $this->mapper()->saved_tabs( $this->fixture['saved'], $warnings );
	}

	public function test_saved_tabs_from_free_yikes_are_manual(): void {
		$saved = $this->saved();
		$this->assertSame( array( 1, 2 ), array_keys( $saved ) );
		$this->assertSame( 'Shipping & Returns', $saved[1]['title'] );
		$this->assertSame( 'manual', $saved[1]['scope'] );
		$this->assertStringContainsString( '€50', $saved[1]['content'] );
	}

	public function test_pro_rules_become_scopes(): void {
		$warnings = array();
		$saved    = $this->mapper()->saved_tabs(
			array(
				3 => array(
					'tab_title'   => 'Everywhere',
					'tab_content' => 'x',
					'tab_id'      => 3,
					'global_tab'  => true,
					'taxonomies'  => array(),
				),
				4 => array(
					'tab_title'   => 'Shoes only',
					'tab_content' => 'y',
					'tab_id'      => 4,
					'global_tab'  => false,
					'taxonomies'  => array(
						'product_cat' => array( 16 => 'shoes' ),
						'product_tag' => array( 21 => 'sale' ),
						'pa_color'    => array( 30 => 'red' ),
					),
				),
				5 => array(
					'tab_title'   => '',
					'tab_content' => 'no title',
					'tab_id'      => 5,
				),
				'junk',
			),
			$warnings
		);
		$this->assertSame( 'all', $saved[3]['scope'] );
		$this->assertSame( 'terms', $saved[4]['scope'] );
		$this->assertSame( array( 16 ), $saved[4]['categories'] );
		$this->assertSame( array( 21 ), $saved[4]['tags'] );
		$this->assertArrayNotHasKey( 5, $saved );
		$this->assertCount( 2, $warnings );
	}

	public function test_product_with_link_duplicates_and_divergent_copy(): void {
		$plan = $this->mapper()->product(
			10,
			$this->fixture['products']['10'],
			$this->fixture['applied']['10'],
			$this->saved(),
			array(
				1 => 501,
				2 => 502,
			),
			true
		);

		$this->assertSame( 1, $plan['linked'] );
		$this->assertSame( 4, $plan['custom'] );
		$this->assertSame( 1, $plan['disabled_duplicate'] );
		$this->assertSame( array(), $plan['hidden_defaults'] );

		$this->assertSame( 'global', $plan['tabs'][0]['type'] );
		$this->assertSame( 501, $plan['tabs'][0]['global_id'] );
		$this->assertSame( 'yikes:1', $plan['tabs'][0]['origin'] );

		// The first "Care" was replaced by the second one in YIKES: imported switched off.
		$this->assertSame( array( 'Care', false ), array( $plan['tabs'][2]['title'], $plan['tabs'][2]['enabled'] ) );
		$this->assertSame( array( 'Care', true ), array( $plan['tabs'][3]['title'], $plan['tabs'][3]['enabled'] ) );

		// "Size guide" was not linked by YIKES (and its copy differs from the saved tab): stays a product tab.
		$this->assertSame( 'content', $plan['tabs'][4]['type'] );
		$this->assertSame( 'Size guide', $plan['tabs'][4]['title'] );
	}

	public function test_default_tab_collision_empty_title_and_content_fidelity(): void {
		$plan = $this->mapper()->product(
			13,
			$this->fixture['products']['13'],
			$this->fixture['applied']['13'] ?? array(),
			$this->saved(),
			array( 1 => 501 ),
			true
		);
		$this->assertSame( array( 'description' ), $plan['hidden_defaults'] );
		$this->assertSame( 1, $plan['replaced_defaults'] );
		$this->assertSame( 1, $plan['disabled_empty'] );
		$this->assertSame( 'Description', $plan['tabs'][0]['title'] );
		$this->assertTrue( $plan['tabs'][0]['enabled'] );
		$this->assertFalse( $plan['tabs'][1]['enabled'] );
		$this->assertStringContainsString( '<iframe', $plan['tabs'][3]['content'] );
	}

	public function test_content_is_filtered_for_users_without_unfiltered_html(): void {
		$plan = $this->mapper()->product( 13, $this->fixture['products']['13'], array(), $this->saved(), array(), false );
		$this->assertStringNotContainsString( '<iframe', $plan['tabs'][3]['content'] );
	}

	public function test_dry_run_placeholder_and_failed_creation(): void {
		$saved = $this->saved();
		$link  = $this->fixture['applied']['10'];
		$dry   = $this->mapper()->product( 10, $this->fixture['products']['10'], $link, $saved, array( 1 => PHP_INT_MAX ), true );
		$this->assertSame( 1, $dry['linked'] );
		$failed = $this->mapper()->product( 10, $this->fixture['products']['10'], $link, $saved, array( 1 => 0 ), true );
		$this->assertSame( 0, $failed['linked'] );
		$this->assertSame( 'Shipping & Returns', $failed['tabs'][0]['title'] );
	}

	public function test_ids_are_stable_between_runs(): void {
		$a = $this->mapper()->product( 12, $this->fixture['products']['12'], array(), $this->saved(), array(), true );
		$b = $this->mapper()->product( 12, $this->fixture['products']['12'], array(), $this->saved(), array(), true );
		$this->assertSame( array_column( $a['tabs'], 'id' ), array_column( $b['tabs'], 'id' ) );
		$this->assertSame( 'Технически данни', $a['tabs'][1]['title'] );
	}

	public function test_serialised_strings_and_objects(): void {
		$tabs = array(
			array(
				'title'   => 'A',
				'id'      => 'a',
				'content' => 'c',
			),
		);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->assertSame( $tabs, YikesMapper::maybe_unserialize( serialize( $tabs ) ) );
		$object = YikesMapper::maybe_unserialize( 'a:1:{i:0;O:8:"stdClass":0:{}}' );
		$this->assertIsArray( $object );
		$this->assertInstanceOf( \__PHP_Incomplete_Class::class, $object[0] );
		$this->assertNull( YikesMapper::maybe_unserialize( 'a:1:{broken' ) );
		$this->assertSame( 'plain', YikesMapper::maybe_unserialize( 'plain' ) );
		$plan = $this->mapper()->product( 1, 'not tabs', array(), array(), array(), true );
		$this->assertSame( array(), $plan['tabs'] );
	}

	public function test_same_content_ignores_line_endings(): void {
		$this->assertTrue( YikesMapper::same_content( "<p>a</p>\r\n", "<p>a</p>\n" ) );
		$this->assertFalse( YikesMapper::same_content( '<table><tr>', '<table><tbody><tr>' ) );
	}
}
