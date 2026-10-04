<?php
/**
 * One-click import from YIKES Custom Product Tabs (dry run, batches, re-runnable, undo).
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Import;

use Pnscripts\ProductTabs\Domain\TabSanitizer;
use Pnscripts\ProductTabs\Storage\GlobalTabs;
use Pnscripts\ProductTabs\Storage\ProductTabs;

defined( 'ABSPATH' ) || exit;

/**
 * YIKES data is only read, never changed or deleted, so YIKES keeps working until it is deactivated
 * and the import can be undone. Re-running is safe:
 * - a saved tab that was imported before (matched by its YIKES id) is not created or changed again;
 * - a product whose YIKES data did not change since its last import is skipped (hash in meta);
 * - a product whose YIKES data changed gets its imported tabs replaced; tabs added in this plugin stay.
 *
 * @phpstan-import-type SavedTab from YikesMapper
 * @phpstan-type Report array{saved_found: int, saved_created: int, saved_existing: int, products_total: int, products_seen: int, products_imported: int, products_unchanged: int, tabs_custom: int, tabs_linked: int, tabs_disabled_empty: int, tabs_disabled_duplicate: int, replaced_defaults: int, removed_globals: int, removed_products: int, warnings_total: int, warnings: list<string>}
 * @phpstan-type Batch array{mode: string, cursor: int, done: bool, report: Report}
 */
final class YikesImporter {

	public const META_PRODUCT  = 'yikes_woo_products_tabs';
	public const OPTION_SAVED  = 'yikes_woo_reusable_products_tabs';
	public const OPTION_APPLY  = 'yikes_woo_reusable_products_tabs_applied';
	public const META_DEFAULTS = '_pnscripts_product_tabs_yikes_defaults';
	public const LAST_RUN      = 'pnscripts_product_tabs_yikes_last_run';

	public const MODE_DRY    = 'dry-run';
	public const MODE_IMPORT = 'import';
	public const MODE_UNDO   = 'undo';

	public const DEFAULT_BATCH = 100;

	/**
	 * Constructor.
	 *
	 * @param YikesMapper $mapper       Mapper.
	 * @param ProductTabs $product_tabs Product tabs storage.
	 * @param GlobalTabs  $globals      Global tabs storage.
	 */
	public function __construct(
		private readonly YikesMapper $mapper,
		private readonly ProductTabs $product_tabs,
		private readonly GlobalTabs $globals
	) {}

	/**
	 * Whether any YIKES data exists on this site.
	 */
	public function has_source(): bool {
		return $this->count_products() > 0 || array() !== $this->raw_saved_tabs();
	}

