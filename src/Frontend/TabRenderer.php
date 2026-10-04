<?php
/**
 * Adds the custom tabs to WooCommerce's product tabs and renders them.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Frontend;

use Pnscripts\ProductTabs\Domain\DefaultTabs;
use Pnscripts\ProductTabs\Domain\FaqSchema;
use Pnscripts\ProductTabs\Domain\TabResolver;
use Pnscripts\ProductTabs\Domain\TabSanitizer;
use Pnscripts\ProductTabs\Settings;
use Pnscripts\ProductTabs\Storage\GlobalTabs;
use Pnscripts\ProductTabs\Storage\ProductTabs;

defined( 'ABSPATH' ) || exit;

/**
 * Classic templates, the legacy Product Details block and WooCommerce's compatibility layer all read the
 * woocommerce_product_tabs filter; this class feeds it. Per product page it reads the product's own
 * (primed) meta, the cached term hierarchy and one cached option: no query per tab.
 *
 * @phpstan-import-type ResolvedTab from TabSanitizer
 * @phpstan-import-type GlobalTab from TabSanitizer
 * @phpstan-import-type FaqItem from TabSanitizer
 * @phpstan-import-type Context from TabResolver
 */
final class TabRenderer {

	public const TAB_DATA_KEY = 'pnscripts_product_tabs';
	public const STYLE        = 'pnscripts-product-tabs';

	/**
	 * Resolved tabs per product id (the filter runs several times per page).
	 *
	 * @var array<int, list<ResolvedTab>>
	 */
	private array $cache = array();

	/**
	 * FAQ entries shown for the main product (for the FAQPage schema).
	 *
	 * @var array<string, FaqItem>
	 */
	private array $schema_items = array();

	/**
	 * While true, the tabs are rendered by the accordion Product Details block integration.
	 *
	 * @var bool
	 */
	private bool $accordion_mode = false;

	/**
	 * Content render depth (guards against a tab that renders the tabs again).
	 *
	 * @var int
	 */
	private int $depth = 0;

	/**
	 * Constructor.
	 *
	 * @param Settings    $settings     Settings.
	 * @param GlobalTabs  $globals      Global tabs.
	 * @param ProductTabs $product_tabs Product tabs.
	 * @param TabResolver $resolver     Resolver.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly GlobalTabs $globals,
		private readonly ProductTabs $product_tabs,
		private readonly TabResolver $resolver
	) {}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_tabs', array( $this, 'filter_tabs' ), 98 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_style' ) );
		add_action( 'wp_footer', array( $this, 'print_schema' ), 20 );
	}

	/**
	 * Register the small stylesheet (enqueued only when a FAQ tab is rendered).
	 */
	public function register_style(): void {
		wp_register_style( self::STYLE, PNSCRIPTS_PRODUCT_TABS_URL . 'assets/css/frontend.css', array(), PNSCRIPTS_PRODUCT_TABS_VERSION );
	}

	/**
	 * Switch the accordion integration on or off.
	 *
	 * @param bool $on Whether the accordion Product Details block renders the tabs.
	 */
	public function set_accordion_mode( bool $on ): void {
		$this->accordion_mode = $on;
	}

	/**
	 * Whether the accordion integration is active.
	 */
	public function in_accordion_mode(): bool {
		return $this->accordion_mode;
	}

	/**
	 * Filter callback for woocommerce_product_tabs.
	 *
	 * @param mixed $tabs Tabs from WooCommerce and other plugins.
	 * @return array<array-key, mixed>
	 */
	public function filter_tabs( mixed $tabs ): array {
		$tabs    = is_array( $tabs ) ? $tabs : array();
		$product = $this->current_product();
		if ( null === $product ) {
			return $tabs;
		}
		$product_id = $product->get_id();
		$settings   = $this->settings->all();

		$tabs = $this->strip_imported_yikes_tabs( $tabs, $product_id );

		if ( ! $this->accordion_mode ) {
			foreach ( $this->resolved( $product_id, array_map( 'strval', array_keys( $tabs ) ) ) as $tab ) {
				$tabs[ $tab['key'] ] = array(
					'title'            => $tab['title'],
					'priority'         => $tab['priority'],
					'callback'         => array( $this, 'render_tab' ),
					self::TAB_DATA_KEY => $tab,
				);
			}
		}

		return DefaultTabs::apply(
			$tabs,
			$settings['defaults'],
			$this->product_tabs->hidden_defaults( $product_id ),
			$settings['reviews_last'],
			(int) $product->get_review_count()
		);
	}

