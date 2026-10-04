<?php
/**
 * FAQPage structured data.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Builds one schema.org FAQPage from the FAQ tabs shown on a product page.
 *
 * @phpstan-import-type FaqItem from TabSanitizer
 */
final class FaqSchema {

	/**
	 * Build the JSON-LD array; null when there is nothing to publish.
	 *
	 * @param list<FaqItem>            $items      Question/answer pairs (answers may contain HTML).
	 * @param callable(string): string $plain_text Converts answer HTML to text (wp_strip_all_tags).
	 * @return array<string, mixed>|null
	 */
	public static function build( array $items, callable $plain_text ): ?array {
		$entities = array();
		$seen     = array();
		foreach ( $items as $item ) {
			$question = trim( $item['q'] );
			$answer   = trim( preg_replace( '/\s+/u', ' ', $plain_text( $item['a'] ) ) ?? '' );
			if ( '' === $question || '' === $answer || isset( $seen[ $question ] ) ) {
				continue;
			}
			$seen[ $question ] = true;
			$entities[]        = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}
		if ( array() === $entities ) {
			return null;
		}
		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
	}
}
