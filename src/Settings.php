<?php
/**
 * Store-wide settings (one autoloaded option).
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs;

use Pnscripts\ProductTabs\Domain\DefaultTabs;
use Pnscripts\ProductTabs\Domain\TabSanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the settings option.
 *
 * @phpstan-import-type DefaultSettings from DefaultTabs
 * @phpstan-type SettingsData array{defaults: DefaultSettings, custom_priority: int, reviews_last: bool, show_heading: bool, faq_schema: bool, hide_yikes: bool, remove_data: bool}
 */
final class Settings {

	public const OPTION = 'pnscripts_product_tabs_settings';

	/**
	 * Request cache.
	 *
	 * @var SettingsData|null
	 */
	private ?array $data = null;

	/**
	 * Defaults (WooCommerce's own priorities; nothing hidden; data kept on uninstall).
	 *
	 * @return SettingsData
	 */
	public static function defaults(): array {
		return array(
			'defaults'        => array(
				'description'            => array(
					'enabled'  => true,
					'title'    => '',
					'priority' => 10,
				),
				'additional_information' => array(
					'enabled'  => true,
					'title'    => '',
					'priority' => 20,
				),
				'reviews'                => array(
					'enabled'  => true,
					'title'    => '',
					'priority' => 30,
				),
			),
			'custom_priority' => 25,
			'reviews_last'    => true,
			'show_heading'    => true,
			'faq_schema'      => true,
			'hide_yikes'      => true,
			'remove_data'     => false,
		);
	}

	/**
	 * All settings.
	 *
	 * @return SettingsData
	 */
	public function all(): array {
		if ( null === $this->data ) {
			$this->data = self::sanitize( get_option( self::OPTION, array() ) );
		}
		return $this->data;
	}

	/**
	 * Forget the request cache (after an update).
	 */
	public function flush(): void {
		$this->data = null;
	}

	/**
	 * Normalise stored or submitted settings. Missing checkboxes in a submitted form mean "off", so
	 * the form posts a hidden "submitted" marker; stored arrays are merged over the defaults.
	 *
	 * @param mixed $raw Untrusted settings.
	 * @return SettingsData
	 */
	public static function sanitize( mixed $raw ): array {
		$defaults = self::defaults();
		if ( ! is_array( $raw ) ) {
			return $defaults;
		}
		$from_form = isset( $raw['submitted'] );
		$bool      = static function ( string $key ) use ( $raw, $defaults, $from_form ): bool {
			if ( array_key_exists( $key, $raw ) ) {
				return TabSanitizer::bool( $raw[ $key ] );
			}
			return $from_form ? false : (bool) $defaults[ $key ];
		};

		$out = $defaults;
		foreach ( DefaultTabs::KEYS as $key ) {
			$row = isset( $raw['defaults'][ $key ] ) && is_array( $raw['defaults'][ $key ] ) ? $raw['defaults'][ $key ] : array();
			if ( array_key_exists( 'enabled', $row ) ) {
				$enabled = TabSanitizer::bool( $row['enabled'] );
			} else {
				$enabled = ! $from_form;
			}
			$out['defaults'][ $key ] = array(
				'enabled'  => $enabled,
				'title'    => TabSanitizer::text( $row['title'] ?? '' ),
				'priority' => TabSanitizer::priority( $row['priority'] ?? null, $defaults['defaults'][ $key ]['priority'] ),
			);
		}
		$out['custom_priority'] = TabSanitizer::priority( $raw['custom_priority'] ?? null, $defaults['custom_priority'] );
		$out['reviews_last']    = $bool( 'reviews_last' );
		$out['show_heading']    = $bool( 'show_heading' );
		$out['faq_schema']      = $bool( 'faq_schema' );
		$out['hide_yikes']      = $bool( 'hide_yikes' );
		$out['remove_data']     = $bool( 'remove_data' );
		return $out;
	}
}
