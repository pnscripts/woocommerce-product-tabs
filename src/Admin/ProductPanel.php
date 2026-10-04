<?php
/**
 * "Custom tabs" panel in the product data box.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Admin;

use Pnscripts\ProductTabs\Domain\Conditions;
use Pnscripts\ProductTabs\Domain\DefaultTabs;
use Pnscripts\ProductTabs\Domain\TabSanitizer;
use Pnscripts\ProductTabs\Frontend\TabRenderer;
use Pnscripts\ProductTabs\Storage\GlobalTabs;
use Pnscripts\ProductTabs\Storage\ProductTabs;

defined( 'ABSPATH' ) || exit;

/**
 * Per-product tabs (rich text or FAQ), linked global tabs, and per-product hiding of global and default tabs.
 *
 * @phpstan-import-type ProductTab from TabSanitizer
 * @phpstan-import-type GlobalTab from TabSanitizer
 */
final class ProductPanel {

	private const NONCE = 'pnscripts_product_tabs_product';
	private const FIELD = 'pnscripts_product_tabs';

	/**
	 * Constructor.
	 *
	 * @param GlobalTabs  $globals      Global tabs.
	 * @param ProductTabs $product_tabs Product tabs.
	 */
	public function __construct(
		private readonly GlobalTabs $globals,
		private readonly ProductTabs $product_tabs
	) {}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the panel tab.
	 *
	 * @param mixed $tabs Product data tabs.
	 * @return array<array-key, mixed>
	 */
	public function add_tab( mixed $tabs ): array {
		$tabs                           = is_array( $tabs ) ? $tabs : array();
		$tabs['pnscripts_product_tabs'] = array(
			'label'    => __( 'Custom tabs', 'pnscripts-product-tabs' ),
			'target'   => 'pnscripts_product_tabs_panel',
			'class'    => array(),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Scripts on the product edit screen only.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || 'product' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_editor();
		wp_enqueue_style( 'pnscripts-product-tabs-admin', PNSCRIPTS_PRODUCT_TABS_URL . 'assets/css/admin.css', array(), PNSCRIPTS_PRODUCT_TABS_VERSION );
		wp_enqueue_script( 'pnscripts-product-tabs-product', PNSCRIPTS_PRODUCT_TABS_URL . 'assets/js/admin-product.js', array( 'jquery', 'jquery-ui-sortable', 'editor' ), PNSCRIPTS_PRODUCT_TABS_VERSION, true );
		wp_localize_script(
			'pnscripts-product-tabs-product',
			'pnscriptsProductTabs',
			array(
				'confirmRemove' => __( 'Remove this tab? It is deleted when you update the product.', 'pnscripts-product-tabs' ),
				'untitled'      => __( '(no title)', 'pnscripts-product-tabs' ),
			)
		);
	}

	/**
	 * Panel markup.
	 */
	public function render_panel(): void {
		global $post;
		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		$tabs       = $product_id > 0 ? $this->product_tabs->tabs( $product_id ) : array();
		$globals    = $this->all_globals();
		$hidden_g   = $product_id > 0 ? $this->product_tabs->hidden_globals( $product_id ) : array();
		$hidden_d   = $product_id > 0 ? $this->product_tabs->hidden_defaults( $product_id ) : array();
		$linked     = array();
		foreach ( $tabs as $tab ) {
			if ( TabSanitizer::TYPE_GLOBAL === $tab['type'] ) {
				$linked[ $tab['global_id'] ] = true;
			}
		}
		$context = array(
			'product_id' => $product_id,
			'categories' => $product_id > 0 ? TabRenderer::with_ancestors( array_map( 'intval', wc_get_product_term_ids( $product_id, 'product_cat' ) ), 'product_cat' ) : array(),
			'tags'       => $product_id > 0 ? array_values( array_map( 'intval', wc_get_product_term_ids( $product_id, 'product_tag' ) ) ) : array(),
		);
		?>
		<div id="pnscripts_product_tabs_panel" class="panel woocommerce_options_panel hidden pnscripts-pt">
			<?php wp_nonce_field( self::NONCE, self::NONCE . '_nonce' ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::FIELD ); ?>[submitted]" value="1" />
			<div class="pnscripts-pt__section">
				<p class="pnscripts-pt__intro"><?php esc_html_e( 'Tabs shown on this product, in this order (drag to reorder). Rich-text tabs accept anything the editor does; FAQ tabs show questions as an accessible list and can publish FAQPage structured data.', 'pnscripts-product-tabs' ); ?></p>
				<ul class="pnscripts-pt__list" data-pnscripts-pt-list>
					<?php
					foreach ( $tabs as $index => $tab ) {
						$this->render_row( (string) $index, $tab, $globals );
					}
					?>
				</ul>
				<p class="pnscripts-pt__empty<?php echo array() === $tabs ? '' : ' hidden'; ?>" data-pnscripts-pt-empty><?php esc_html_e( 'No custom tabs on this product yet.', 'pnscripts-product-tabs' ); ?></p>
				<p class="pnscripts-pt__toolbar">
					<button type="button" class="button" data-pnscripts-pt-add="content"><?php esc_html_e( 'Add tab', 'pnscripts-product-tabs' ); ?></button>
					<button type="button" class="button" data-pnscripts-pt-add="faq"><?php esc_html_e( 'Add FAQ tab', 'pnscripts-product-tabs' ); ?></button>
					<?php if ( array() !== $globals ) : ?>
						<label class="screen-reader-text" for="pnscripts-pt-global-select"><?php esc_html_e( 'Global tab to add', 'pnscripts-product-tabs' ); ?></label>
						<select id="pnscripts-pt-global-select" data-pnscripts-pt-global-select>
							<option value=""><?php esc_html_e( 'Add a global tab…', 'pnscripts-product-tabs' ); ?></option>
							<?php foreach ( $globals as $global ) : ?>
								<option value="<?php echo esc_attr( (string) $global['id'] ); ?>" data-title="<?php echo esc_attr( $global['title'] ); ?>"><?php echo esc_html( $global['title'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="button" data-pnscripts-pt-add="global"><?php esc_html_e( 'Add', 'pnscripts-product-tabs' ); ?></button>
					<?php endif; ?>
					<a class="pnscripts-pt__manage" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . GlobalTabs::POST_TYPE ) ); ?>"><?php esc_html_e( 'Manage global tabs', 'pnscripts-product-tabs' ); ?></a>
				</p>
			</div>

			<?php
			$automatic = array();
			foreach ( $globals as $global ) {
				if ( isset( $linked[ $global['id'] ] ) ) {
					continue;
				}
				if ( Conditions::matches( $global, $context ) || in_array( $global['id'], $hidden_g, true ) ) {
					$automatic[] = $global;
				}
			}
			if ( array() !== $automatic ) :
				?>
				<div class="pnscripts-pt__section">
					<h4><?php esc_html_e( 'Global tabs shown automatically', 'pnscripts-product-tabs' ); ?></h4>
					<?php foreach ( $automatic as $global ) : ?>
						<p class="form-field pnscripts-pt__check">
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>[hidden_globals][]" value="<?php echo esc_attr( (string) $global['id'] ); ?>" <?php checked( in_array( $global['id'], $hidden_g, true ) ); ?> />
								<?php
								printf(
									/* translators: %s: tab title */
									esc_html__( 'Hide "%s" on this product', 'pnscripts-product-tabs' ),
									esc_html( $global['title'] )
								);
								?>
							</label>
						</p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="pnscripts-pt__section">
				<h4><?php esc_html_e( 'WooCommerce tabs on this product', 'pnscripts-product-tabs' ); ?></h4>
				<?php
				foreach ( self::default_labels() as $key => $label ) :
					?>
					<p class="form-field pnscripts-pt__check">
						<label>
							<input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>[hidden_defaults][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $hidden_d, true ) ); ?> />
							<?php
							printf(
								/* translators: %s: tab title */
								esc_html__( 'Hide "%s" on this product', 'pnscripts-product-tabs' ),
								esc_html( $label )
							);
							?>
						</label>
					</p>
				<?php endforeach; ?>
			</div>

			<template id="pnscripts-pt-template-content"><?php $this->render_row( '__INDEX__', self::blank( TabSanitizer::TYPE_CONTENT ), $globals ); ?></template>
			<template id="pnscripts-pt-template-faq"><?php $this->render_row( '__INDEX__', self::blank( TabSanitizer::TYPE_FAQ ), $globals ); ?></template>
			<template id="pnscripts-pt-template-global"><?php $this->render_row( '__INDEX__', self::blank( TabSanitizer::TYPE_GLOBAL ), $globals ); ?></template>
			<template id="pnscripts-pt-template-faq-item">
			<?php
			self::render_faq_item(
				self::FIELD . '[tabs][__INDEX__][faq][__FAQ__]',
				array(
					'q' => '',
					'a' => '',
				)
			);
			?>
															</template>
		</div>
		<?php
	}

