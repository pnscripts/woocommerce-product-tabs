<?php
/**
 * YIKES import on a real site with YIKES Custom Product Tabs 1.8.6 active.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Integration;

use Pnscripts\ProductTabs\Import\YikesImporter;
use Pnscripts\ProductTabs\Storage\GlobalTabs;
use Pnscripts\ProductTabs\Storage\ProductTabs;

final class YikesImportTest extends IntegrationTestCase {

	/**
	 * Fixture product key ("10", "12", "13") => new product id.
	 *
	 * @var array<string, int>
	 */
	private array $ids = array();

	protected function setUp(): void {
		parent::setUp();
		$this->assertTrue( class_exists( 'YIKES_Custom_Product_Tabs_Display', false ), 'YIKES must be active on the test site.' );
		$fixture = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/yikes-1.8.6.json' ), true );
		$this->assertIsArray( $fixture );
		$applied = array();
		foreach ( $fixture['products'] as $key => $tabs ) {
			$id                         = $this->product( 'YIKES fixture ' . $key );
			$this->ids[ (string) $key ] = $id;
			update_post_meta( $id, YikesImporter::META_PRODUCT, wp_slash( $tabs ) );
			if ( isset( $fixture['applied'][ $key ] ) ) {
				$links = $fixture['applied'][ $key ];
				foreach ( $links as $saved_id => $link ) {
					$links[ $saved_id ]['post_id'] = (string) $id;
				}
				$applied[ $id ] = $links;
			}
		}
		update_option( YikesImporter::OPTION_SAVED, wp_slash( $fixture['saved'] ) );
		update_option( YikesImporter::OPTION_APPLY, $applied );
	}

	/**
	 * Snapshot of all YIKES data (must never change).
	 */
	private function yikes_snapshot(): string {
		global $wpdb;
		$meta = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id", YikesImporter::META_PRODUCT ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return md5( serialize( array( $meta, get_option( YikesImporter::OPTION_SAVED ), get_option( YikesImporter::OPTION_APPLY ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Snapshot of everything this plugin stores.
	 */
	private function own_snapshot(): string {
		global $wpdb;
		$meta  = $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_pnscripts\\_product\\_tabs%' ORDER BY post_id, meta_key", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content, post_modified FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID", GlobalTabs::POST_TYPE ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return md5( serialize( array( $meta, $posts ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	public function test_dry_run_writes_nothing_and_predicts_the_import(): void {
		$yikes  = $this->yikes_snapshot();
		$own    = $this->own_snapshot();
		$dry    = $this->plugin->importer->run_all( YikesImporter::MODE_DRY );
		$this->assertSame( $own, $this->own_snapshot() );
		$this->assertSame( $yikes, $this->yikes_snapshot() );
		$this->assertSame( 3, $dry['products_total'] );
		$this->assertSame( 2, $dry['saved_created'] );

		$real = $this->plugin->importer->run_all( YikesImporter::MODE_IMPORT );
		foreach ( array( 'saved_created', 'products_imported', 'tabs_custom', 'tabs_linked', 'tabs_disabled_empty', 'tabs_disabled_duplicate', 'replaced_defaults' ) as $key ) {
			$this->assertSame( $dry[ $key ], $real[ $key ], $key );
		}
		$this->assertSame( $yikes, $this->yikes_snapshot(), 'YIKES data must not change.' );
	}

	public function test_import_is_idempotent_and_keeps_own_tabs(): void {
		$first = $this->plugin->importer->run_all( YikesImporter::MODE_IMPORT );
		$this->assertSame( 3, $first['products_imported'] );
		$this->assertSame( 2, $first['saved_created'] );
		$this->assertSame( 3, $first['tabs_linked'] );
		$snapshot = $this->own_snapshot();

		$second = $this->plugin->importer->run_all( YikesImporter::MODE_IMPORT );
		$this->assertSame( 0, $second['products_imported'] );
		$this->assertSame( 3, $second['products_unchanged'] );
		$this->assertSame( 0, $second['saved_created'] );
		$this->assertSame( 2, $second['saved_existing'] );
		$this->assertSame( $snapshot, $this->own_snapshot(), 'A second run must not write anything.' );

		// The merchant adds a tab of their own, then YIKES data of that product changes: re-import replaces only imported tabs.
		$id   = $this->ids['12'];
		$repo = $this->plugin->product_tabs;
		$tabs = $repo->tabs( $id );
		$tabs[] = array(
			'id'        => 'mine1',
			'type'      => 'content',
			'title'     => 'Mine',
			'content'   => 'Own tab',
			'faq'       => array(),
			'schema'    => false,
			'global_id' => 0,
			'enabled'   => true,
			'origin'    => '',
		);
		$repo->save( $id, $tabs, array(), array() );
		$yikes   = get_post_meta( $id, YikesImporter::META_PRODUCT, true );
		$yikes[] = array(
			'title'   => 'New in YIKES',
			'id'      => 'new-in-yikes',
			'content' => 'x',
		);
		update_post_meta( $id, YikesImporter::META_PRODUCT, wp_slash( $yikes ) );

		$third = $this->plugin->importer->run_all( YikesImporter::MODE_IMPORT );
		$this->assertSame( 1, $third['products_imported'] );
		$titles = array_column( $repo->tabs( $id ), 'title' );
		$this->assertContains( 'Mine', $titles );
		$this->assertContains( 'New in YIKES', $titles );
		$this->assertCount( 4, $repo->tabs( $id ), 'linked shipping + Технически данни + New in YIKES + Mine' );
	}

	public function test_storefront_is_identical_after_import(): void {
		$before = array();
		foreach ( $this->ids as $key => $id ) {
			$before[ $key ] = $this->visible( $id );
		}
		$this->assertSame(
			array( 'Description: Description Product description.', 'Shipping & Returns: Shipping & Returns Free shipping over €50 . Returns accepted within 30 days. EU: 2–4 days Rest of world: 5–10 days', 'Materials: Materials Upper: recycled mesh. Sole: Vibram® rubber.', 'Care: Care Hand wash cold. Air dry.', 'Size guide: Size guide EU US 42 8.5 Measure your foot in the evening.', 'Reviews (0): ' ),
			array_map( static fn ( string $row ): string => 0 === strpos( $row, 'Reviews' ) ? 'Reviews (0): ' : $row, $before['10'] ),
			'YIKES baseline of the fixture product'
		);
		$this->plugin->importer->run_all( YikesImporter::MODE_IMPORT );
		foreach ( $this->ids as $key => $id ) {
			// YIKES is still active: its copies are hidden, ours are shown.
			$this->assertSame( $before[ $key ], $this->visible( $id ), "Product $key with both plugins" );
		}

		// Same result with YIKES' filter removed (as after deactivating YIKES).
		$removed = $this->without_yikes(
			function () use ( $before ): void {
				foreach ( $this->ids as $key => $id ) {
					$this->assertSame( $before[ $key ], $this->visible( $id ), "Product $key without YIKES" );
				}
			}
		);
		$this->assertTrue( $removed );
	}

	public function test_undo_removes_everything_imported(): void {
		$yikes = $this->yikes_snapshot();
		$own   = $this->own_snapshot();
		$this->plugin->importer->run_all( YikesImporter::MODE_IMPORT );
		$this->assertNotSame( $own, $this->own_snapshot() );
		$undo = $this->plugin->importer->run_all( YikesImporter::MODE_UNDO );
		$this->assertSame( 2, $undo['removed_globals'] );
		$this->assertSame( 3, $undo['removed_products'] );
		$this->assertSame( $own, $this->own_snapshot() );
		$this->assertSame( $yikes, $this->yikes_snapshot() );
	}

	public function test_batches_give_the_same_result_as_one_run(): void {
		$template = get_post_meta( $this->ids['10'], YikesImporter::META_PRODUCT, true );
		for ( $i = 0; $i < 23; $i++ ) {
			update_post_meta( $this->product( 'Bulk ' . $i ), YikesImporter::META_PRODUCT, wp_slash( $template ) );
		}
		$small = $this->plugin->importer->run_all( YikesImporter::MODE_DRY, 4 );
		$big   = $this->plugin->importer->run_all( YikesImporter::MODE_DRY, 500 );
		$this->assertSame( 26, $small['products_seen'] );
		$small['warnings'] = array();
		$big['warnings']   = array();
		$this->assertSame( $big, $small );

		$batch = $this->plugin->importer->run_batch( YikesImporter::MODE_IMPORT, 0, 10 );
		$this->assertFalse( $batch['done'] );
		$this->assertSame( 10, $batch['report']['products_seen'] );
		$next = $this->plugin->importer->run_batch( YikesImporter::MODE_IMPORT, $batch['cursor'], 10 );
		$this->assertSame( 0, $next['report']['saved_created'], 'Saved tabs are created in the first batch only.' );
	}

	public function test_global_and_category_saved_tabs_from_yikes_pro(): void {
		$category = $this->category( 'Boots' );
		$saved    = get_option( YikesImporter::OPTION_SAVED );
		$saved[7] = array(
			'tab_title'   => 'Everywhere',
			'tab_name'    => '',
			'tab_content' => '<p>Global</p>',
			'tab_id'      => 7,
			'taxonomies'  => array(),
			'tab_slug'    => 'everywhere',
			'global_tab'  => true,
		);
		$saved[8] = array(
			'tab_title'   => 'Boot care',
			'tab_name'    => '',
			'tab_content' => '<p>Wax</p>',
			'tab_id'      => 8,
			'taxonomies'  => array( 'product_cat' => array( $category => 'boots' ) ),
			'tab_slug'    => 'boot-care',
			'global_tab'  => false,
		);
		update_option( YikesImporter::OPTION_SAVED, wp_slash( $saved ) );
		$this->plugin->importer->run_all( YikesImporter::MODE_IMPORT );
		$boot  = $this->product( 'Boot', array( $category ) );
		$plain = $this->product( 'Plain' );
		$this->assertArrayHasKey( 'boot-care', $this->storefront_tabs( $boot ) );
		$this->assertArrayHasKey( 'everywhere', $this->storefront_tabs( $boot ) );
		$this->assertArrayNotHasKey( 'boot-care', $this->storefront_tabs( $plain ) );
		$this->assertArrayHasKey( 'everywhere', $this->storefront_tabs( $plain ) );
	}

	/**
	 * Visible tabs as "title: text" in display order.
	 *
	 * @param int $id Product id.
	 * @return list<string>
	 */
	private function visible( int $id ): array {
		$out = array();
		foreach ( $this->storefront_tabs( $id ) as $tab ) {
			$text = self::text( $tab['html'] );
			// YIKES prints the title as an h2 inside the panel; so do we (show_heading default on).
			$out[] = $tab['title'] . ': ' . $text;
		}
		return $out;
	}

	/**
	 * Run a callback with YIKES' storefront filter removed.
	 *
	 * @param callable $callback Callback.
	 */
	private function without_yikes( callable $callback ): bool {
		global $wp_filter;
		$removed = array();
		foreach ( $wp_filter['woocommerce_product_tabs']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $entry ) {
				if ( is_array( $entry['function'] ) && $entry['function'][0] instanceof \YIKES_Custom_Product_Tabs_Display ) {
					remove_filter( 'woocommerce_product_tabs', $entry['function'], $priority );
					$removed[] = array( $entry['function'], $priority );
				}
			}
		}
		try {
			$callback();
		} finally {
			foreach ( $removed as $item ) {
				add_filter( 'woocommerce_product_tabs', $item[0], $item[1] );
			}
		}
		return array() !== $removed;
	}
}
