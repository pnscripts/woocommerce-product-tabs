<?php
/**
 * Maps YIKES Custom Product Tabs data to this plugin's data.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Import;

use Pnscripts\ProductTabs\Domain\DefaultTabs;
use Pnscripts\ProductTabs\Domain\TabSanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Pure mapping (no database access), verified against YIKES 1.8.6:
 *
 * - Product tabs: post meta "yikes_woo_products_tabs" = list of {title, id, content}; "id" is
 *   urldecode(sanitize_title(title)) and is the WooCommerce tab key. Tabs with an empty title are not
 *   shown; when two tabs share a key the later one replaces the earlier (only the last is shown) and a
 *   key equal to a default tab ("description", "reviews", …) replaces that default tab.
 * - Saved tabs: option "yikes_woo_reusable_products_tabs" = [id => {tab_title, tab_name, tab_content,
 *   tab_id, tab_slug, taxonomies, global_tab}]; taxonomies/global_tab are set by the Pro add-on
 *   (taxonomies = [taxonomy => [term_id => slug]]).
 * - Links: option "yikes_woo_reusable_products_tabs_applied" = [product_id => [saved_id => {post_id,
 *   reusable_tab_id, tab_id}]] where tab_id is the product tab's key. YIKES copies the saved content
 *   into the product meta and rewrites the copies when the saved tab changes.
 *
 * Rule: after an import the storefront shows the same tabs in the same order; tabs YIKES never showed
 * are imported switched off and reported.
 *
 * @phpstan-import-type ProductTab from TabSanitizer
 * @phpstan-type SavedTab array{yikes_id: int, title: string, content: string, scope: string, categories: list<int>, tags: list<int>}
 * @phpstan-type ProductPlan array{tabs: list<ProductTab>, hidden_defaults: list<string>, linked: int, custom: int, disabled_empty: int, disabled_duplicate: int, replaced_defaults: int, warnings: list<string>}
 */
final class YikesMapper {

	public const ORIGIN = 'yikes';

	/**
	 * Slug function (urldecode(sanitize_title()) in WordPress).
	 *
	 * @var callable(string): string
	 */
	private $slugify;

	/**
	 * Constructor.
	 *
	 * @param callable(string): string $slugify Same key function YIKES used.
	 */
	public function __construct( callable $slugify ) {
		$this->slugify = $slugify;
	}

