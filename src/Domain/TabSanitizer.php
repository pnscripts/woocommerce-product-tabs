<?php
/**
 * Normalises tab data coming from forms, imports and storage.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Every tab array that is stored or rendered passes through here, so the shapes below are the contract.
 *
 * @phpstan-type FaqItem array{q: string, a: string}
 * @phpstan-type ProductTab array{id: string, type: string, title: string, content: string, faq: list<FaqItem>, schema: bool, global_id: int, enabled: bool, origin: string}
 * @phpstan-type GlobalTab array{id: int, title: string, slug: string, content: string, type: string, faq: list<FaqItem>, schema: bool, scope: string, categories: list<int>, tags: list<int>, priority: int, extra: array<array-key, mixed>}
 * @phpstan-type ResolvedTab array{key: string, title: string, priority: int, type: string, content: string, faq: list<FaqItem>, schema: bool, global_id: int, source: string}
 */
final class TabSanitizer {

	public const TYPE_CONTENT = 'content';
	public const TYPE_FAQ     = 'faq';
	public const TYPE_GLOBAL  = 'global';

	public const SCOPE_ALL    = 'all';
	public const SCOPE_TERMS  = 'terms';
	public const SCOPE_MANUAL = 'manual';

	/**
	 * Maximum tabs kept per product (protects the product page and the meta row from runaway input).
	 */
	public const MAX_TABS = 50;

	/**
	 * Maximum FAQ entries per tab.
	 */
	public const MAX_FAQ = 100;

	/**
	 * Normalise a list of product tabs.
	 *
	 * @param mixed $raw            Untrusted list (already unslashed).
	 * @param bool  $unfiltered_html Whether the current user may store unfiltered HTML.
	 * @return list<ProductTab>
	 */
	public static function product_tabs( mixed $raw, bool $unfiltered_html ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$tabs = array();
		$ids  = array();
		foreach ( $raw as $item ) {
			if ( count( $tabs ) >= self::MAX_TABS ) {
				break;
			}
			$tab = self::product_tab( $item, $unfiltered_html );
			if ( null === $tab ) {
				continue;
			}
			if ( isset( $ids[ $tab['id'] ] ) ) {
				$tab['id'] = self::new_id( $tab['id'] . count( $tabs ) );
			}
			$ids[ $tab['id'] ] = true;
			$tabs[]            = $tab;
		}
		return $tabs;
	}

	/**
	 * Normalise one product tab. Returns null for rows that carry nothing (no title, content or link).
	 *
	 * @param mixed $item            Untrusted row.
	 * @param bool  $unfiltered_html Whether raw HTML is allowed.
	 * @return ProductTab|null
	 */
	public static function product_tab( mixed $item, bool $unfiltered_html ): ?array {
		if ( ! is_array( $item ) ) {
			return null;
		}
		$type = self::string( $item['type'] ?? self::TYPE_CONTENT );
		if ( ! in_array( $type, array( self::TYPE_CONTENT, self::TYPE_FAQ, self::TYPE_GLOBAL ), true ) ) {
			$type = self::TYPE_CONTENT;
		}
		$global_id = self::TYPE_GLOBAL === $type ? self::absint( $item['global_id'] ?? 0 ) : 0;
		$title     = self::TYPE_GLOBAL === $type ? '' : self::text( $item['title'] ?? '' );
		$content   = self::TYPE_CONTENT === $type ? self::html( $item['content'] ?? '', $unfiltered_html ) : '';
		$faq       = self::TYPE_FAQ === $type ? self::faq_items( $item['faq'] ?? array(), $unfiltered_html ) : array();

		if ( self::TYPE_GLOBAL === $type && 0 === $global_id ) {
			return null;
		}
		if ( self::TYPE_GLOBAL !== $type && '' === $title && '' === trim( $content ) && array() === $faq ) {
			return null;
		}

		$id = self::string( $item['id'] ?? '' );
		if ( 1 !== preg_match( '/^[a-z0-9_-]{1,40}$/', $id ) ) {
			$id = self::TYPE_GLOBAL === $type ? 'g' . $global_id : self::new_id( $title . $content . wp_json_encode( $faq ) );
		}

		$origin = self::string( $item['origin'] ?? '' );
		if ( 1 !== preg_match( '/^[a-z0-9_:.-]{0,64}$/', $origin ) ) {
			$origin = '';
		}

		return array(
			'id'        => $id,
			'type'      => $type,
			'title'     => $title,
			'content'   => $content,
			'faq'       => $faq,
			'schema'    => self::TYPE_FAQ === $type ? self::bool( $item['schema'] ?? true ) : false,
			'global_id' => $global_id,
			'enabled'   => self::bool( $item['enabled'] ?? true ),
			'origin'    => $origin,
		);
	}

