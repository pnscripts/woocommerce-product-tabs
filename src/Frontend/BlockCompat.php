<?php
/**
 * Block theme support for the accordion version of the Product Details block.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Frontend;

use Pnscripts\ProductTabs\Domain\DefaultTabs;
use Pnscripts\ProductTabs\Settings;
use Pnscripts\ProductTabs\Storage\ProductTabs;

defined( 'ABSPATH' ) || exit;

/**
 * Since WooCommerce 10 the Product Details block can hold an accordion whose Description, Additional
 * information and Reviews items are inner blocks; third-party tabs are appended at the end and the
 * woocommerce_product_tabs title/priority changes are ignored for the default items.
 *
 * This class rewrites the block's parsed tree before WooCommerce renders it: default items are renamed,
 * hidden and ordered by the same settings as classic tabs, and the custom tabs are inserted as accordion
 * items at their priority. While it runs, TabRenderer does not add the custom tabs again.
 * Classic templates and the legacy (tabbed) Product Details block need none of this.
 */
final class BlockCompat {

	private const GROUPS = array( 'core/accordion', 'woocommerce/accordion-group' );
	private const ITEMS  = array( 'core/accordion-item', 'woocommerce/accordion-item' );

	/**
	 * Content blocks that identify WooCommerce's default items.
	 */
	private const DEFAULT_BLOCKS = array(
		'woocommerce/product-description'        => 'description',
		'core/post-content'                      => 'description',
		'woocommerce/product-specifications'     => 'additional_information',
		'woocommerce/product-reviews'            => 'reviews',
		'woocommerce/blockified-product-reviews' => 'reviews',
	);

	/**
	 * Depth of Product Details blocks being rendered by us.
	 *
	 * @var int
	 */
	private int $active = 0;