	/**
	 * One tab row.
	 *
	 * @param string                $index   Row index (or __INDEX__ in templates).
	 * @param ProductTab            $tab     Tab.
	 * @param array<int, GlobalTab> $globals Global tabs.
	 */
	private function render_row( string $index, array $tab, array $globals ): void {
		$name     = self::FIELD . '[tabs][' . $index . ']';
		$is_new   = '__INDEX__' === $index;
		$editor   = 'pnscripts-pt-content-' . $index;
		$global   = TabSanitizer::TYPE_GLOBAL === $tab['type'] ? ( $globals[ $tab['global_id'] ] ?? null ) : null;
		$title    = TabSanitizer::TYPE_GLOBAL === $tab['type'] ? ( null !== $global ? $global['title'] : __( '(deleted global tab)', 'pnscripts-product-tabs' ) ) : $tab['title'];
		$type_txt = array(
			TabSanitizer::TYPE_CONTENT => __( 'Rich text', 'pnscripts-product-tabs' ),
			TabSanitizer::TYPE_FAQ     => __( 'FAQ', 'pnscripts-product-tabs' ),
			TabSanitizer::TYPE_GLOBAL  => __( 'Global', 'pnscripts-product-tabs' ),
		)[ $tab['type'] ] ?? '';
		?>
		<li class="pnscripts-pt__row<?php echo $tab['enabled'] ? '' : ' is-disabled'; ?><?php echo $is_new ? ' is-open' : ''; ?>" data-pnscripts-pt-row data-index="<?php echo esc_attr( $index ); ?>" data-type="<?php echo esc_attr( $tab['type'] ); ?>">
			<div class="pnscripts-pt__head">
				<span class="pnscripts-pt__handle dashicons dashicons-menu" aria-hidden="true" title="<?php esc_attr_e( 'Drag to reorder', 'pnscripts-product-tabs' ); ?>"></span>
				<button type="button" class="pnscripts-pt__toggle" aria-expanded="<?php echo $is_new ? 'true' : 'false'; ?>" data-pnscripts-pt-toggle>
					<span class="pnscripts-pt__title" data-pnscripts-pt-title><?php echo '' !== $title ? esc_html( $title ) : esc_html__( '(no title)', 'pnscripts-product-tabs' ); ?></span>
					<span class="pnscripts-pt__badge"><?php echo esc_html( $type_txt ); ?></span>
					<?php if ( str_starts_with( $tab['origin'], 'yikes' ) ) : ?>
						<span class="pnscripts-pt__badge pnscripts-pt__badge--import"><?php esc_html_e( 'Imported', 'pnscripts-product-tabs' ); ?></span>
					<?php endif; ?>
				</button>
				<label class="pnscripts-pt__enabled">
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>[enabled]" value="0" />
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $tab['enabled'] ); ?> data-pnscripts-pt-enabled />
					<?php esc_html_e( 'Show', 'pnscripts-product-tabs' ); ?>
				</label>
				<button type="button" class="button-link button-link-delete pnscripts-pt__remove" data-pnscripts-pt-remove><?php esc_html_e( 'Remove', 'pnscripts-product-tabs' ); ?></button>
			</div>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $is_new ? '' : $tab['id'] ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[type]" value="<?php echo esc_attr( $tab['type'] ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[origin]" value="<?php echo esc_attr( $tab['origin'] ); ?>" />
			<div class="pnscripts-pt__body">
				<?php if ( TabSanitizer::TYPE_GLOBAL === $tab['type'] ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>[global_id]" value="<?php echo esc_attr( $is_new ? '' : (string) $tab['global_id'] ); ?>" data-pnscripts-pt-global-id />
					<p class="pnscripts-pt__note">
						<?php esc_html_e( 'Content comes from the global tab and updates everywhere when you edit it.', 'pnscripts-product-tabs' ); ?>
						<?php if ( null !== $global ) : ?>
							<a href="<?php echo esc_url( (string) get_edit_post_link( $global['id'] ) ); ?>"><?php esc_html_e( 'Edit global tab', 'pnscripts-product-tabs' ); ?></a>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<p class="form-field">
						<label for="pnscripts-pt-title-<?php echo esc_attr( $index ); ?>"><?php esc_html_e( 'Tab title', 'pnscripts-product-tabs' ); ?></label>
						<input type="text" class="short" id="pnscripts-pt-title-<?php echo esc_attr( $index ); ?>" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $tab['title'] ); ?>" data-pnscripts-pt-title-input />
					</p>
					<?php if ( TabSanitizer::TYPE_FAQ === $tab['type'] ) : ?>
						<div class="pnscripts-pt__faq" data-pnscripts-pt-faq data-next="<?php echo esc_attr( (string) count( $tab['faq'] ) ); ?>">
							<?php
							foreach ( $tab['faq'] as $i => $item ) {
								self::render_faq_item( $name . '[faq][' . $i . ']', $item );
							}
							?>
						</div>
						<p class="pnscripts-pt__faq-actions">
							<button type="button" class="button" data-pnscripts-pt-faq-add><?php esc_html_e( 'Add question', 'pnscripts-product-tabs' ); ?></button>
							<label>
								<input type="hidden" name="<?php echo esc_attr( $name ); ?>[schema]" value="0" />
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[schema]" value="1" <?php checked( $tab['schema'] ); ?> />
								<?php esc_html_e( 'Publish as FAQPage structured data', 'pnscripts-product-tabs' ); ?>
							</label>
						</p>
					<?php else : ?>
						<div class="pnscripts-pt__editor">
							<label class="screen-reader-text" for="<?php echo esc_attr( $editor ); ?>"><?php esc_html_e( 'Tab content', 'pnscripts-product-tabs' ); ?></label>
							<textarea class="wp-editor-area" id="<?php echo esc_attr( $editor ); ?>" name="<?php echo esc_attr( $name ); ?>[content]" rows="10" data-pnscripts-pt-editor><?php echo esc_textarea( $tab['content'] ); ?></textarea>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</li>
		<?php
	}