	/**
	 * Saved tabs from the raw option, keyed by YIKES id. Invalid rows are skipped with a warning.
	 *
	 * @param mixed        $raw      Option value.
	 * @param list<string> $warnings Collected warnings.
	 * @return array<int, SavedTab>
	 */
	public function saved_tabs( mixed $raw, array &$warnings ): array {
		$raw = self::maybe_unserialize( $raw );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id    = isset( $row['tab_id'] ) && is_numeric( $row['tab_id'] ) ? (int) $row['tab_id'] : ( is_numeric( $key ) ? (int) $key : 0 );
			$title = self::str( $row['tab_title'] ?? '' );
			if ( $id <= 0 ) {
				continue;
			}
			if ( '' === trim( $title ) ) {
				/* translators: %d: YIKES saved tab id */
				$warnings[] = sprintf( __( 'Saved tab #%d has no title and was skipped.', 'pnscripts-tabcrest' ), $id );
				continue;
			}
			$categories = array();
			$tags       = array();
			$taxonomies = isset( $row['taxonomies'] ) && is_array( $row['taxonomies'] ) ? $row['taxonomies'] : array();
			foreach ( $taxonomies as $taxonomy => $terms ) {
				if ( ! is_array( $terms ) || array() === $terms ) {
					continue;
				}
				if ( 'product_cat' === $taxonomy ) {
					$categories = TabSanitizer::id_list( array_keys( $terms ) );
				} elseif ( 'product_tag' === $taxonomy ) {
					$tags = TabSanitizer::id_list( array_keys( $terms ) );
				} else {
					/* translators: 1: saved tab title, 2: taxonomy name */
					$warnings[] = sprintf( __( 'Saved tab "%1$s" was assigned by the taxonomy "%2$s", which is not supported; it is imported for the categories and tags only.', 'pnscripts-tabcrest' ), $title, (string) $taxonomy );
				}
			}
			$global = TabSanitizer::bool( $row['global_tab'] ?? false );
			if ( $global ) {
				$scope = TabSanitizer::SCOPE_ALL;
			} elseif ( array() !== $categories || array() !== $tags ) {
				$scope = TabSanitizer::SCOPE_TERMS;
			} else {
				$scope = TabSanitizer::SCOPE_MANUAL;
			}
			$out[ $id ] = array(
				'yikes_id'   => $id,
				'title'      => $title,
				'content'    => self::str( $row['tab_content'] ?? '' ),
				'scope'      => $scope,
				'categories' => $categories,
				'tags'       => $tags,
			);
		}
		return $out;
	}

	/**
	 * Map one product's YIKES tabs.
	 *
	 * @param int                  $product_id      Product id.
	 * @param mixed                $raw_tabs        Value of yikes_woo_products_tabs.
	 * @param mixed                $applied         The product's entry of the "applied" option.
	 * @param array<int, SavedTab> $saved           Saved tabs by YIKES id.
	 * @param array<int, int>      $global_ids      YIKES saved tab id => global tab post id (0 in a dry run).
	 * @param bool                 $unfiltered_html Whether content is kept unfiltered.
	 * @return ProductPlan
	 */
	public function product( int $product_id, mixed $raw_tabs, mixed $applied, array $saved, array $global_ids, bool $unfiltered_html ): array {
		$plan     = array(
			'tabs'               => array(),
			'hidden_defaults'    => array(),
			'linked'             => 0,
			'custom'             => 0,
			'disabled_empty'     => 0,
			'disabled_duplicate' => 0,
			'replaced_defaults'  => 0,
			'warnings'           => array(),
		);
		$raw_tabs = self::maybe_unserialize( $raw_tabs );
		if ( ! is_array( $raw_tabs ) ) {
			return $plan;
		}
		$rows = array();
		foreach ( array_values( $raw_tabs ) as $row ) {
			if ( is_array( $row ) ) {
				$rows[] = $row;
			}
		}

		// Which row YIKES actually showed for each key: the last one with a title.
		$keys  = array();
		$shown = array();
		foreach ( $rows as $i => $row ) {
			$title = self::str( $row['title'] ?? '' );
			$key   = self::str( $row['id'] ?? '' );
			if ( '' === $key ) {
				$key = ( $this->slugify )( $title );
			}
			$keys[ $i ] = $key;
			if ( '' !== $title ) {
				$shown[ $key ] = $i;
			}
		}

		// Saved tabs linked to this product, by the key of the product tab.
		$links = array();
		if ( is_array( $applied ) ) {
			foreach ( $applied as $saved_id => $link ) {
				$tab_key = is_array( $link ) ? self::str( $link['tab_id'] ?? '' ) : '';
				$sid     = is_array( $link ) && isset( $link['reusable_tab_id'] ) && is_numeric( $link['reusable_tab_id'] ) ? (int) $link['reusable_tab_id'] : ( is_numeric( $saved_id ) ? (int) $saved_id : 0 );
				if ( '' !== $tab_key && isset( $saved[ $sid ] ) ) {
					$links[ $tab_key ] = $sid;
				}
			}
		}

		foreach ( $rows as $i => $row ) {
			$title   = self::str( $row['title'] ?? '' );
			$content = self::str( $row['content'] ?? '' );
			$key     = $keys[ $i ];
			$enabled = '' !== $title && ( $shown[ $key ] ?? null ) === $i;
			$id      = 'y' . substr( md5( $product_id . '|' . $i . '|' . $title ), 0, 10 );

			if ( '' === $title ) {
				if ( '' === trim( $content ) ) {
					continue;
				}
				++$plan['disabled_empty'];
				/* translators: %d: product id */
				$plan['warnings'][] = sprintf( __( 'Product #%d: a tab without a title (never shown by YIKES) was imported switched off.', 'pnscripts-tabcrest' ), $product_id );
			} elseif ( ! $enabled ) {
				++$plan['disabled_duplicate'];
				/* translators: 1: product id, 2: tab title */
				$plan['warnings'][] = sprintf( __( 'Product #%1$d: the tab "%2$s" was hidden by a later tab with the same title in YIKES; it was imported switched off.', 'pnscripts-tabcrest' ), $product_id, $title );
			}

			if ( $enabled && in_array( $key, DefaultTabs::KEYS, true ) && ! in_array( $key, $plan['hidden_defaults'], true ) ) {
				$plan['hidden_defaults'][] = $key;
				++$plan['replaced_defaults'];
			}

			$sid = $links[ $key ] ?? 0;
			if ( $sid > 0 && ( $global_ids[ $sid ] ?? 0 ) > 0 && $enabled && self::same_content( $saved[ $sid ]['content'], $content ) && $saved[ $sid ]['title'] === $title ) {
				$plan['tabs'][] = array(
					'id'        => 'g' . $sid . 'y' . substr( md5( (string) $product_id ), 0, 6 ),
					'type'      => TabSanitizer::TYPE_GLOBAL,
					'title'     => '',
					'content'   => '',
					'faq'       => array(),
					'schema'    => false,
					'global_id' => $global_ids[ $sid ],
					'enabled'   => true,
					'origin'    => self::ORIGIN . ':' . $sid,
				);
				++$plan['linked'];
				continue;
			}

			$plan['tabs'][] = array(
				'id'        => $id,
				'type'      => TabSanitizer::TYPE_CONTENT,
				'title'     => TabSanitizer::text( $title ),
				'content'   => TabSanitizer::html( $content, $unfiltered_html ),
				'faq'       => array(),
				'schema'    => false,
				'global_id' => 0,
				'enabled'   => $enabled,
				'origin'    => self::ORIGIN,
			);
			++$plan['custom'];
		}
		return $plan;
	}

	/**
	 * Compare saved and copied content ignoring line endings and surrounding whitespace.
	 *
	 * @param string $a Content.
	 * @param string $b Content.
	 */
	public static function same_content( string $a, string $b ): bool {
		$normalise = static fn ( string $s ): string => trim( str_replace( array( "\r\n", "\r" ), "\n", $s ) );
		return $normalise( $a ) === $normalise( $b );
	}

	/**
	 * Undo double serialisation (YIKES data imported with WP All Import is sometimes a serialised string).
	 * Objects are never instantiated.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function maybe_unserialize( mixed $value ): mixed {
		if ( is_string( $value ) ) {
			$trimmed = trim( $value );
			if ( 1 === preg_match( '/^a:\d+:\{/', $trimmed ) ) {
				// Same as YIKES (yikes_custom_tabs_maybe_unserialize); objects are never instantiated.
				$data = @unserialize( $trimmed, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged
				return false === $data ? null : $data;
			}
		}
		return $value;
	}

	/**
	 * Scalar to string.
	 *
	 * @param mixed $value Value.
	 */
	private static function str( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
