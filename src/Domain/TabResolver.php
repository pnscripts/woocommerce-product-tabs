<?php
/**
 * Decides which custom tabs a product shows, in which order and under which keys.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Pure logic (no WordPress calls) so it is unit tested in isolation.
 *
 * Rules:
 * - Tabs listed on the product keep their list order, starting at the "custom tabs" priority (25 by
 *   default: after Additional information, before Reviews, as YIKES did).
 * - A global tab listed on the product (linked) uses the product position; it is never added twice.
 * - Global tabs that match the product's categories/tags (or "all products") are added with their own
 *   priority unless the product hides them.
 * - Disabled tabs and global tabs that no longer exist are skipped.
 * - Keys are unique slugs of the title, never colliding with WooCommerce's or other plugins' keys.
 *
 * @phpstan-import-type ProductTab from TabSanitizer
 * @phpstan-import-type GlobalTab from TabSanitizer
 * @phpstan-import-type ResolvedTab from TabSanitizer
 * @phpstan-type Context array{product_id: int, categories: list<int>, tags: list<int>}
 */
final class TabResolver {

	/**
	 * Slug function (sanitize_title in WordPress).
	 *
	 * @var callable(string): string
	 */
	private $slugify;

	/**
	 * Constructor.
	 *
	 * @param callable(string): string $slugify Turns a title into a key.
	 */
	public function __construct( callable $slugify ) {
		$this->slugify = $slugify;
	}

	/**
	 * Resolve the custom tabs of a product.
	 *
	 * @param Context                                         $context        Product id and term ids (categories include ancestors).
	 * @param list<ProductTab>                                $product_tabs   Tabs stored on the product.
	 * @param list<int>                                       $hidden_globals Global tab ids hidden on this product.
	 * @param array<int, GlobalTab>                           $globals        Published global tabs by id.
	 * @param int                                             $base_priority  Priority of the first product tab.
	 * @param list<string>                                    $taken_keys     Keys already used by other tabs.
	 * @param (callable(bool, GlobalTab, Context): bool)|null $applies        Optional override of the condition check (add-ons).
	 * @return list<ResolvedTab>
	 */
	public function resolve( array $context, array $product_tabs, array $hidden_globals, array $globals, int $base_priority, array $taken_keys = array(), ?callable $applies = null ): array {
		$resolved = array();
		$linked   = array();
		$position = 0;

		foreach ( $product_tabs as $tab ) {
			$priority = $base_priority + $position;
			++$position;
			if ( TabSanitizer::TYPE_GLOBAL === $tab['type'] ) {
				$linked[ $tab['global_id'] ] = true;
				if ( ! $tab['enabled'] || ! isset( $globals[ $tab['global_id'] ] ) ) {
					continue;
				}
				$resolved[] = $this->from_global( $globals[ $tab['global_id'] ], $priority, 'linked' );
				continue;
			}
			if ( ! $tab['enabled'] ) {
				continue;
			}
			$resolved[] = array(
				'key'       => '',
				'title'     => $tab['title'],
				'priority'  => $priority,
				'type'      => $tab['type'],
				'content'   => $tab['content'],
				'faq'       => $tab['faq'],
				'schema'    => $tab['schema'],
				'global_id' => 0,
				'source'    => 'product',
			);
		}

		foreach ( $globals as $id => $global_tab ) {
			if ( isset( $linked[ $id ] ) || in_array( $id, $hidden_globals, true ) ) {
				continue;
			}
			$applies_here = Conditions::matches( $global_tab, $context );
			if ( null !== $applies ) {
				$applies_here = (bool) $applies( $applies_here, $global_tab, $context );
			}
			if ( $applies_here ) {
				$resolved[] = $this->from_global( $global_tab, $global_tab['priority'], 'global' );
			}
		}

		usort(
			$resolved,
			static fn ( array $a, array $b ): int => $a['priority'] <=> $b['priority']
		);

		return $this->assign_keys( $resolved, $taken_keys );
	}

	/**
	 * Resolved tab from a global tab.
	 *
	 * @param GlobalTab $global_tab Global tab.
	 * @param int       $priority Priority.
	 * @param string    $source   "linked" or "global".
	 * @return ResolvedTab
	 */
	private function from_global( array $global_tab, int $priority, string $source ): array {
		return array(
			'key'       => $global_tab['slug'],
			'title'     => $global_tab['title'],
			'priority'  => $priority,
			'type'      => $global_tab['type'],
			'content'   => $global_tab['content'],
			'faq'       => $global_tab['faq'],
			'schema'    => $global_tab['schema'],
			'global_id' => $global_tab['id'],
			'source'    => $source,
		);
	}

	/**
	 * Give every tab a unique key.
	 *
	 * @param list<ResolvedTab> $tabs  Tabs.
	 * @param list<string>      $taken Keys already used.
	 * @return list<ResolvedTab>
	 */
	private function assign_keys( array $tabs, array $taken ): array {
		$used = array_fill_keys( array_merge( $taken, array( 'description', 'additional_information', 'reviews' ) ), true );
		foreach ( $tabs as $index => $tab ) {
			$base = '' !== $tab['key'] ? $tab['key'] : ( $this->slugify )( $tab['title'] );
			$base = '' !== $base ? $base : 'tab';
			$key  = $base;
			$n    = 2;
			while ( isset( $used[ $key ] ) ) {
				$key = $base . '-' . $n;
				++$n;
			}
			$used[ $key ]          = true;
			$tabs[ $index ]['key'] = $key;
		}
		return $tabs;
	}
}