	/**
	 * Resolved custom tabs of a product (cached per request).
	 *
	 * @param int          $product_id Product id.
	 * @param list<string> $taken_keys Keys used by other tabs.
	 * @return list<ResolvedTab>
	 */
	public function resolved( int $product_id, array $taken_keys = array() ): array {
		if ( isset( $this->cache[ $product_id ] ) ) {
			return $this->cache[ $product_id ];
		}
		$globals = $this->globals->all();
		$context = $this->context( $product_id, $globals );

		$applies = has_filter( 'pnscripts_product_tabs_global_tab_applies' )
			? static function ( bool $applies, array $global_tab, array $context ): bool {
				/**
				 * Filters whether a global tab is shown on a product (advanced conditions, schedules).
				 *
				 * @param bool  $applies Result of the built-in rule (all products / categories / tags).
				 * @param array $global_tab Global tab, including add-on data under "extra".
				 * @param array $context Product id, category ids (with ancestors) and tag ids.
				 */
				return (bool) apply_filters( 'pnscripts_product_tabs_global_tab_applies', $applies, $global_tab, $context );
			}
			: null;

		$resolved = $this->resolver->resolve(
			$context,
			$this->product_tabs->tabs( $product_id ),
			$this->product_tabs->hidden_globals( $product_id ),
			$globals,
			$this->settings->all()['custom_priority'],
			$taken_keys,
			$applies
		);

		/**
		 * Filters the custom tabs of a product after the rules ran.
		 *
		 * @param array $resolved   Tabs: key, title, priority, type, content, faq, schema, global_id, source.
		 * @param int   $product_id Product id.
		 */
		$filtered = apply_filters( 'pnscripts_product_tabs_resolved', $resolved, $product_id );
		$resolved = is_array( $filtered ) ? array_values( array_filter( $filtered, array( self::class, 'is_resolved_tab' ) ) ) : $resolved;

		$this->cache[ $product_id ] = $resolved;
		$this->collect_schema( $product_id, $resolved );
		return $resolved;
	}

