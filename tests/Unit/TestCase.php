<?php
/**
 * Base test case.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Sets up Brain Monkey and simple, faithful stand-ins for WordPress sanitising helpers.
 */
abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\stubs(
			array(
				'sanitize_text_field' => static fn ( $s ) => trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) ),
				'wp_kses_post'        => static fn ( $s ) => preg_replace( '#<(script|iframe)[^>]*>.*?</\1>#is', '', (string) $s ),
				'wp_json_encode'      => static fn ( $v, $f = 0 ) => json_encode( $v, $f ),
				'wp_strip_all_tags'   => static fn ( $s ) => trim( strip_tags( (string) $s ) ),
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * WordPress-like slug: lowercase, non-alphanumerics to dashes.
	 */
	protected static function slugify(): callable {
		return static fn ( string $title ): string => trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', '-', mb_strtolower( $title ) ), '-' );
	}
}
