<?php
/**
 * Rename, reorder and hide WooCommerce's own tabs.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Pure transformation of the woocommerce_product_tabs array.
 *
 * @phpstan-type DefaultSetting array{enabled: bool, title: string, priority: int}
 * @phpstan-type DefaultSettings array{description: DefaultSetting, additional_information: DefaultSetting, reviews: DefaultSetting}
 */
final class DefaultTabs {

	public const KEYS = array( 'description', 'additional_information', 'reviews' );

	/**
	 * Apply the settings.
	 *
	 * @param array<array-key, mixed> $tabs              WooCommerce tabs (key => [title, priority, callback, ...]).
	 * @param DefaultSettings         $settings          Store-wide settings.
	 * @param list<string>            $hidden_on_product Default tab keys hidden on this product.
	 * @param bool                    $reviews_last      Move Reviews after every other tab.
	 * @param int                     $review_count      Number of reviews (for a "%d" placeholder in the Reviews title).
	 * @return array<array-key, mixed>
	 */
	public static function apply( array $tabs, array $settings, array $hidden_on_product, bool $reviews_last, int $review_count ): array {
		foreach ( self::KEYS as $key ) {
			if ( ! isset( $tabs[ $key ] ) || ! is_array( $tabs[ $key ] ) ) {
				continue;
			}
			$setting = $settings[ $key ];
			if ( ! $setting['enabled'] || in_array( $key, $hidden_on_product, true ) ) {
				unset( $tabs[ $key ] );
				continue;
			}
			if ( '' !== $setting['title'] ) {
				$tabs[ $key ]['title'] = self::title( $setting['title'], $review_count, 'reviews' === $key );
			}
			$tabs[ $key ]['priority'] = $setting['priority'];
		}

		if ( $reviews_last && isset( $tabs['reviews'] ) && is_array( $tabs['reviews'] ) ) {
			$max = 0;
			foreach ( $tabs as $key => $tab ) {
				if ( 'reviews' !== $key && is_array( $tab ) && isset( $tab['priority'] ) && is_numeric( $tab['priority'] ) ) {
					$max = max( $max, (int) $tab['priority'] );
				}
			}
			$current = isset( $tabs['reviews']['priority'] ) && is_numeric( $tabs['reviews']['priority'] ) ? (int) $tabs['reviews']['priority'] : 30;
			if ( $current <= $max ) {
				$tabs['reviews']['priority'] = $max + 1;
			}
		}

		return $tabs;
	}

	/**
	 * Custom title; "%d" in the Reviews title becomes the review count.
	 *
	 * @param string $title        Title from the settings.
	 * @param int    $review_count Review count.
	 * @param bool   $is_reviews   Whether this is the Reviews tab.
	 */
	public static function title( string $title, int $review_count, bool $is_reviews ): string {
		return $is_reviews ? str_replace( '%d', (string) $review_count, $title ) : $title;
	}
}
