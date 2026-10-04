<?php
/**
 * Admin save handlers: capabilities, nonces, sanitising; uninstall.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Tests\Integration;

use Pnscripts\ProductTabs\Admin\GlobalTabMetaBox;
use Pnscripts\ProductTabs\Admin\ProductPanel;
use Pnscripts\ProductTabs\Admin\SettingsPage;
use Pnscripts\ProductTabs\Settings;
use Pnscripts\ProductTabs\Storage\GlobalTabs;

final class AdminTest extends IntegrationTestCase {

	protected function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Create a user with a role.
	 *
	 * @param string $role Role.
	 */
	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password( 20 ),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	/**
	 * Product form submission.
	 *
	 * @param array<string, mixed> $fields Panel fields.
	 */
	private function post_panel( array $fields ): void {
		$_POST = wp_slash(
			array(
				'pnscripts_product_tabs_product_nonce' => wp_create_nonce( 'pnscripts_product_tabs_product' ),
				'pnscripts_product_tabs'               => $fields + array( 'submitted' => '1' ),
			)
		);
	}

	public function test_product_panel_saves_order_types_and_hidden_tabs(): void {
		$id    = $this->product( 'Panel' );
		$panel = new ProductPanel( $this->plugin->globals, $this->plugin->product_tabs );
		$this->post_panel(
			array(
				'tabs'            => array(
					'5' => array(
						'type'    => 'content',
						'title'   => 'Second "quoted"',
						'content' => '<p>It\'s <a href="https://example.test">ok</a></p>',
						'enabled' => '1',
					),
					'2' => array(
						'type'    => 'faq',
						'title'   => 'FAQ',
						'enabled' => '0',
						'schema'  => '1',
						'faq'     => array(
							'7' => array(
								'q' => 'Q?',
								'a' => 'A',
							),
						),
					),
				),
				'hidden_globals'  => array( '12', 'x' ),
				'hidden_defaults' => array( 'reviews', 'bogus' ),
			)
		);
		$panel->save( wc_get_product( $id ) );
		$tabs = $this->plugin->product_tabs->tabs( $id );
		$this->assertSame( array( 'Second "quoted"', 'FAQ' ), array_column( $tabs, 'title' ) );
		$this->assertSame( '<p>It\'s <a href="https://example.test">ok</a></p>', $tabs[0]['content'] );
		$this->assertFalse( $tabs[1]['enabled'] );
		$this->assertSame( 'Q?', $tabs[1]['faq'][0]['q'] );
		$this->assertSame( array( 12 ), $this->plugin->product_tabs->hidden_globals( $id ) );
		$this->assertSame( array( 'reviews' ), $this->plugin->product_tabs->hidden_defaults( $id ) );

		// Removing all rows deletes the meta.
		$this->post_panel( array() );
		$panel->save( wc_get_product( $id ) );
		$this->assertSame( '', get_post_meta( $id, '_pnscripts_product_tabs', true ) );
	}

	public function test_product_panel_requires_nonce_and_capability(): void {
		$id    = $this->product( 'Guarded' );
		$panel = new ProductPanel( $this->plugin->globals, $this->plugin->product_tabs );
		$this->post_panel( array( 'tabs' => array( array( 'title' => 'Sneaky' ) ) ) );
		$_POST['pnscripts_product_tabs_product_nonce'] = 'bad';
		$panel->save( wc_get_product( $id ) );
		$this->assertSame( array(), $this->plugin->product_tabs->tabs( $id ) );

		wp_set_current_user( $this->user( 'customer' ) );
		$this->post_panel( array( 'tabs' => array( array( 'title' => 'Sneaky' ) ) ) );
		$panel->save( wc_get_product( $id ) );
		$this->assertSame( array(), $this->plugin->product_tabs->tabs( $id ) );
	}

	public function test_users_without_unfiltered_html_get_kses(): void {
		$id = $this->product( 'Kses' );
		$manager = $this->user( 'shop_manager' );
		( new \WP_User( $manager ) )->add_cap( 'unfiltered_html', false ); // Like multisite shop managers.
		wp_set_current_user( $manager );
		$this->assertTrue( current_user_can( 'edit_post', $id ) );
		$this->assertFalse( current_user_can( 'unfiltered_html' ) );
		$panel = new ProductPanel( $this->plugin->globals, $this->plugin->product_tabs );
		$this->post_panel(
			array(
				'tabs' => array(
					array(
						'title'   => '<b>T</b>',
						'content' => '<p>ok</p><script>alert(1)</script>',
					),
				),
			)
		);
		$panel->save( wc_get_product( $id ) );
		$tabs = $this->plugin->product_tabs->tabs( $id );
		$this->assertSame( 'T', $tabs[0]['title'] );
		$this->assertSame( '<p>ok</p>alert(1)', $tabs[0]['content'] );
	}

	public function test_global_tab_meta_box_save(): void {
		$id = wp_insert_post(
			array(
				'post_type'   => GlobalTabs::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Box',
			)
		);
		$this->assertIsInt( $id );
		$box   = new GlobalTabMetaBox( $this->plugin->globals );
		$_POST = wp_slash(
			array(
				'pnscripts_product_tabs_global_nonce' => wp_create_nonce( 'pnscripts_product_tabs_global' ),
				'pnscripts_product_tabs_global'       => array(
					'submitted'  => '1',
					'type'       => 'faq',
					'scope'      => 'terms',
					'categories' => array( '3', '4' ),
					'priority'   => '7',
					'schema'     => '0',
					'faq'        => array(
						'9' => array(
							'q' => 'Back\\slash?',
							'a' => 'Kept',
						),
					),
				),
			)
		);
		$box->save( $id, get_post( $id ) );
		$tab = $this->plugin->globals->get( $id );
		$this->assertNotNull( $tab );
		$this->assertSame( 'faq', $tab['type'] );
		$this->assertSame( 'terms', $tab['scope'] );
		$this->assertSame( array( 3, 4 ), $tab['categories'] );
		$this->assertSame( 7, $tab['priority'] );
		$this->assertFalse( $tab['schema'] );
		$this->assertSame( 'Back\\slash?', $tab['faq'][0]['q'] );
		$this->assertArrayHasKey( $id, $this->plugin->globals->all(), 'Index rebuilt after save.' );
	}

	public function test_settings_page_registers_sanitiser_and_capability(): void {
		$page = new SettingsPage( $this->plugin->settings, $this->plugin->importer );
		$page->register();
		$page->register_setting();
		$this->assertSame( 'manage_woocommerce', apply_filters( 'option_page_capability_' . SettingsPage::GROUP, 'manage_options' ) );
		update_option(
			Settings::OPTION,
			array(
				'submitted'       => '1',
				'custom_priority' => '9000',
			)
		);
		$stored = get_option( Settings::OPTION );
		$this->assertIsArray( $stored );
		$this->assertSame( 999, $stored['custom_priority'] );
		$this->assertArrayNotHasKey( 'submitted', $stored );
	}

	public function test_uninstall_keeps_data_unless_opted_in(): void {
		$id = $this->product( 'Keep' );
		$this->plugin->product_tabs->save(
			$id,
			\Pnscripts\ProductTabs\Domain\TabSanitizer::product_tabs( array( array( 'title' => 'Keep me' ) ), true ),
			array(),
			array()
		);
		$global = $this->global_tab( 'Keep global', 'x' );
		update_option( 'yikes_woo_reusable_products_tabs', array( 1 => array( 'tab_title' => 'YIKES' ) ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'pnscripts-product-tabs/pnscripts-product-tabs.php' );
		}
		require_once PNSCRIPTS_PRODUCT_TABS_DIR . 'uninstall.php';
		$this->assertNotSame( '', get_post_meta( $id, '_pnscripts_product_tabs', true ) );
		$this->assertNotNull( get_post( $global ) );

		update_option( Settings::OPTION, array( 'remove_data' => true ) );
		pnscripts_product_tabs_uninstall_site();
		$this->assertSame( '', get_post_meta( $id, '_pnscripts_product_tabs', true ) );
		$this->assertNull( get_post( $global ) );
		$this->assertSame( 'gone', get_option( Settings::OPTION, 'gone' ) );
		$this->assertNotFalse( get_option( 'yikes_woo_reusable_products_tabs' ), 'YIKES data is never touched.' );
	}
}