	/**
	 * Tab callback (classic templates and the legacy block): WooCommerce passes the tab array.
	 *
	 * @param int|string           $key Tab key (numeric in the block compatibility layer).
	 * @param array<string, mixed> $tab Tab array.
	 */
	public function render_tab( $key, $tab ): void {
		unset( $key );
		if ( ! is_array( $tab ) || ! isset( $tab[ self::TAB_DATA_KEY ] ) || ! self::is_resolved_tab( $tab[ self::TAB_DATA_KEY ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in panel_html().
		echo $this->panel_html( $tab[ self::TAB_DATA_KEY ], ! $this->accordion_mode );
	}

	/**
	 * HTML of one tab panel.
	 *
	 * @param ResolvedTab $tab          Tab.
	 * @param bool        $with_heading Print the tab title as a heading (not inside an accordion).
	 */
	public function panel_html( array $tab, bool $with_heading ): string {
		$html = '';
		if ( $with_heading && $this->settings->all()['show_heading'] ) {
			/**
			 * Filters the heading printed above a custom tab's content ('' removes it).
			 *
			 * @param string $title Heading text.
			 * @param array  $tab   Tab.
			 */
			$heading = (string) apply_filters( 'pnscripts_product_tabs_heading', $tab['title'], $tab );
			if ( '' !== $heading ) {
				$html .= '<h2 class="pnscripts-product-tabs__heading">' . esc_html( $heading ) . '</h2>';
			}
		}
		if ( TabSanitizer::TYPE_FAQ === $tab['type'] ) {
			$html .= $this->faq_html( $tab['faq'] );
		} else {
			$html .= $this->content_html( $tab['content'], $tab );
		}
		return '<div class="pnscripts-product-tabs pnscripts-product-tabs--' . esc_attr( $tab['type'] ) . '">' . $html . '</div>';
	}

	/**
	 * Render rich content like post content (blocks, embeds, shortcodes, autop) without the_content.
	 * Other plugins' the_content additions (share buttons, related posts) would otherwise appear inside
	 * every tab; the pnscripts_product_tabs_use_the_content filter switches to the_content.
	 *
	 * The content is trusted like post content: only users who can edit products write it, and it was
	 * filtered with wp_kses_post() on save unless the author has the unfiltered_html capability.
	 *
	 * @param string               $content Content.
	 * @param array<string, mixed> $tab     Tab (context for filters).
	 */
	public function content_html( string $content, array $tab ): string {
		if ( $this->depth > 1 ) {
			return '';
		}
		++$this->depth;
		if ( apply_filters( 'pnscripts_product_tabs_use_the_content', false, $tab ) ) {
			$html = (string) apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
		} else {
			$html       = $content;
			$has_blocks = has_blocks( $content );
			if ( isset( $GLOBALS['wp_embed'] ) && $GLOBALS['wp_embed'] instanceof \WP_Embed ) {
				$html = $GLOBALS['wp_embed']->run_shortcode( $html );
				$html = $GLOBALS['wp_embed']->autoembed( $html );
			}
			if ( $has_blocks ) {
				$html = do_blocks( $html );
			}
			$html = wptexturize( $html );
			if ( ! $has_blocks ) {
				$html = wpautop( $html );
			}
			$html = shortcode_unautop( $html );
			$html = do_shortcode( $html );
			$html = wp_filter_content_tags( $html, 'pnscripts_product_tabs' );
			$html = convert_smilies( $html );
		}
		--$this->depth;

		/**
		 * Filters the rendered content of a custom tab.
		 *
		 * @param string $html Rendered HTML.
		 * @param array  $tab  Tab.
		 */
		return (string) apply_filters( 'pnscripts_product_tabs_content', $html, $tab );
	}

	/**
	 * Accessible FAQ list (native details/summary, no JavaScript).
	 *
	 * @param list<FaqItem> $items Items.
	 */
	public function faq_html( array $items ): string {
		if ( array() === $items ) {
			return '';
		}
		wp_enqueue_style( self::STYLE );
		$html = '<div class="pnscripts-product-tabs-faq">';
		foreach ( $items as $item ) {
			$html .= '<details class="pnscripts-product-tabs-faq__item">'
				. '<summary class="pnscripts-product-tabs-faq__question">' . esc_html( $item['q'] ) . '</summary>'
				. '<div class="pnscripts-product-tabs-faq__answer">' . wp_kses_post( wpautop( $item['a'] ) ) . '</div>'
				. '</details>';
		}
		return $html . '</div>';
	}

	/**
	 * Print one FAQPage JSON-LD block for the FAQ tabs shown on the current product.
	 */
	public function print_schema(): void {
		if ( ! $this->settings->all()['faq_schema'] || array() === $this->schema_items ) {
			return;
		}
		$data = FaqSchema::build( array_values( $this->schema_items ), 'wp_strip_all_tags' );

		/**
		 * Filters the FAQPage structured data (return null to print nothing, e.g. when an SEO plugin
		 * already outputs FAQ markup).
		 *
		 * @param array|null $data  JSON-LD array.
		 * @param array      $items Question/answer pairs.
		 */
		$data = apply_filters( 'pnscripts_product_tabs_faq_schema', $data, array_values( $this->schema_items ) );
		if ( ! is_array( $data ) ) {
			return;
		}
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( false === $json ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG, cannot close the script element.
		echo "<script type=\"application/ld+json\" class=\"pnscripts-product-tabs-schema\">{$json}</script>\n";
	}

	/**
	 * FAQ entries collected for the schema (tests and add-ons).
	 *
	 * @return list<FaqItem>
	 */
	public function schema_items(): array {
		return array_values( $this->schema_items );
	}

	/**
	 * Forget per-request caches (tests, long-running CLI).
	 */
	public function reset(): void {
		$this->cache        = array();
		$this->schema_items = array();
	}

	/**
	 * Collect FAQ items of the main product of a single product page.
	 *
	 * @param int               $product_id Product id.
	 * @param list<ResolvedTab> $tabs       Tabs.
	 */
	private function collect_schema( int $product_id, array $tabs ): void {
		if ( ! is_singular( 'product' ) || get_queried_object_id() !== $product_id ) {
			return;
		}
		foreach ( $tabs as $tab ) {
			if ( TabSanitizer::TYPE_FAQ !== $tab['type'] || ! $tab['schema'] ) {
				continue;
			}
			foreach ( $tab['faq'] as $item ) {
				$this->schema_items[ $item['q'] ] = $item;
			}
		}
	}

	/**
	 * Remove YIKES' copies of tabs that were imported, so nothing shows twice while both plugins run.
	 *
	 * @param array<array-key, mixed> $tabs       Tabs.
	 * @param int                     $product_id Product id.
	 * @return array<array-key, mixed>
	 */
	private function strip_imported_yikes_tabs( array $tabs, int $product_id ): array {
		if ( ! $this->settings->all()['hide_yikes'] || ! class_exists( 'YIKES_Custom_Product_Tabs_Display', false ) ) {
			return $tabs;
		}
		if ( '' === (string) get_post_meta( $product_id, ProductTabs::META_YIKES_HASH, true ) ) {
			return $tabs;
		}
		foreach ( $tabs as $key => $tab ) {
			$callback = is_array( $tab ) ? ( $tab['callback'] ?? null ) : null;
			if ( is_array( $callback ) && isset( $callback[0] ) && $callback[0] instanceof \YIKES_Custom_Product_Tabs_Display ) {
				unset( $tabs[ $key ] );
			}
		}
		return $tabs;
	}

	/**
	 * Product context for the rules: category ids with all ancestors (from the cached hierarchy) and tag ids.
	 *
	 * @param int                   $product_id Product id.
	 * @param array<int, GlobalTab> $globals    Global tabs (terms are only looked up when a rule needs them).
	 * @return Context
	 */
	private function context( int $product_id, array $globals ): array {
		$context     = array(
			'product_id' => $product_id,
			'categories' => array(),
			'tags'       => array(),
		);
		$needs_terms = has_filter( 'pnscripts_product_tabs_global_tab_applies' );
		foreach ( $globals as $global_tab ) {
			if ( TabSanitizer::SCOPE_TERMS === $global_tab['scope'] ) {
				$needs_terms = true;
				break;
			}
		}
		if ( ! $needs_terms ) {
			return $context;
		}
		$categories            = array_map( 'intval', wc_get_product_term_ids( $product_id, 'product_cat' ) );
		$context['categories'] = self::with_ancestors( $categories, 'product_cat' );
		$context['tags']       = array_values( array_map( 'intval', wc_get_product_term_ids( $product_id, 'product_tag' ) ) );
		return $context;
	}

	/**
	 * Add ancestor term ids using the cached parent → children map (no per-term queries).
	 *
	 * @param array<int> $term_ids Term ids.
	 * @param string     $taxonomy Taxonomy.
	 * @return list<int>
	 */
	public static function with_ancestors( array $term_ids, string $taxonomy ): array {
		$children = _get_term_hierarchy( $taxonomy );
		$parents  = array();
		foreach ( $children as $parent => $kids ) {
			foreach ( (array) $kids as $kid ) {
				$parents[ (int) $kid ] = (int) $parent;
			}
		}
		$all = array();
		foreach ( $term_ids as $id ) {
			$guard = 0;
			while ( $id > 0 && ! isset( $all[ $id ] ) && $guard < 50 ) {
				$all[ $id ] = true;
				$id         = $parents[ $id ] ?? 0;
				++$guard;
			}
		}
		return array_map( 'intval', array_keys( $all ) );
	}

	/**
	 * Current product (single product page, Product Details block, or the product in the loop).
	 */
	private function current_product(): ?\WC_Product {
		global $product;
		if ( $product instanceof \WC_Product ) {
			return $product;
		}
		$id = get_the_ID();
		if ( false === $id ) {
			return null;
		}
		$found = wc_get_product( $id );
		return $found instanceof \WC_Product ? $found : null;
	}

	/**
	 * Shape check for tabs coming back from filters.
	 *
	 * @param mixed $tab Candidate.
	 * @phpstan-assert-if-true ResolvedTab $tab
	 */
	public static function is_resolved_tab( mixed $tab ): bool {
		return is_array( $tab )
			&& isset( $tab['key'], $tab['title'], $tab['priority'], $tab['type'], $tab['content'], $tab['faq'], $tab['schema'], $tab['global_id'], $tab['source'] )
			&& is_string( $tab['key'] ) && is_string( $tab['title'] ) && is_int( $tab['priority'] ) && is_string( $tab['type'] )
			&& is_string( $tab['content'] ) && is_array( $tab['faq'] ) && is_bool( $tab['schema'] ) && is_int( $tab['global_id'] ) && is_string( $tab['source'] );
	}
}