	/**
	 * Normalise FAQ entries; entries without a question are dropped.
	 *
	 * @param mixed $raw             Untrusted list of {q, a}.
	 * @param bool  $unfiltered_html Whether raw HTML is allowed in answers.
	 * @return list<FaqItem>
	 */
	public static function faq_items( mixed $raw, bool $unfiltered_html ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$items = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || count( $items ) >= self::MAX_FAQ ) {
				continue;
			}
			$question = self::text( $row['q'] ?? '' );
			if ( '' === $question ) {
				continue;
			}
			$items[] = array(
				'q' => $question,
				'a' => self::html( $row['a'] ?? '', $unfiltered_html ),
			);
		}
		return $items;
	}

	/**
	 * Normalise a list of positive integers (term or post ids), unique and in input order.
	 *
	 * @param mixed $raw Untrusted list.
	 * @return list<int>
	 */
	public static function id_list( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$ids = array();
		foreach ( $raw as $value ) {
			$id = self::absint( $value );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Allowed scope value.
	 *
	 * @param mixed $raw Untrusted value.
	 */
	public static function scope( mixed $raw ): string {
		$scope = self::string( $raw );
		return in_array( $scope, array( self::SCOPE_ALL, self::SCOPE_TERMS, self::SCOPE_MANUAL ), true ) ? $scope : self::SCOPE_MANUAL;
	}

	/**
	 * Allowed type value for a global tab.
	 *
	 * @param mixed $raw Untrusted value.
	 */
	public static function global_type( mixed $raw ): string {
		return self::TYPE_FAQ === self::string( $raw ) ? self::TYPE_FAQ : self::TYPE_CONTENT;
	}

	/**
	 * Priority in the range WooCommerce themes expect.
	 *
	 * @param mixed $raw     Untrusted value.
	 * @param int   $fallback Default.
	 */
	public static function priority( mixed $raw, int $fallback ): int {
		if ( is_int( $raw ) || ( is_string( $raw ) && is_numeric( $raw ) ) || is_float( $raw ) ) {
			return max( 0, min( 999, (int) $raw ) );
		}
		return $fallback;
	}

	/**
	 * Plain single-line text.
	 *
	 * @param mixed $raw Untrusted value.
	 */
	public static function text( mixed $raw ): string {
		return sanitize_text_field( self::string( $raw ) );
	}

	/**
	 * Rich text: kept as is for users with unfiltered_html (same rule as post content), otherwise kses'd.
	 *
	 * @param mixed $raw             Untrusted value.
	 * @param bool  $unfiltered_html Whether raw HTML is allowed.
	 */
	public static function html( mixed $raw, bool $unfiltered_html ): string {
		$html = self::string( $raw );
		return $unfiltered_html ? $html : wp_kses_post( $html );
	}

	/**
	 * Boolean from checkbox-like input.
	 *
	 * @param mixed $raw Untrusted value.
	 */
	public static function bool( mixed $raw ): bool {
		if ( is_bool( $raw ) ) {
			return $raw;
		}
		if ( is_int( $raw ) ) {
			return 0 !== $raw;
		}
		return in_array( self::string( $raw ), array( '1', 'yes', 'true', 'on' ), true );
	}

	/**
	 * Short deterministic id for a new tab.
	 *
	 * @param string $seed Seed (content of the tab).
	 */
	public static function new_id( string $seed ): string {
		static $counter = 0;
		++$counter;
		return 't' . substr( md5( $seed . '|' . $counter . '|' . microtime() ), 0, 10 );
	}

	/**
	 * Scalar to string.
	 *
	 * @param mixed $raw Value.
	 */
	private static function string( mixed $raw ): string {
		return is_scalar( $raw ) ? (string) $raw : '';
	}

	/**
	 * Non-negative int.
	 *
	 * @param mixed $raw Value.
	 */
	private static function absint( mixed $raw ): int {
		return is_numeric( $raw ) ? max( 0, (int) $raw ) : 0;
	}
}
