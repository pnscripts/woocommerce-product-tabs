<?php
/**
 * Tests for DefaultTabs.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Unit;

use Pnscripts\ProductTabs\Domain\DefaultTabs;
use Pnscripts\ProductTabs\Settings;

final class DefaultTabsTest extends TestCase {

	/**
	 * WooCommerce's default tabs plus one custom tab.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function tabs(): array {
		return array(
			'description'            => array(
				'title'    => 'Description',
				'priority' => 10,
				'callback' => 'woocommerce_product_description_tab',
			),
			'additional_information' => array(
				'title'    => 'Additional information',
				'priority' => 20,
				'callback' => 'woocommerce_product_additional_information_tab',
			),
			'reviews'                => array(
				'title'    => 'Reviews (3)',
				'priority' => 30,
				'callback' => 'comments_template',
			),
			'size'                   => array(
				'title'    => 'Size',
				'priority' => 35,
				'callback' => 'x',
			),
		);
	}

	public function test_defaults_change_nothing_but_reviews_last(): void {
		$out = DefaultTabs::apply( self::tabs(), Settings::defaults()['defaults'], array(), true, 3 );
		$this->assertSame( 'Description', $out['description']['title'] );
		$this->assertSame( 10, $out['description']['priority'] );
		$this->assertSame( 36, $out['reviews']['priority'] );
		$out = DefaultTabs::apply( self::tabs(), Settings::defaults()['defaults'], array(), false, 3 );
		$this->assertSame( 30, $out['reviews']['priority'] );
	}

	public function test_rename_reorder_and_hide(): void {
		$settings                                 = Settings::defaults()['defaults'];
		$settings['description']['title']         = 'Overview';
		$settings['description']['priority']      = 50;
		$settings['additional_information']['enabled'] = false;
		$settings['reviews']['title']             = 'What customers say (%d)';
		$out                                      = DefaultTabs::apply( self::tabs(), $settings, array(), false, 3 );
		$this->assertSame( 'Overview', $out['description']['title'] );
		$this->assertSame( 50, $out['description']['priority'] );
		$this->assertArrayNotHasKey( 'additional_information', $out );
		$this->assertSame( 'What customers say (3)', $out['reviews']['title'] );
		$this->assertSame( 'Size', $out['size']['title'] );
	}

	public function test_hidden_on_product(): void {
		$out = DefaultTabs::apply( self::tabs(), Settings::defaults()['defaults'], array( 'reviews' ), true, 0 );
		$this->assertArrayNotHasKey( 'reviews', $out );
		$this->assertArrayHasKey( 'description', $out );
	}

	public function test_missing_default_tabs_are_left_alone(): void {
		$out = DefaultTabs::apply( array( 'size' => array( 'title' => 'S', 'priority' => 5 ) ), Settings::defaults()['defaults'], array(), true, 0 );
		$this->assertSame( array( 'size' ), array_keys( $out ) );
	}

	public function test_title_placeholder_only_for_reviews(): void {
		$this->assertSame( 'Reviews 4', DefaultTabs::title( 'Reviews %d', 4, true ) );
		$this->assertSame( '100%d', DefaultTabs::title( '100%d', 4, false ) );
	}
}
