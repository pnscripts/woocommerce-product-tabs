<?php
/**
 * Products → Tab settings: default tabs, display options, data removal, YIKES import.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Admin;

use Pnscripts\ProductTabs\Domain\DefaultTabs;
use Pnscripts\ProductTabs\Import\YikesImporter;
use Pnscripts\ProductTabs\Settings;
use Pnscripts\ProductTabs\Storage\GlobalTabs;

defined( 'ABSPATH' ) || exit;

/**
 * Settings use the Settings API (options.php checks the nonce; the capability is manage_woocommerce).
 * The importer runs in batches over admin-ajax with its own nonce and capability check.
 */
final class SettingsPage {

	public const SLUG         = 'pnscripts-tabwise';
	public const LEGACY_SLUG  = 'pnscripts-product-tabs';
	public const GROUP        = 'pnscripts_product_tabs';
	public const AJAX_ACTION  = 'pnscripts_product_tabs_import';
	public const CAPABILITY   = 'manage_woocommerce';
	private const DISMISS_KEY = 'pnscripts_product_tabs_dismiss_yikes';

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings Settings.
	 * @param YikesImporter $importer Importer.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly YikesImporter $importer
	) {}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_import' ) );
		add_action( 'admin_notices', array( $this, 'yikes_notice' ) );
		add_action( 'admin_init', array( $this, 'maybe_dismiss' ) );
		add_action( 'admin_page_access_denied', array( $this, 'redirect_legacy_slug' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PNSCRIPTS_PRODUCT_TABS_FILE ), array( $this, 'action_links' ) );
		add_filter( 'option_page_capability_' . self::GROUP, static fn (): string => self::CAPABILITY );
	}

	/**
	 * Submenu under Products.
	 */
	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'Product tab settings', 'pnscripts-tabwise' ),
			__( 'Tab settings', 'pnscripts-tabwise' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Bookmarks of the 1.0.x page (page=pnscripts-product-tabs) open the renamed page.
	 */
	public function redirect_legacy_slug(): void {
		$url = self::legacy_redirect_url();
		if ( null !== $url ) {
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Where a request for the 1.0.x page slug should go, or null when it is not one.
	 */
	public static function legacy_redirect_url(): ?string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only redirect between two admin pages.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( self::LEGACY_SLUG !== $page || ! current_user_can( self::CAPABILITY ) ) {
			return null;
		}
		return self::url( $tab );
	}

	/**
	 * Register the option with its sanitiser.
	 */
	public function register_setting(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'show_in_rest'      => false,
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param mixed $links Links.
	 * @return array<array-key, mixed>
	 */
	public function action_links( mixed $links ): array {
		$links = is_array( $links ) ? $links : array();
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'pnscripts-tabwise' ) ),
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'edit.php?post_type=' . GlobalTabs::POST_TYPE ) ), esc_html__( 'Global tabs', 'pnscripts-tabwise' ) )
		);
		return $links;
	}

	/**
	 * Page URL.
	 *
	 * @param string $tab Page tab.
	 */
	public static function url( string $tab = '' ): string {
		$args = array(
			'post_type' => 'product',
			'page'      => self::SLUG,
		);
		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	/**
	 * Assets on our page.
	 *
	 * @param string $hook_suffix Page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( 'product_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'pnscripts-product-tabs-admin', PNSCRIPTS_PRODUCT_TABS_URL . 'assets/css/admin.css', array(), PNSCRIPTS_PRODUCT_TABS_VERSION );
		wp_enqueue_script( 'pnscripts-product-tabs-import', PNSCRIPTS_PRODUCT_TABS_URL . 'assets/js/admin-import.js', array(), PNSCRIPTS_PRODUCT_TABS_VERSION, true );
		wp_localize_script(
			'pnscripts-product-tabs-import',
			'pnscriptsProductTabsImport',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'action'        => self::AJAX_ACTION,
				'nonce'         => wp_create_nonce( self::AJAX_ACTION ),
				'confirmImport' => __( 'Import the YIKES tabs now? YIKES data is not changed and you can undo the import.', 'pnscripts-tabwise' ),
				'confirmUndo'   => __( 'Remove all tabs created by the import (tabs you added yourself stay)?', 'pnscripts-tabwise' ),
				/* translators: %d: number of products handled so far */
				'running'       => __( 'Working… %d products handled', 'pnscripts-tabwise' ),
				'failed'        => __( 'The request failed. Reload the page and try again.', 'pnscripts-tabwise' ),
				'doneDry'       => __( 'Dry run finished: nothing was changed. These numbers are what an import will do.', 'pnscripts-tabwise' ),
				'doneImport'    => __( 'Import finished. Check a few product pages, then deactivate YIKES Custom Product Tabs.', 'pnscripts-tabwise' ),
				'doneUndo'      => __( 'Import removed.', 'pnscripts-tabwise' ),
				/* translators: %d: number of further notes */
				'moreNotes'     => __( '… and %d more notes like these.', 'pnscripts-tabwise' ),
				'labels'        => self::report_labels(),
			)
		);
	}

	/**
	 * Labels of the report counters.
	 *
	 * @return array<string, string>
	 */
	public static function report_labels(): array {
		return array(
			'products_total'          => __( 'Products with YIKES tabs', 'pnscripts-tabwise' ),
			'saved_found'             => __( 'YIKES saved tabs', 'pnscripts-tabwise' ),
			'saved_created'           => __( 'Global tabs to create / created', 'pnscripts-tabwise' ),
			'saved_existing'          => __( 'Saved tabs imported before (left as they are)', 'pnscripts-tabwise' ),
			'products_imported'       => __( 'Products to import / imported', 'pnscripts-tabwise' ),
			'products_unchanged'      => __( 'Products unchanged since the last import (skipped)', 'pnscripts-tabwise' ),
			'tabs_custom'             => __( 'Product tabs copied', 'pnscripts-tabwise' ),
			'tabs_linked'             => __( 'Product tabs linked to a global tab', 'pnscripts-tabwise' ),
			'tabs_disabled_empty'     => __( 'Tabs without a title (imported switched off)', 'pnscripts-tabwise' ),
			'tabs_disabled_duplicate' => __( 'Tabs hidden by a duplicate title in YIKES (imported switched off)', 'pnscripts-tabwise' ),
			'replaced_defaults'       => __( 'Default tabs YIKES replaced (hidden on those products)', 'pnscripts-tabwise' ),
			'removed_globals'         => __( 'Imported global tabs removed', 'pnscripts-tabwise' ),
			'removed_products'        => __( 'Products cleaned', 'pnscripts-tabwise' ),
		);
	}

	/**
	 * AJAX: one import batch.
	 */
	public function ajax_import(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to import tabs.', 'pnscripts-tabwise' ) ), 403 );
		}
		$mode   = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : YikesImporter::MODE_DRY;
		$cursor = isset( $_POST['cursor'] ) ? absint( wp_unslash( $_POST['cursor'] ) ) : 0;
		if ( ! in_array( $mode, array( YikesImporter::MODE_DRY, YikesImporter::MODE_IMPORT, YikesImporter::MODE_UNDO ), true ) ) {
			wp_send_json_error( array( 'message' => 'mode' ), 400 );
		}
		wp_send_json_success( $this->importer->run_batch( $mode, $cursor ) );
	}

	/**
	 * Page.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$tab = isset( $_GET['tab'] ) && 'import' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ? 'import' : 'settings';
		?>
		<div class="wrap pnscripts-pt-settings">
			<h1><?php esc_html_e( 'Product tab settings', 'pnscripts-tabwise' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( self::url() ); ?>" class="nav-tab<?php echo 'settings' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'pnscripts-tabwise' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . GlobalTabs::POST_TYPE ) ); ?>" class="nav-tab"><?php esc_html_e( 'Global tabs', 'pnscripts-tabwise' ); ?></a>
				<a href="<?php echo esc_url( self::url( 'import' ) ); ?>" class="nav-tab<?php echo 'import' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Import from YIKES', 'pnscripts-tabwise' ); ?></a>
			</nav>
			<?php
			if ( 'import' === $tab ) {
				$this->render_import();
			} else {
				$this->render_settings();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Settings form.
	 */
	private function render_settings(): void {
		$s      = $this->settings->all();
		$name   = Settings::OPTION;
		$labels = ProductPanel::default_labels();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( self::GROUP ); ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[submitted]" value="1" />
			<h2><?php esc_html_e( 'WooCommerce tabs', 'pnscripts-tabwise' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: the literal placeholder "%d" that merchants type into the Reviews title */
					esc_html__( 'Rename, reorder or hide the standard tabs on every product (single products can hide them too, under Product data → Custom tabs). Leave a title empty to keep WooCommerce\'s title; in the Reviews title, %s becomes the number of reviews.', 'pnscripts-tabwise' ),
					'<code>%d</code>'
				);
				?>
			</p>
			<table class="widefat striped pnscripts-pt-defaults">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Tab', 'pnscripts-tabwise' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Show', 'pnscripts-tabwise' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Title', 'pnscripts-tabwise' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Priority', 'pnscripts-tabwise' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( DefaultTabs::KEYS as $key ) : ?>
						<?php $row = $s['defaults'][ $key ]; ?>
						<tr>
							<th scope="row"><?php echo esc_html( $labels[ $key ] ?? $key ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( $name . '[defaults][' . $key . '][enabled]' ); ?>" value="0" />
								<input type="checkbox" id="pnscripts-pt-show-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name . '[defaults][' . $key . '][enabled]' ); ?>" value="1" <?php checked( $row['enabled'] ); ?> />
								<label class="screen-reader-text" for="pnscripts-pt-show-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Show', 'pnscripts-tabwise' ); ?></label>
							</td>
							<td>
								<input type="text" class="regular-text" aria-label="<?php esc_attr_e( 'Title', 'pnscripts-tabwise' ); ?>" name="<?php echo esc_attr( $name . '[defaults][' . $key . '][title]' ); ?>" value="<?php echo esc_attr( $row['title'] ); ?>" placeholder="<?php echo esc_attr( $labels[ $key ] ?? '' ); ?>" />
							</td>
							<td>
								<input type="number" class="small-text" min="0" max="999" aria-label="<?php esc_attr_e( 'Priority', 'pnscripts-tabwise' ); ?>" name="<?php echo esc_attr( $name . '[defaults][' . $key . '][priority]' ); ?>" value="<?php echo esc_attr( (string) $row['priority'] ); ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Custom tabs', 'pnscripts-tabwise' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pnscripts-pt-custom-priority"><?php esc_html_e( 'Priority of product tabs', 'pnscripts-tabwise' ); ?></label></th>
					<td>
						<input type="number" class="small-text" min="0" max="999" id="pnscripts-pt-custom-priority" name="<?php echo esc_attr( $name ); ?>[custom_priority]" value="<?php echo esc_attr( (string) $s['custom_priority'] ); ?>" />
						<p class="description"><?php esc_html_e( 'The first tab added on a product gets this priority, the next one +1, and so on. 25 places them between Additional information and Reviews. Global tabs have their own priority.', 'pnscripts-tabwise' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Display', 'pnscripts-tabwise' ); ?></th>
					<td>
						<?php
						self::checkbox( 'reviews_last', $s['reviews_last'], __( 'Keep Reviews as the last tab', 'pnscripts-tabwise' ) );
						self::checkbox( 'show_heading', $s['show_heading'], __( 'Repeat the tab title as a heading inside the tab (classic tabs; never inside accordions)', 'pnscripts-tabwise' ) );
						self::checkbox( 'faq_schema', $s['faq_schema'], __( 'Output FAQPage structured data for FAQ tabs that allow it (turn off if your SEO plugin already adds FAQ markup)', 'pnscripts-tabwise' ) );
						self::checkbox( 'hide_yikes', $s['hide_yikes'], __( 'While YIKES Custom Product Tabs is still active, hide its copy of tabs that were imported', 'pnscripts-tabwise' ) );
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Uninstall', 'pnscripts-tabwise' ); ?></th>
					<td>
						<?php self::checkbox( 'remove_data', $s['remove_data'], __( 'Delete all tabs and settings of this plugin when it is deleted from the Plugins screen', 'pnscripts-tabwise' ) ); ?>
						<p class="description"><?php esc_html_e( 'Off by default, so deleting and reinstalling the plugin keeps your tabs. YIKES data is never touched.', 'pnscripts-tabwise' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Checkbox row.
	 *
	 * @param string $key     Setting key.
	 * @param bool   $checked Value.
	 * @param string $label   Label.
	 */
	private static function checkbox( string $key, bool $checked, string $label ): void {
		printf(
			'<p><label><input type="hidden" name="%1$s" value="0" /><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label></p>',
			esc_attr( Settings::OPTION . '[' . $key . ']' ),
			checked( $checked, true, false ),
			esc_html( $label )
		);
	}

	/**
	 * Import screen.
	 */
	private function render_import(): void {
		$products = $this->importer->count_products();
		$active   = class_exists( 'YIKES_Custom_Product_Tabs_Display', false ) || defined( 'YIKES_Custom_Product_Tabs_Version' );
		$last     = (int) get_option( YikesImporter::LAST_RUN, 0 );
		?>
		<div class="pnscripts-pt-import" data-pnscripts-pt-import>
			<p><?php esc_html_e( 'Copies the tabs of "Custom Product Tabs for WooCommerce" by YIKES: every product\'s tabs (in the same order, with the same content) and the saved tabs, which become global tabs. Products that used a saved tab are linked to the new global tab, so editing it updates them all. Saved tabs that the YIKES Pro add-on assigned to all products or to categories/tags keep those rules.', 'pnscripts-tabwise' ); ?></p>
			<ul class="ul-disc">
				<li><?php esc_html_e( 'YIKES data is only read. Nothing is changed or deleted, and YIKES keeps working until you deactivate it.', 'pnscripts-tabwise' ); ?></li>
				<li><?php esc_html_e( 'Running it again is safe: tabs are never duplicated, and products whose YIKES tabs did not change are skipped.', 'pnscripts-tabwise' ); ?></li>
				<li><?php esc_html_e( 'Tabs YIKES never showed (no title, or hidden by another tab with the same title) are imported switched off and listed below.', 'pnscripts-tabwise' ); ?></li>
				<li><?php esc_html_e( 'Large catalogues are handled in batches of 100 products. Command line: wp pnscripts-tabwise import-yikes --dry-run', 'pnscripts-tabwise' ); ?></li>
			</ul>
			<p>
				<strong><?php esc_html_e( 'Found:', 'pnscripts-tabwise' ); ?></strong>
				<?php
				printf(
					/* translators: %d: number of products */
					esc_html( _n( '%d product with YIKES tabs', '%d products with YIKES tabs', $products, 'pnscripts-tabwise' ) ),
					(int) $products
				);
				echo ' · ';
				echo $active ? esc_html__( 'YIKES plugin active', 'pnscripts-tabwise' ) : esc_html__( 'YIKES plugin not active (data can still be imported)', 'pnscripts-tabwise' );
				$when = $last > 0 ? wp_date( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ), $last ) : false;
				if ( is_string( $when ) ) {
					echo ' · ';
					printf(
						/* translators: %s: date and time */
						esc_html__( 'last import: %s', 'pnscripts-tabwise' ),
						esc_html( $when )
					);
				}
				?>
			</p>
			<p class="pnscripts-pt-import__actions">
				<button type="button" class="button button-secondary" data-pnscripts-pt-run="dry-run"><?php esc_html_e( 'Dry run (change nothing)', 'pnscripts-tabwise' ); ?></button>
				<button type="button" class="button button-primary" data-pnscripts-pt-run="import"><?php esc_html_e( 'Import now', 'pnscripts-tabwise' ); ?></button>
				<button type="button" class="button button-link-delete" data-pnscripts-pt-run="undo"><?php esc_html_e( 'Undo import', 'pnscripts-tabwise' ); ?></button>
			</p>
			<p class="pnscripts-pt-import__status" role="status" aria-live="polite" data-pnscripts-pt-status></p>
			<table class="widefat striped pnscripts-pt-import__report hidden" data-pnscripts-pt-report><tbody></tbody></table>
			<div class="pnscripts-pt-import__warnings hidden" data-pnscripts-pt-warnings>
				<h3><?php esc_html_e( 'Notes', 'pnscripts-tabwise' ); ?></h3>
				<ul class="ul-disc"></ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Notice when YIKES data is present and was never imported (dismissible per user).
	 */
	public function yikes_notice(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || ! in_array( $screen->id, array( 'plugins', 'edit-product', 'edit-' . GlobalTabs::POST_TYPE ), true ) ) {
			return;
		}
		if ( get_user_meta( get_current_user_id(), self::DISMISS_KEY, true ) || (int) get_option( YikesImporter::LAST_RUN, 0 ) > 0 ) {
			return;
		}
		if ( 0 === $this->importer->count_products() ) {
			return;
		}
		$dismiss = wp_nonce_url( add_query_arg( self::DISMISS_KEY, '1' ), self::DISMISS_KEY );
		printf(
			'<div class="notice notice-info"><p>%1$s</p><p><a class="button button-primary" href="%2$s">%3$s</a> <a class="button-link" href="%4$s">%5$s</a></p></div>',
			esc_html__( 'PN Scripts Tabwise found tabs from Custom Product Tabs for WooCommerce (YIKES). Import them in one click; YIKES data stays untouched.', 'pnscripts-tabwise' ),
			esc_url( self::url( 'import' ) ),
			esc_html__( 'Review the import', 'pnscripts-tabwise' ),
			esc_url( $dismiss ),
			esc_html__( 'Dismiss', 'pnscripts-tabwise' )
		);
	}

	/**
	 * Store the dismissal.
	 */
	public function maybe_dismiss(): void {
		if ( ! isset( $_GET[ self::DISMISS_KEY ], $_GET['_wpnonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), self::DISMISS_KEY ) ) {
			return;
		}
		update_user_meta( get_current_user_id(), self::DISMISS_KEY, 1 );
		wp_safe_redirect( remove_query_arg( array( self::DISMISS_KEY, '_wpnonce' ) ) );
		exit;
	}
}