	/**
	 * Constructor.
	 *
	 * @param Settings    $settings     Settings.
	 * @param ProductTabs $product_tabs Product tabs.
	 * @param TabRenderer $renderer     Renderer.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly ProductTabs $product_tabs,
		private readonly TabRenderer $renderer
	) {}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'render_block_data', array( $this, 'prepare' ), 10, 1 );
		add_filter( 'render_block_woocommerce/product-details', array( $this, 'finish' ), 10, 1 );
	}

	/**
	 * Rewrite an accordion Product Details block before it renders.
	 *
	 * @param mixed $block Parsed block.
	 * @return mixed
	 */
	public function prepare( mixed $block ): mixed {
		if ( ! is_array( $block ) || 'woocommerce/product-details' !== ( $block['blockName'] ?? null ) || empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return $block;
		}
		if ( apply_filters( 'woocommerce_disable_compatibility_layer', false ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce filter.
			return $block;
		}
		$product = wc_get_product();
		if ( ! $product instanceof \WC_Product ) {
			return $block;
		}
		/**
		 * Whether to integrate with the accordion Product Details block (false keeps WooCommerce's behaviour).
		 *
		 * @param bool $enabled Enabled.
		 */
		if ( ! apply_filters( 'pnscripts_product_tabs_accordion_integration', true ) ) {
			return $block;
		}
		$rewritten = $this->rewrite( $block, $product );
		if ( null === $rewritten ) {
			return $block;
		}
		++$this->active;
		$this->renderer->set_accordion_mode( true );
		return $rewritten;
	}

	/**
	 * Leave accordion mode after the block rendered.
	 *
	 * @param mixed $content Block HTML.
	 * @return mixed
	 */
	public function finish( mixed $content ): mixed {
		if ( $this->active > 0 ) {
			--$this->active;
			$this->renderer->set_accordion_mode( $this->active > 0 );
		}
		return $content;
	}

	/**
	 * Rewrite the first accordion inside the block; null when there is no accordion.
	 *
	 * @param array<string, mixed> $block   Product Details block.
	 * @param \WC_Product          $product Product.
	 * @return array<string, mixed>|null
	 */
	public function rewrite( array $block, \WC_Product $product ): ?array {
		$done = false;
		$out  = $this->walk( $block, $product, $done );
		return $done ? $out : null;
	}

	/**
	 * Depth-first search for the accordion group.
	 *
	 * @param array<string, mixed> $block   Block.
	 * @param \WC_Product          $product Product.
	 * @param bool                 $done    Set when the group was rewritten.
	 * @return array<string, mixed>
	 */
	private function walk( array $block, \WC_Product $product, bool &$done ): array {
		if ( in_array( $block['blockName'] ?? null, self::GROUPS, true ) ) {
			$done = true;
			return $this->rewrite_group( $block, $product );
		}
		if ( ! isset( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return $block;
		}
		foreach ( $block['innerBlocks'] as $i => $inner ) {
			if ( $done ) {
				break;
			}
			if ( is_array( $inner ) ) {
				$block['innerBlocks'][ $i ] = $this->walk( $inner, $product, $done );
			}
		}
		return $block;
	}

	/**
	 * Rename/hide/order default items and insert the custom tabs.
	 *
	 * @param array<string, mixed> $group   Accordion group block.
	 * @param \WC_Product          $product Product.
	 * @return array<string, mixed>
	 */
	private function rewrite_group( array $group, \WC_Product $product ): array {
		$settings   = $this->settings->all();
		$product_id = $product->get_id();
		$hidden     = $this->product_tabs->hidden_defaults( $product_id );
		$items      = array();
		$last       = 0;
		$order      = 0;
		$reviews_at = null;
		$inner      = is_array( $group['innerBlocks'] ?? null ) ? $group['innerBlocks'] : array();

		foreach ( $inner as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$key = in_array( $item['blockName'] ?? null, self::ITEMS, true ) ? self::default_key( $item ) : null;
			if ( null !== $key ) {
				$setting = $settings['defaults'][ $key ];
				if ( ! $setting['enabled'] || in_array( $key, $hidden, true ) ) {
					continue;
				}
				if ( '' !== $setting['title'] ) {
					$item = self::rename( $item, DefaultTabs::title( $setting['title'], (int) $product->get_review_count(), 'reviews' === $key ) );
				}
				$last = $setting['priority'];
			}
			$items[] = array(
				'priority' => $last,
				'order'    => $order++,
				'block'    => $item,
			);
			if ( 'reviews' === $key ) {
				$reviews_at = count( $items ) - 1;
			}
		}

		$custom = $this->renderer->resolved( $product_id, DefaultTabs::KEYS );
		foreach ( $custom as $tab ) {
			$items[] = array(
				'priority' => $tab['priority'],
				'order'    => $order++,
				'block'    => self::item_block( $group, $tab['title'], $this->renderer->panel_html( $tab, false ) ),
			);
		}

		if ( $settings['reviews_last'] && null !== $reviews_at ) {
			$max = 0;
			foreach ( $items as $i => $row ) {
				if ( $i !== $reviews_at ) {
					$max = max( $max, $row['priority'] );
				}
			}
			$row                  = $items[ $reviews_at ];
			$row['priority']      = max( $row['priority'], $max + 1 );
			$items[ $reviews_at ] = $row;
		}

		usort(
			$items,
			static fn ( array $a, array $b ): int => array( $a['priority'], $a['order'] ) <=> array( $b['priority'], $b['order'] )
		);

		$blocks                = array_column( $items, 'block' );
		$content               = is_array( $group['innerContent'] ?? null ) ? $group['innerContent'] : array();
		$opening               = is_string( reset( $content ) ) ? (string) reset( $content ) : '';
		$closing               = is_string( end( $content ) ) && count( $content ) > 1 ? (string) end( $content ) : '';
		$group['innerBlocks']  = $blocks;
		$group['innerContent'] = array_merge( array( $opening ), array_fill( 0, count( $blocks ), null ), array( $closing ) );
		return $group;
	}

	/**
	 * Which default tab an accordion item holds (by the content block inside it).
	 *
	 * @param array<string, mixed> $block Block.
	 */
	public static function default_key( array $block ): ?string {
		$name = $block['blockName'] ?? null;
		if ( is_string( $name ) && isset( self::DEFAULT_BLOCKS[ $name ] ) ) {
			return self::DEFAULT_BLOCKS[ $name ];
		}
		foreach ( is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array() as $inner ) {
			if ( is_array( $inner ) ) {
				$key = self::default_key( $inner );
				if ( null !== $key ) {
					return $key;
				}
			}
		}
		return null;
	}

	/**
	 * Replace the visible title of an accordion item.
	 *
	 * @param array<string, mixed> $item  Accordion item.
	 * @param string               $title New title.
	 * @return array<string, mixed>
	 */
	public static function rename( array $item, string $title ): array {
		if ( ! is_array( $item['innerBlocks'] ?? null ) ) {
			return $item;
		}
		foreach ( $item['innerBlocks'] as $i => $inner ) {
			if ( ! is_array( $inner ) || ! in_array( $inner['blockName'] ?? null, array( 'core/accordion-heading', 'woocommerce/accordion-header' ), true ) ) {
				continue;
			}
			$old = is_string( $inner['attrs']['title'] ?? null ) ? $inner['attrs']['title'] : self::heading_text( is_string( $inner['innerHTML'] ?? null ) ? $inner['innerHTML'] : '' );
			if ( isset( $inner['attrs'] ) && is_array( $inner['attrs'] ) && isset( $inner['attrs']['title'] ) ) {
				$inner['attrs']['title'] = $title;
			}
			if ( '' !== $old ) {
				$inner['innerHTML'] = self::replace_text( is_string( $inner['innerHTML'] ?? null ) ? $inner['innerHTML'] : '', $old, $title );
				if ( is_array( $inner['innerContent'] ?? null ) ) {
					foreach ( $inner['innerContent'] as $c => $chunk ) {
						if ( is_string( $chunk ) ) {
							$inner['innerContent'][ $c ] = self::replace_text( $chunk, $old, $title );
						}
					}
				}
			}
			$item['innerBlocks'][ $i ] = $inner;
			break;
		}
		return $item;
	}

	/**
	 * Visible title inside an accordion heading's saved HTML (the toggle icon is not part of it).
	 *
	 * @param string $html Heading HTML.
	 */
	public static function heading_text( string $html ): string {
		if ( 1 === preg_match( '/class="[^"]*toggle-title[^"]*"[^>]*>([^<]*)</', $html, $m ) || 1 === preg_match( '/<span[^>]*>([^<]+)<\/span>/', $html, $m ) ) {
			return trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
		}
		return trim( wp_strip_all_tags( $html ) );
	}

	/**
	 * Replace the text node equal to $old (first occurrence) with $new, escaped.
	 *
	 * @param string $html        HTML.
	 * @param string $search      Old text.
	 * @param string $replacement New text.
	 */
	private static function replace_text( string $html, string $search, string $replacement ): string {
		$pattern = '/>(\s*)' . preg_quote( esc_html( $search ), '/' ) . '(\s*)</u';
		$result  = preg_replace_callback(
			$pattern,
			static fn ( array $m ): string => '>' . $m[1] . esc_html( $replacement ) . $m[2] . '<',
			$html,
			1
		);
		return is_string( $result ) ? $result : $html;
	}

	/**
	 * Accordion item block (same markup WooCommerce uses for third-party tabs) for the group type.
	 *
	 * @param array<string, mixed> $group   Accordion group.
	 * @param string               $title   Title.
	 * @param string               $content Panel HTML (already escaped/rendered).
	 * @return array<string, mixed>
	 */
	public static function item_block( array $group, string $title, string $content ): array {
		$title = esc_html( $title );
		if ( 'core/accordion' === ( $group['blockName'] ?? null ) ) {
			$markup = '<!-- wp:accordion-item --><div class="wp-block-accordion-item">'
				. '<!-- wp:accordion-heading --><h3 class="wp-block-accordion-heading"><button class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">' . $title . '</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3><!-- /wp:accordion-heading -->'
				. '<!-- wp:accordion-panel --><div class="wp-block-accordion-panel"><!-- wp:html -->' . $content . '<!-- /wp:html --></div><!-- /wp:accordion-panel -->'
				. '</div><!-- /wp:accordion-item -->';
		} else {
			$markup = '<!-- wp:woocommerce/accordion-item --><div class="wp-block-woocommerce-accordion-item">'
				. '<!-- wp:woocommerce/accordion-header --><h3 class="wp-block-woocommerce-accordion-header accordion-item__heading"><button class="accordion-item__toggle"><span>' . $title . '</span><span class="accordion-item__toggle-icon has-icon-plus" style="width:1.2em;height:1.2em"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M11 12.5V17.5H12.5V12.5H17.5V11H12.5V6H11V11H6V12.5H11Z" fill="currentColor"></path></svg></span></button></h3><!-- /wp:woocommerce/accordion-header -->'
				. '<!-- wp:woocommerce/accordion-panel --><div class="wp-block-woocommerce-accordion-panel"><div class="accordion-content__wrapper"><!-- wp:html -->' . $content . '<!-- /wp:html --></div></div><!-- /wp:woocommerce/accordion-panel -->'
				. '</div><!-- /wp:woocommerce/accordion-item -->';
		}
		$parsed = parse_blocks( $markup );
		return is_array( $parsed[0] ?? null ) ? $parsed[0] : array();
	}
}
