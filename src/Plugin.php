<?php
/**
 * Plugin bootstrap and service container.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs;

use Pnscripts\ProductTabs\Admin\GlobalTabMetaBox;
use Pnscripts\ProductTabs\Admin\ProductPanel;
use Pnscripts\ProductTabs\Admin\SettingsPage;
use Pnscripts\ProductTabs\Cli\Command;
use Pnscripts\ProductTabs\Domain\TabResolver;
use Pnscripts\ProductTabs\Frontend\BlockCompat;
use Pnscripts\ProductTabs\Frontend\TabRenderer;
use Pnscripts\ProductTabs\Import\YikesImporter;
use Pnscripts\ProductTabs\Import\YikesMapper;
use Pnscripts\ProductTabs\Licensing\FreeLicense;
use Pnscripts\ProductTabs\Licensing\LicenseInterface;
use Pnscripts\ProductTabs\Storage\GlobalTabs;
use Pnscripts\ProductTabs\Storage\ProductTabs;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the services. Add-ons receive the instance through the pnscripts_product_tabs_loaded action.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	public readonly Settings $settings;

	/**
	 * Global tabs storage.
	 *
	 * @var GlobalTabs
	 */
	public readonly GlobalTabs $globals;

	/**
	 * Product tabs storage.
	 *
	 * @var ProductTabs
	 */
	public readonly ProductTabs $product_tabs;

	/**
	 * Rules.
	 *
	 * @var TabResolver
	 */
	public readonly TabResolver $resolver;

	/**
	 * Storefront renderer.
	 *
	 * @var TabRenderer
	 */
	public readonly TabRenderer $renderer;

	/**
	 * Accordion Product Details block integration.
	 *
	 * @var BlockCompat
	 */
	public readonly BlockCompat $block_compat;

	/**
	 * YIKES importer.
	 *
	 * @var YikesImporter
	 */
	public readonly YikesImporter $importer;

	/**
	 * Build services.
	 */
	private function __construct() {
		$slugify            = static fn ( string $title ): string => urldecode( sanitize_title( $title ) );
		$this->settings     = new Settings();
		$this->globals      = new GlobalTabs();
		$this->product_tabs = new ProductTabs();
		$this->resolver     = new TabResolver( $slugify );
		$this->renderer     = new TabRenderer( $this->settings, $this->globals, $this->product_tabs, $this->resolver );
		$this->block_compat = new BlockCompat( $this->settings, $this->product_tabs, $this->renderer );
		$this->importer     = new YikesImporter( new YikesMapper( $slugify ), $this->product_tabs, $this->globals );
	}

	/**
	 * Instance (null before boot or when WooCommerce is missing).
	 */
	public static function instance(): ?Plugin {
		return self::$instance;
	}

	/**
	 * Boot on plugins_loaded.
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}
		if ( ! self::woocommerce_ready() ) {
			add_action( 'admin_notices', array( self::class, 'missing_woocommerce_notice' ) );
			return;
		}

		self::$instance = new self();
		self::$instance->register();

		/**
		 * Fires when the plugin is ready. Add-ons receive the service container.
		 *
		 * @param Plugin $plugin Plugin.
		 */
		do_action( 'pnscripts_product_tabs_loaded', self::$instance );
	}

	/**
	 * Register hooks.
	 */
	private function register(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'update_option_' . Settings::OPTION, array( $this->settings, 'flush' ) );

		$this->globals->register();
		$this->product_tabs->register();
		$this->renderer->register();
		$this->block_compat->register();

		if ( is_admin() ) {
			( new ProductPanel( $this->globals, $this->product_tabs ) )->register();
			( new GlobalTabMetaBox( $this->globals ) )->register();
			( new SettingsPage( $this->settings, $this->importer ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'pnscripts-product-tabs', new Command( $this ) );
		}
	}

	/**
	 * Bundled translations (translate.wordpress.org packs take precedence once available).
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'pnscripts-product-tabs', false, dirname( plugin_basename( PNSCRIPTS_PRODUCT_TABS_FILE ) ) . '/languages' );
	}

	/**
	 * Active licence implementation (free no-op unless an add-on provides one).
	 */
	public function license(): LicenseInterface {
		$license = apply_filters( 'pnscripts_product_tabs_license', null );
		return $license instanceof LicenseInterface ? $license : new FreeLicense();
	}

	/**
	 * WooCommerce active in a supported version.
	 */
	private static function woocommerce_ready(): bool {
		return class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' ) && version_compare( (string) WC_VERSION, PNSCRIPTS_PRODUCT_TABS_MIN_WC, '>=' );
	}

	/**
	 * Admin notice when WooCommerce is missing or too old.
	 */
	public static function missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: minimum WooCommerce version */
					__( 'PN Product Tabs needs WooCommerce %s or newer to be active.', 'pnscripts-product-tabs' ),
					PNSCRIPTS_PRODUCT_TABS_MIN_WC
				)
			)
		);
	}
}
