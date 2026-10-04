<?php
/**
 * WP-CLI commands.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Cli;

use Pnscripts\ProductTabs\Import\YikesImporter;
use Pnscripts\ProductTabs\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Manage PN Product Tabs.
 */
final class Command {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private readonly Plugin $plugin ) {}

	/**
	 * Import tabs from YIKES Custom Product Tabs for WooCommerce.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would be imported without writing anything.
	 *
	 * [--undo]
	 * : Remove everything an earlier import created.
	 *
	 * [--batch=<n>]
	 * : Products per batch.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format of the report.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pnscripts-product-tabs import-yikes --dry-run
	 *     wp pnscripts-product-tabs import-yikes
	 *     wp pnscripts-product-tabs import-yikes --undo
	 *
	 * @subcommand import-yikes
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Options.
	 */
	public function import_yikes( array $args, array $assoc_args ): void {
		unset( $args );
		$mode = YikesImporter::MODE_IMPORT;
		if ( ! empty( $assoc_args['dry-run'] ) ) {
			$mode = YikesImporter::MODE_DRY;
		} elseif ( ! empty( $assoc_args['undo'] ) ) {
			$mode = YikesImporter::MODE_UNDO;
		}
		$batch  = isset( $assoc_args['batch'] ) && is_numeric( $assoc_args['batch'] ) ? (int) $assoc_args['batch'] : YikesImporter::DEFAULT_BATCH;
		$report = $this->plugin->importer->run_all( $mode, $batch );
		$format = isset( $assoc_args['format'] ) && 'json' === $assoc_args['format'] ? 'json' : 'table';

		if ( 'json' === $format ) {
			\WP_CLI::line( (string) wp_json_encode( array( 'mode' => $mode ) + $report, JSON_PRETTY_PRINT ) );
			return;
		}
		$rows = array();
		foreach ( $report as $key => $value ) {
			if ( 'warnings' !== $key && 'warnings_total' !== $key ) {
				$rows[] = array(
					'counter' => $key,
					'value'   => $value,
				);
			}
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'counter', 'value' ) );
		$shown = array_slice( $report['warnings'], 0, 20 );
		foreach ( $shown as $warning ) {
			\WP_CLI::warning( $warning );
		}
		if ( $report['warnings_total'] > count( $shown ) ) {
			\WP_CLI::log( sprintf( '… %d more notes (--format=json lists the first 200).', $report['warnings_total'] - count( $shown ) ) );
		}
		\WP_CLI::success( YikesImporter::MODE_DRY === $mode ? 'Dry run finished; nothing was changed.' : 'Done.' );
	}

	/**
	 * List the custom tabs a product shows.
	 *
	 * ## OPTIONS
	 *
	 * <product-id>
	 * : Product id.
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Options.
	 */
	public function tabs( array $args, array $assoc_args ): void {
		$product_id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( ! wc_get_product( $product_id ) ) {
			\WP_CLI::error( 'Product not found.' );
		}
		$rows = array();
		foreach ( $this->plugin->renderer->resolved( $product_id ) as $tab ) {
			$rows[] = array(
				'key'      => $tab['key'],
				'title'    => $tab['title'],
				'priority' => $tab['priority'],
				'type'     => $tab['type'],
				'source'   => $tab['source'],
			);
		}
		$format = isset( $assoc_args['format'] ) && 'json' === $assoc_args['format'] ? 'json' : 'table';
		\WP_CLI\Utils\format_items( $format, $rows, array( 'key', 'title', 'priority', 'type', 'source' ) );
	}
}