	/**
	 * One question/answer pair.
	 *
	 * @param string                      $name Field name prefix.
	 * @param array{q: string, a: string} $item Item.
	 */
	private static function render_faq_item( string $name, array $item ): void {
		?>
		<div class="pnscripts-pt__faq-item" data-pnscripts-pt-faq-item>
			<p class="form-field">
				<label><?php esc_html_e( 'Question', 'pnscripts-product-tabs' ); ?></label>
				<input type="text" class="short" name="<?php echo esc_attr( $name ); ?>[q]" value="<?php echo esc_attr( $item['q'] ); ?>" />
			</p>
			<p class="form-field">
				<label><?php esc_html_e( 'Answer', 'pnscripts-product-tabs' ); ?></label>
				<textarea class="short" rows="3" name="<?php echo esc_attr( $name ); ?>[a]"><?php echo esc_textarea( $item['a'] ); ?></textarea>
				<button type="button" class="button-link button-link-delete" data-pnscripts-pt-faq-remove><?php esc_html_e( 'Remove question', 'pnscripts-product-tabs' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Save with the product (WooCommerce already checked its own nonce; ours is checked too).
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public function save( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		$product_id = $product->get_id();
		if ( $product_id <= 0 || ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}
		// Sanitised field by field in TabSanitizer / ProductTabs::default_keys().
		$raw = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $raw ) || ! isset( $raw['submitted'] ) ) {
			return;
		}
		$tabs = TabSanitizer::product_tabs( isset( $raw['tabs'] ) && is_array( $raw['tabs'] ) ? array_values( $raw['tabs'] ) : array(), current_user_can( 'unfiltered_html' ) );
		$this->product_tabs->save(
			$product_id,
			$tabs,
			TabSanitizer::id_list( $raw['hidden_globals'] ?? array() ),
			ProductTabs::default_keys( $raw['hidden_defaults'] ?? array() )
		);
	}

	/**
	 * All global tabs (published and drafts) for the admin selector.
	 *
	 * @return array<int, GlobalTab>
	 */
	private function all_globals(): array {
		$out = $this->globals->all();
		return $out;
	}

	/**
	 * Empty tab for templates.
	 *
	 * @param string $type Type.
	 * @return ProductTab
	 */
	private static function blank( string $type ): array {
		return array(
			'id'        => '',
			'type'      => $type,
			'title'     => '',
			'content'   => '',
			'faq'       => TabSanitizer::TYPE_FAQ === $type ? array(
				array(
					'q' => '',
					'a' => '',
				),
			) : array(),
			'schema'    => true,
			'global_id' => 0,
			'enabled'   => true,
			'origin'    => '',
		);
	}

	/**
	 * Translated names of the default tabs.
	 *
	 * @return array<string, string>
	 */
	public static function default_labels(): array {
		$labels = array(
			'description'            => __( 'Description', 'pnscripts-product-tabs' ),
			'additional_information' => __( 'Additional information', 'pnscripts-product-tabs' ),
			'reviews'                => __( 'Reviews', 'pnscripts-product-tabs' ),
		);
		return array_intersect_key( $labels, array_flip( DefaultTabs::KEYS ) );
	}
}
