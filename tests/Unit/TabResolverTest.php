<?php
/**
 * Tests for TabResolver and Conditions.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Unit;

use Pnscripts\ProductTabs\Domain\Conditions;
use Pnscripts\ProductTabs\Domain\TabResolver;

final class TabResolverTest extends TestCase {

	private const CONTEXT = array(
		'product_id' => 7,
		'categories' => array( 10, 11 ),
		'tags'       => array( 50 ),
	);

	/**
	 * Product tab fixture.
	 *
	 * @param array<string, mixed> $over Overrides.
	 * @return array{id: string, type: string, title: string, content: string, faq: list<array{q: string, a: string}>, schema: bool, global_id: int, enabled: bool, origin: string}
	 */
	private static function tab( array $over ): array {
		return array_merge(
			array(
				'id'        => 't1',
				'type'      => 'content',
				'title'     => 'Tab',
				'content'   => 'C',
				'faq'       => array(),
				'schema'    => false,
				'global_id' => 0,
				'enabled'   => true,
				'origin'    => '',
			),
			$over
		);
	}

	/**
	 * Global tab fixture.
	 *
	 * @param int                  $id   Id.
	 * @param array<string, mixed> $over Overrides.
	 * @return array{id: int, title: string, slug: string, content: string, type: string, faq: list<array{q: string, a: string}>, schema: bool, scope: string, categories: list<int>, tags: list<int>, priority: int, extra: array<array-key, mixed>}
	 */
	private static function global_tab( int $id, array $over = array() ): array {
		return array_merge(
			array(
				'id'         => $id,
				'title'      => 'Global ' . $id,
				'slug'       => 'global-' . $id,
				'content'    => 'G' . $id,
				'type'       => 'content',
				'faq'        => array(),
				'schema'     => false,
				'scope'      => 'all',
				'categories' => array(),
				'tags'       => array(),
				'priority'   => 40,
				'extra'      => array(),
			),
			$over
		);
	}

	private function resolver(): TabResolver {
		return new TabResolver( self::slugify() );
	}

	public function test_product_tabs_keep_list_order_from_base_priority(): void {
		$tabs = $this->resolver()->resolve(
			self::CONTEXT,
			array(
				self::tab(
					array(
						'id'    => 'a',
						'title' => 'First',
					)
				),
				self::tab(
					array(
						'id'    => 'b',
						'title' => 'Second',
					)
				),
			),
			array(),
			array(),
			25
		);
		$this->assertSame( array( 'first', 'second' ), array_column( $tabs, 'key' ) );
		$this->assertSame( array( 25, 26 ), array_column( $tabs, 'priority' ) );
		$this->assertSame( 'product', $tabs[0]['source'] );
	}

	public function test_disabled_tabs_are_skipped_but_keep_their_slot(): void {
		$tabs = $this->resolver()->resolve(
			self::CONTEXT,
			array(
				self::tab(
					array(
						'id'      => 'a',
						'title'   => 'Off',
						'enabled' => false,
					)
				),
				self::tab(
					array(
						'id'    => 'b',
						'title' => 'On',
					)
				),
			),
			array(),
			array(),
			25
		);
		$this->assertSame( array( 'On' ), array_column( $tabs, 'title' ) );
		$this->assertSame( 26, $tabs[0]['priority'] );
	}

	public function test_linked_global_uses_product_position_and_is_not_added_twice(): void {
		$tabs = $this->resolver()->resolve(
			self::CONTEXT,
			array(
				self::tab(
					array(
						'id'        => 'g1',
						'type'      => 'global',
						'global_id' => 1,
					)
				),
			),
			array(),
			array( 1 => self::global_tab( 1 ) ),
			25
		);
		$this->assertCount( 1, $tabs );
		$this->assertSame( 25, $tabs[0]['priority'] );
		$this->assertSame( 'linked', $tabs[0]['source'] );
		$this->assertSame( 'G1', $tabs[0]['content'] );
	}

	public function test_disabled_link_hides_an_automatic_global_tab(): void {
		$tabs = $this->resolver()->resolve(
			self::CONTEXT,
			array(
				self::tab(
					array(
						'type'      => 'global',
						'global_id' => 1,
						'enabled'   => false,
					)
				),
			),
			array(),
			array( 1 => self::global_tab( 1 ) ),
			25
		);
		$this->assertSame( array(), $tabs );
	}

	public function test_link_to_deleted_global_is_ignored(): void {
		$tabs = $this->resolver()->resolve(
			self::CONTEXT,
			array(
				self::tab(
					array(
						'type'      => 'global',
						'global_id' => 99,
					)
				),
			),
			array(),
			array(),
			25
		);
		$this->assertSame( array(), $tabs );
	}

	public function test_automatic_globals_by_scope_and_hidden_list(): void {
		$globals = array(
			1 => self::global_tab( 1 ),
			2 => self::global_tab(
				2,
				array(
					'scope'      => 'terms',
					'categories' => array( 11 ),
					'priority'   => 5,
				)
			),
			3 => self::global_tab(
				3,
				array(
					'scope'      => 'terms',
					'categories' => array( 99 ),
				)
			),
			4 => self::global_tab(
				4,
				array(
					'scope' => 'terms',
					'tags'  => array( 50 ),
				)
			),
			5 => self::global_tab( 5, array( 'scope' => 'manual' ) ),
			6 => self::global_tab( 6 ),
		);
		$tabs    = $this->resolver()->resolve( self::CONTEXT, array(), array( 6 ), $globals, 25 );
		$this->assertSame( array( 2, 1, 4 ), array_column( $tabs, 'global_id' ) );
		$this->assertSame( 'global', $tabs[0]['source'] );
	}

	public function test_keys_are_unique_and_avoid_taken_and_default_keys(): void {
		$tabs = $this->resolver()->resolve(
			self::CONTEXT,
			array(
				self::tab(
					array(
						'id'    => 'a',
						'title' => 'Reviews',
					)
				),
				self::tab(
					array(
						'id'    => 'b',
						'title' => 'Care',
					)
				),
				self::tab(
					array(
						'id'    => 'c',
						'title' => 'Care',
					)
				),
				self::tab(
					array(
						'id'    => 'd',
						'title' => 'Shipping',
					)
				),
				self::tab(
					array(
						'id'    => 'e',
						'title' => '!!!',
					)
				),
			),
			array(),
			array(),
			25,
			array( 'shipping' )
		);
		$this->assertSame( array( 'reviews-2', 'care', 'care-2', 'shipping-2', 'tab' ), array_column( $tabs, 'key' ) );
	}

	public function test_applies_callback_can_override_rules(): void {
		$globals = array(
			1 => self::global_tab( 1 ),
			2 => self::global_tab( 2, array( 'scope' => 'manual' ) ),
		);
		$seen    = array();
		$tabs    = $this->resolver()->resolve(
			self::CONTEXT,
			array(),
			array(),
			$globals,
			25,
			array(),
			static function ( bool $applies, array $global_tab, array $context ) use ( &$seen ): bool {
				$seen[] = $context['product_id'];
				return 2 === $global_tab['id'] ? true : ! $applies;
			}
		);
		$this->assertSame( array( 2 ), array_column( $tabs, 'global_id' ) );
		$this->assertSame( array( 7, 7 ), $seen );
	}

	public function test_conditions(): void {
		$this->assertTrue( Conditions::matches( self::global_tab( 1 ), self::CONTEXT ) );
		$this->assertFalse( Conditions::matches( self::global_tab( 1, array( 'scope' => 'manual' ) ), self::CONTEXT ) );
		$this->assertFalse(
			Conditions::matches(
				self::global_tab( 1, array( 'scope' => 'terms' ) ),
				self::CONTEXT
			)
		);
		$this->assertTrue(
			Conditions::matches(
				self::global_tab(
					1,
					array(
						'scope' => 'terms',
						'tags'  => array( 50 ),
					)
				),
				self::CONTEXT
			)
		);
	}
}
