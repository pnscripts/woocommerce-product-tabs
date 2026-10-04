<?php
/**
 * Tests for Settings::sanitize().
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Unit;

use Pnscripts\ProductTabs\Settings;

final class SettingsTest extends TestCase {

	public function test_garbage_gives_defaults(): void {
		$this->assertSame( Settings::defaults(), Settings::sanitize( 'x' ) );
		$this->assertSame( Settings::defaults(), Settings::sanitize( array() ) );
	}

	public function test_form_submission_turns_missing_checkboxes_off(): void {
		$out = Settings::sanitize(
			array(
				'submitted'       => '1',
				'defaults'        => array(
					'reviews' => array(
						'enabled'  => '1',
						'title'    => '<b>Reviews</b> (%d)',
						'priority' => '70',
					),
				),
				'custom_priority' => '15',
				'faq_schema'      => '1',
			)
		);
		$this->assertFalse( $out['defaults']['description']['enabled'] );
		$this->assertTrue( $out['defaults']['reviews']['enabled'] );
		$this->assertSame( 'Reviews (%d)', $out['defaults']['reviews']['title'] );
		$this->assertSame( 70, $out['defaults']['reviews']['priority'] );
		$this->assertSame( 15, $out['custom_priority'] );
		$this->assertTrue( $out['faq_schema'] );
		$this->assertFalse( $out['reviews_last'] );
		$this->assertFalse( $out['remove_data'] );
	}

	public function test_stored_partial_array_is_merged_over_defaults(): void {
		$out = Settings::sanitize( array( 'remove_data' => true ) );
		$this->assertTrue( $out['remove_data'] );
		$this->assertTrue( $out['reviews_last'] );
		$this->assertTrue( $out['defaults']['description']['enabled'] );
		$this->assertSame( 20, $out['defaults']['additional_information']['priority'] );
	}
}