	/**
	 * Run one batch. Cursor 0 starts a run (and handles the saved tabs); the returned cursor continues it.
	 *
	 * @param string $mode   dry-run, import or undo.
	 * @param int    $cursor Last product id handled (0 to start).
	 * @param int    $batch  Products per batch.
	 * @return Batch
	 */
	public function run_batch( string $mode, int $cursor = 0, int $batch = self::DEFAULT_BATCH ): array {
		$batch  = max( 1, min( 500, $batch ) );
		$report = self::empty_report();
		if ( self::MODE_UNDO === $mode ) {
			return $this->undo_batch( $cursor, $batch, $report );
		}
		$mode  = self::MODE_IMPORT === $mode ? self::MODE_IMPORT : self::MODE_DRY;
		$write = self::MODE_IMPORT === $mode;

		$warnings = array();
		$saved    = $this->mapper->saved_tabs( $this->raw_saved_tabs(), $warnings );
		$map      = $this->existing_globals();

		$first = 0 === $cursor;
		if ( $first ) {
			$report['saved_found']    = count( $saved );
			$report['products_total'] = $this->count_products();
			$report['warnings']       = $warnings;
		}
		foreach ( $saved as $yikes_id => $tab ) {
			if ( isset( $map[ $yikes_id ] ) ) {
				$report['saved_existing'] += $first ? 1 : 0;
				continue;
			}
			$report['saved_created'] += $first ? 1 : 0;
			if ( ! $write ) {
				$map[ $yikes_id ] = PHP_INT_MAX; // Placeholder: would be created.
			} elseif ( $first ) {
				$map[ $yikes_id ] = $this->create_global( $tab );
			}
		}

		$applied    = YikesMapper::maybe_unserialize( get_option( self::OPTION_APPLY, array() ) );
		$applied    = is_array( $applied ) ? $applied : array();
		$unfiltered = self::unfiltered_html();
		$ids        = $this->product_ids_after( $cursor, $batch );

		foreach ( $ids as $product_id ) {
			++$report['products_seen'];
			$raw   = get_post_meta( $product_id, self::META_PRODUCT, true );
			$link  = $applied[ $product_id ] ?? array();
			$hash  = md5( (string) wp_json_encode( array( $raw, $link ) ) );
			$known = (string) get_post_meta( $product_id, ProductTabs::META_YIKES_HASH, true );
			if ( $known === $hash ) {
				++$report['products_unchanged'];
				continue;
			}
			$plan = $this->mapper->product( $product_id, $raw, $link, $saved, $map, $unfiltered );

			++$report['products_imported'];
			$report['tabs_custom']             += $plan['custom'];
			$report['tabs_linked']             += $plan['linked'];
			$report['tabs_disabled_empty']     += $plan['disabled_empty'];
			$report['tabs_disabled_duplicate'] += $plan['disabled_duplicate'];
			$report['replaced_defaults']       += $plan['replaced_defaults'];
			$report['warnings']                 = array_merge( $report['warnings'], $plan['warnings'] );

			if ( $write ) {
				$this->write_product( $product_id, $plan['tabs'], $plan['hidden_defaults'], $hash );
			}
		}

		$done = count( $ids ) < $batch;
		if ( $done && $write ) {
			update_option( self::LAST_RUN, time(), false );
		}
		$report['warnings_total'] = count( $report['warnings'] );
		$report['warnings']       = array_slice( $report['warnings'], 0, 200 );

		return array(
			'mode'   => $mode,
			'cursor' => array() !== $ids ? (int) end( $ids ) : $cursor,
			'done'   => $done,
			'report' => $report,
		);
	}

	/**
	 * Run all batches (WP-CLI and tests).
	 *
	 * @param string                                      $mode     Mode.
	 * @param int                                         $batch    Batch size.
	 * @param (callable(array<string, mixed>): void)|null $progress Called after each batch.
	 * @return Report
	 */
	public function run_all( string $mode, int $batch = self::DEFAULT_BATCH, ?callable $progress = null ): array {
		$total  = self::empty_report();
		$cursor = 0;
		do {
			$result = $this->run_batch( $mode, $cursor, $batch );
			$total  = self::merge( $total, $result['report'] );
			$cursor = $result['cursor'];
			if ( null !== $progress ) {
				$progress( $result );
			}
			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
		} while ( ! $result['done'] );
		return $total;
	}

	/**
	 * Add one batch report to a running total.
	 *
	 * @param Report $total Total.
	 * @param Report $add   Batch.
	 * @return Report
	 */
	public static function merge( array $total, array $add ): array {
		return array(
			'saved_found'             => $total['saved_found'] + $add['saved_found'],
			'saved_created'           => $total['saved_created'] + $add['saved_created'],
			'saved_existing'          => $total['saved_existing'] + $add['saved_existing'],
			'products_total'          => $total['products_total'] + $add['products_total'],
			'products_seen'           => $total['products_seen'] + $add['products_seen'],
			'products_imported'       => $total['products_imported'] + $add['products_imported'],
			'products_unchanged'      => $total['products_unchanged'] + $add['products_unchanged'],
			'tabs_custom'             => $total['tabs_custom'] + $add['tabs_custom'],
			'tabs_linked'             => $total['tabs_linked'] + $add['tabs_linked'],
			'tabs_disabled_empty'     => $total['tabs_disabled_empty'] + $add['tabs_disabled_empty'],
			'tabs_disabled_duplicate' => $total['tabs_disabled_duplicate'] + $add['tabs_disabled_duplicate'],
			'replaced_defaults'       => $total['replaced_defaults'] + $add['replaced_defaults'],
			'removed_globals'         => $total['removed_globals'] + $add['removed_globals'],
			'removed_products'        => $total['removed_products'] + $add['removed_products'],
			'warnings_total'          => $total['warnings_total'] + $add['warnings_total'],
			'warnings'                => array_slice( array_merge( $total['warnings'], $add['warnings'] ), 0, 200 ),
		);
	}

