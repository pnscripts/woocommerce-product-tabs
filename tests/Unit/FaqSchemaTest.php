<?php
/**
 * Tests for FaqSchema.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Unit;

use Pnscripts\ProductTabs\Domain\FaqSchema;

final class FaqSchemaTest extends TestCase {

	public function test_builds_faq_page(): void {
		$data = FaqSchema::build(
			array(
				array(
					'q' => 'Is it warm?',
					'a' => "<p>Yes,\n <strong>very</strong>.</p>",
				),
				array(
					'q' => 'Is it warm?',
					'a' => 'Duplicate question is dropped.',
				),
				array(
					'q' => 'Empty answer?',
					'a' => '<p> </p>',
				),
			),
			'wp_strip_all_tags'
		);
		$this->assertNotNull( $data );
		$this->assertSame( 'FAQPage', $data['@type'] );
		$this->assertSame(
			array(
				array(
					'@type'          => 'Question',
					'name'           => 'Is it warm?',
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => 'Yes, very.',
					),
				),
			),
			$data['mainEntity']
		);
	}

	public function test_nothing_to_publish(): void {
		$this->assertNull( FaqSchema::build( array(), 'wp_strip_all_tags' ) );
	}
}