	/**
	 * Zeroed report.
	 *
	 * @return Report
	 */
	public static function empty_report(): array {
		return array(
			'saved_found'             => 0,
			'saved_created'           => 0,
			'saved_existing'          => 0,
			'products_total'          => 0,
			'products_seen'           => 0,
			'products_imported'       => 0,
			'products_unchanged'      => 0,
			'tabs_custom'             => 0,
			'tabs_linked'             => 0,
			'tabs_disabled_empty'     => 0,
			'tabs_disabled_duplicate' => 0,
			'replaced_defaults'       => 0,
			'removed_globals'         => 0,
			'removed_products'        => 0,
			'warnings_total'          => 0,
			'warnings'                => array(),
		);
	}

	/**
	 * Number of products with YIKES tabs.
	 */
	public function count_products(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off admin count; no API for it.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s",
				self::META_PRODUCT,
				'product'
			)
		);
		return (int) $count;
	}

	/**
	 * Product ids with YIKES tabs after a cursor.
	 *
	 * @param int $cursor Last id.
	 * @param int $limit  Limit.
	 * @return list<int>
	 */
	private function product_ids_after( int $cursor, int $limit ): array {
		return $this->ids_with_meta( self::META_PRODUCT, $cursor, $limit );
	}

	/**
	 * Product ids having a meta key, ordered by id, after a cursor.
	 *
	 * @param string $meta_key Meta key.
	 * @param int    $cursor   Last id.
	 * @param int    $limit    Limit.
	 * @return list<int>
	 */
	private function ids_with_meta( string $meta_key, int $cursor, int $limit ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batched admin import; ordered id cursor.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s AND pm.post_id > %d ORDER BY pm.post_id ASC LIMIT %d",
				$meta_key,
				'product',
				$cursor,
				$limit
			)
		);
		return array_values( array_map( 'intval', is_array( $ids ) ? $ids : array() ) );
	}

	/**
	 * Raw saved tabs option.
	 *
	 * @return array<mixed>
	 */
	private function raw_saved_tabs(): array {
		$raw = YikesMapper::maybe_unserialize( get_option( self::OPTION_SAVED, array() ) );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Global tabs created by earlier imports: YIKES id => post id (any status, so a tab the merchant
	 * trashed is not recreated).
	 *
	 * @return array<int, int>
	 */
	private function existing_globals(): array {
		$posts   = get_posts(
			array(
				'post_type'   => GlobalTabs::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => GlobalTabs::META_YIKES, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Few posts.
			)
		);
		$trashed = get_posts(
			array(
				'post_type'   => GlobalTabs::POST_TYPE,
				'post_status' => 'trash',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => GlobalTabs::META_YIKES, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Few posts.
			)
		);
		$map     = array();
		foreach ( array_merge( $posts, $trashed ) as $post_id ) {
			$post_id  = is_object( $post_id ) ? (int) $post_id->ID : (int) $post_id;
			$yikes_id = (int) get_post_meta( $post_id, GlobalTabs::META_YIKES, true );
			if ( $yikes_id > 0 && ! isset( $map[ $yikes_id ] ) ) {
				$map[ $yikes_id ] = $post_id;
			}
		}
		return $map;
	}

	/**
	 * Create a global tab from a saved tab.
	 *
	 * @param SavedTab $tab Saved tab.
	 * @return int Post id (0 on failure).
	 */
	private function create_global( array $tab ): int {
		$unfiltered = self::unfiltered_html();
		if ( $unfiltered ) {
			kses_remove_filters();
		}
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => GlobalTabs::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $tab['title'],
					'post_content' => $unfiltered ? $tab['content'] : wp_kses_post( $tab['content'] ),
				)
			),
			true
		);
		if ( $unfiltered && ! current_user_can( 'unfiltered_html' ) ) {
			kses_init_filters();
		}
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}
		$this->globals->save_settings(
			$post_id,
			array(
				'type'       => TabSanitizer::TYPE_CONTENT,
				'scope'      => $tab['scope'],
				'categories' => $tab['categories'],
				'tags'       => $tab['tags'],
				'priority'   => 25,
				'schema'     => false,
			),
			$unfiltered
		);
		update_post_meta( $post_id, GlobalTabs::META_YIKES, $tab['yikes_id'] );
		return $post_id;
	}

	/**
	 * Replace the imported tabs of a product, keeping tabs created in this plugin.
	 *
	 * @param int                        $product_id      Product id.
	 * @param list<array<string, mixed>> $imported        Imported tabs (already normalised).
	 * @param list<string>               $hidden_defaults Default tabs YIKES replaced.
	 * @param string                     $hash            Source hash.
	 */
	private function write_product( int $product_id, array $imported, array $hidden_defaults, string $hash ): void {
		$own  = array_values(
			array_filter(
				$this->product_tabs->tabs( $product_id ),
				static fn ( array $tab ): bool => ! str_starts_with( $tab['origin'], YikesMapper::ORIGIN )
			)
		);
		$tabs = TabSanitizer::product_tabs( array_merge( $imported, $own ), true );

		$previous = ProductTabs::default_keys( get_post_meta( $product_id, self::META_DEFAULTS, true ) );
		$current  = array_values( array_diff( $this->product_tabs->hidden_defaults( $product_id ), $previous ) );
		$defaults = array_values( array_unique( array_merge( $current, $hidden_defaults ) ) );

		$this->product_tabs->save( $product_id, $tabs, $this->product_tabs->hidden_globals( $product_id ), $defaults );
		update_post_meta( $product_id, ProductTabs::META_YIKES_HASH, $hash );
		if ( array() === $hidden_defaults ) {
			delete_post_meta( $product_id, self::META_DEFAULTS );
		} else {
			update_post_meta( $product_id, self::META_DEFAULTS, $hidden_defaults );
		}
		clean_post_cache( $product_id );
	}

	/**
	 * Undo: remove imported global tabs and imported product tabs (YIKES data was never changed).
	 *
	 * @param int    $cursor Last product id.
	 * @param int    $batch  Batch size.
	 * @param Report $report Report.
	 * @return Batch
	 */
	private function undo_batch( int $cursor, int $batch, array $report ): array {
		if ( 0 === $cursor ) {
			foreach ( $this->existing_globals() as $post_id ) {
				if ( wp_delete_post( $post_id, true ) ) {
					++$report['removed_globals'];
				}
			}
			$this->globals->flush();
			delete_option( self::LAST_RUN );
		}
		$ids = $this->ids_with_meta( ProductTabs::META_YIKES_HASH, $cursor, $batch );
		foreach ( $ids as $product_id ) {
			$own      = array_values(
				array_filter(
					$this->product_tabs->tabs( $product_id ),
					static fn ( array $tab ): bool => ! str_starts_with( $tab['origin'], YikesMapper::ORIGIN )
				)
			);
			$added    = ProductTabs::default_keys( get_post_meta( $product_id, self::META_DEFAULTS, true ) );
			$defaults = array_values( array_diff( $this->product_tabs->hidden_defaults( $product_id ), $added ) );
			$this->product_tabs->save( $product_id, $own, $this->product_tabs->hidden_globals( $product_id ), $defaults );
			delete_post_meta( $product_id, ProductTabs::META_YIKES_HASH );
			delete_post_meta( $product_id, self::META_DEFAULTS );
			clean_post_cache( $product_id );
			++$report['removed_products'];
		}
		return array(
			'mode'   => self::MODE_UNDO,
			'cursor' => array() !== $ids ? (int) end( $ids ) : $cursor,
			'done'   => count( $ids ) < $batch,
			'report' => $report,
		);
	}

	/**
	 * Content is kept byte for byte when the importing user may post unfiltered HTML (or on the command line).
	 */
	private static function unfiltered_html(): bool {
		return ( defined( 'WP_CLI' ) && WP_CLI ) || current_user_can( 'unfiltered_html' );
	}
}
