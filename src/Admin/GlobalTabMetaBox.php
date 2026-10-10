<?php
/**
 * Settings box and list columns of global tabs.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Admin;

use Pnscripts\ProductTabs\Domain\TabSanitizer;
use Pnscripts\ProductTabs\Storage\GlobalTabs;

defined( 'ABSPATH' ) || exit;

/**
 * Where a global tab appears (all products, categories/tags, or manually), its priority, and FAQ entries.
 *
 * @phpstan-import-type GlobalTab from TabSanitizer
 */
final class GlobalTabMetaBox {

	private const NONCE = 'pnscripts_product_tabs_global';
	private const FIELD = 'pnscripts_product_tabs_global';

	/**
	 * Constructor.
	 *
	 * @param GlobalTabs $globals Global tabs.
	 */
	public function __construct( private readonly GlobalTabs $globals ) {}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_' . GlobalTabs::POST_TYPE, array( $this, 'add_box' ) );
		add_action( 'save_post_' . GlobalTabs::POST_TYPE, array( $this, 'save' ), 5, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'manage_' . GlobalTabs::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . GlobalTabs::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * Register the box.
	 */
	public function add_box(): void {
		add_meta_box( 'pnscripts-product-tabs-settings', __( 'Tab settings', 'pnscripts-tabcrest' ), array( $this, 'render' ), GlobalTabs::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Assets on our edit screens.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public function enqueue( string $hook_suffix ): void {
		$screen = get_current_screen();
		if ( null === $screen || GlobalTabs::POST_TYPE !== $screen->post_type || ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'pnscripts-product-tabs-admin', PNSCRIPTS_PRODUCT_TABS_URL . 'assets/css/admin.css', array(), PNSCRIPTS_PRODUCT_TABS_VERSION );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'pnscripts-product-tabs-global', PNSCRIPTS_PRODUCT_TABS_URL . 'assets/js/admin-global.js', array( 'jquery', 'wc-enhanced-select' ), PNSCRIPTS_PRODUCT_TABS_VERSION, true );
	}

	/**
	 * Box markup.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render( $post ): void {
		$tab = $this->globals->get( (int) $post->ID );
		if ( null === $tab ) {
			return;
		}
		if ( 'auto-draft' === $post->post_status ) {
			$tab['scope']  = TabSanitizer::SCOPE_ALL;
			$tab['schema'] = true;
		}
		$name = self::FIELD;
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		?>
		<div class="pnscripts-pt-global" data-pnscripts-pt-global>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[submitted]" value="1" />
			<fieldset class="pnscripts-pt-global__row">
				<legend><?php esc_html_e( 'Tab type', 'pnscripts-tabcrest' ); ?></legend>
				<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[type]" value="content" <?php checked( TabSanitizer::TYPE_CONTENT, $tab['type'] ); ?> data-pnscripts-pt-type /> <?php esc_html_e( 'Rich text: the content written in the editor above', 'pnscripts-tabcrest' ); ?></label><br />
				<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[type]" value="faq" <?php checked( TabSanitizer::TYPE_FAQ, $tab['type'] ); ?> data-pnscripts-pt-type /> <?php esc_html_e( 'FAQ: questions and answers below', 'pnscripts-tabcrest' ); ?></label>
			</fieldset>

			<div class="pnscripts-pt-global__faq" data-pnscripts-pt-show-for="faq">
				<div data-pnscripts-pt-faq data-next="<?php echo esc_attr( (string) count( $tab['faq'] ) ); ?>">
					<?php
					$items = array() !== $tab['faq'] ? $tab['faq'] : array(
						array(
							'q' => '',
							'a' => '',
						),
					);
					foreach ( $items as $i => $item ) {
						self::faq_item( $name . '[faq][' . $i . ']', $item );
					}
					?>
				</div>
				<p>
					<button type="button" class="button" data-pnscripts-pt-faq-add><?php esc_html_e( 'Add question', 'pnscripts-tabcrest' ); ?></button>
					<label>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>[schema]" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[schema]" value="1" <?php checked( $tab['schema'] ); ?> />
						<?php esc_html_e( 'Publish as FAQPage structured data', 'pnscripts-tabcrest' ); ?>
					</label>
				</p>
				<template id="pnscripts-pt-template-global-faq">
				<?php
				self::faq_item(
					$name . '[faq][__FAQ__]',
					array(
						'q' => '',
						'a' => '',
					)
				);
				?>
																</template>
			</div>

			<fieldset class="pnscripts-pt-global__row">
				<legend><?php esc_html_e( 'Show this tab on', 'pnscripts-tabcrest' ); ?></legend>
				<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[scope]" value="all" <?php checked( TabSanitizer::SCOPE_ALL, $tab['scope'] ); ?> data-pnscripts-pt-scope /> <?php esc_html_e( 'All products', 'pnscripts-tabcrest' ); ?></label><br />
				<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[scope]" value="terms" <?php checked( TabSanitizer::SCOPE_TERMS, $tab['scope'] ); ?> data-pnscripts-pt-scope /> <?php esc_html_e( 'Products in these categories or with these tags', 'pnscripts-tabcrest' ); ?></label><br />
				<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[scope]" value="manual" <?php checked( TabSanitizer::SCOPE_MANUAL, $tab['scope'] ); ?> data-pnscripts-pt-scope /> <?php esc_html_e( 'Only products where I add it (Product data → Custom tabs)', 'pnscripts-tabcrest' ); ?></label>
			</fieldset>

			<div class="pnscripts-pt-global__terms" data-pnscripts-pt-show-for-scope="terms">
				<p>
					<label for="pnscripts-pt-cats"><strong><?php esc_html_e( 'Categories (subcategories included)', 'pnscripts-tabcrest' ); ?></strong></label><br />
					<select id="pnscripts-pt-cats" class="wc-enhanced-select" multiple="multiple" style="width:100%" name="<?php echo esc_attr( $name ); ?>[categories][]" data-placeholder="<?php esc_attr_e( 'Choose categories…', 'pnscripts-tabcrest' ); ?>">
						<?php self::term_options( 'product_cat', $tab['categories'] ); ?>
					</select>
				</p>
				<p>
					<label for="pnscripts-pt-tags"><strong><?php esc_html_e( 'Tags', 'pnscripts-tabcrest' ); ?></strong></label><br />
					<select id="pnscripts-pt-tags" class="wc-enhanced-select" multiple="multiple" style="width:100%" name="<?php echo esc_attr( $name ); ?>[tags][]" data-placeholder="<?php esc_attr_e( 'Choose tags…', 'pnscripts-tabcrest' ); ?>">
						<?php self::term_options( 'product_tag', $tab['tags'] ); ?>
					</select>
				</p>
			</div>

			<p class="pnscripts-pt-global__row">
				<label for="pnscripts-pt-priority"><strong><?php esc_html_e( 'Position (priority)', 'pnscripts-tabcrest' ); ?></strong></label><br />
				<input type="number" id="pnscripts-pt-priority" min="0" max="999" step="1" name="<?php echo esc_attr( $name ); ?>[priority]" value="<?php echo esc_attr( (string) $tab['priority'] ); ?>" class="small-text" />
				<span class="description"><?php esc_html_e( 'Lower numbers come first. WooCommerce uses 10 for Description, 20 for Additional information and 30 for Reviews (change these under Products → Tab settings).', 'pnscripts-tabcrest' ); ?></span>
			</p>
			<?php
			/**
			 * Fires at the end of the global tab settings box (add-ons add their rule fields here).
			 *
			 * @param \WP_Post $post Post.
			 * @param array    $tab  Tab data.
			 */
			do_action( 'pnscripts_product_tabs_global_tab_settings', $post, $tab );
			?>
		</div>
		<?php
	}

	/**
	 * Term options of a taxonomy (hierarchical names for categories).
	 *
	 * @param string    $taxonomy Taxonomy.
	 * @param list<int> $selected Selected ids.
	 */
	private static function term_options( string $taxonomy, array $selected ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'number'     => 2000,
			)
		);
		if ( ! is_array( $terms ) ) {
			return;
		}
		$by_id = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$by_id[ $term->term_id ] = $term;
			}
		}
		foreach ( $by_id as $term ) {
			$label  = $term->name;
			$parent = $term->parent;
			$guard  = 0;
			while ( $parent > 0 && isset( $by_id[ $parent ] ) && $guard < 10 ) {
				$label  = $by_id[ $parent ]->name . ' › ' . $label;
				$parent = $by_id[ $parent ]->parent;
				++$guard;
			}
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $term->term_id ),
				selected( in_array( $term->term_id, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
	}

	/**
	 * Question/answer fields.
	 *
	 * @param string                      $name Field prefix.
	 * @param array{q: string, a: string} $item Item.
	 */
	private static function faq_item( string $name, array $item ): void {
		?>
		<div class="pnscripts-pt__faq-item" data-pnscripts-pt-faq-item>
			<p>
				<label><strong><?php esc_html_e( 'Question', 'pnscripts-tabcrest' ); ?></strong><br />
				<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[q]" value="<?php echo esc_attr( $item['q'] ); ?>" /></label>
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Answer', 'pnscripts-tabcrest' ); ?></strong><br />
				<textarea class="widefat" rows="3" name="<?php echo esc_attr( $name ); ?>[a]"><?php echo esc_textarea( $item['a'] ); ?></textarea></label>
				<button type="button" class="button-link button-link-delete" data-pnscripts-pt-faq-remove><?php esc_html_e( 'Remove question', 'pnscripts-tabcrest' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Save the box (classic and block editor both post the meta box form).
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public function save( $post_id, $post ): void {
		unset( $post );
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( (int) $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return;
		}
		// Sanitised field by field in GlobalTabs::save_settings().
		$raw = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $raw ) || ! isset( $raw['submitted'] ) ) {
			return;
		}
		$data = array();
		foreach ( $raw as $key => $value ) {
			$data[ (string) $key ] = $value;
		}
		if ( isset( $data['faq'] ) && is_array( $data['faq'] ) ) {
			$data['faq'] = array_values( $data['faq'] );
		}
		$this->globals->save_settings( (int) $post_id, $data, current_user_can( 'unfiltered_html' ) );

		/**
		 * Fires after a global tab's settings were saved (add-ons save their own fields).
		 *
		 * @param int   $post_id Post id.
		 * @param array $raw     Unslashed, unsanitised submitted settings.
		 */
		do_action( 'pnscripts_product_tabs_global_tab_saved', (int) $post_id, $data );
	}

	/**
	 * List columns.
	 *
	 * @param mixed $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( mixed $columns ): array {
		$columns = is_array( $columns ) ? $columns : array();
		$out     = array();
		foreach ( $columns as $key => $label ) {
			$out[ (string) $key ] = is_string( $label ) ? $label : '';
			if ( 'title' === $key ) {
				$out['pnscripts_pt_scope']    = __( 'Shown on', 'pnscripts-tabcrest' );
				$out['pnscripts_pt_type']     = __( 'Type', 'pnscripts-tabcrest' );
				$out['pnscripts_pt_priority'] = __( 'Priority', 'pnscripts-tabcrest' );
			}
		}
		return $out;
	}

	/**
	 * Column values.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post id.
	 */
	public function column( $column, $post_id ): void {
		$tab = $this->globals->get( (int) $post_id );
		if ( null === $tab ) {
			return;
		}
		switch ( $column ) {
			case 'pnscripts_pt_scope':
				echo esc_html( self::scope_label( $tab ) );
				break;
			case 'pnscripts_pt_type':
				echo esc_html( TabSanitizer::TYPE_FAQ === $tab['type'] ? __( 'FAQ', 'pnscripts-tabcrest' ) : __( 'Rich text', 'pnscripts-tabcrest' ) );
				break;
			case 'pnscripts_pt_priority':
				echo esc_html( (string) $tab['priority'] );
				break;
		}
	}

	/**
	 * Human description of where a tab shows.
	 *
	 * @param GlobalTab $tab Tab.
	 */
	public static function scope_label( array $tab ): string {
		if ( TabSanitizer::SCOPE_ALL === $tab['scope'] ) {
			return __( 'All products', 'pnscripts-tabcrest' );
		}
		if ( TabSanitizer::SCOPE_MANUAL === $tab['scope'] ) {
			return __( 'Products where added', 'pnscripts-tabcrest' );
		}
		$names = array();
		foreach ( array_merge( $tab['categories'], $tab['tags'] ) as $term_id ) {
			$term = get_term( $term_id );
			if ( $term instanceof \WP_Term ) {
				$names[] = $term->name;
			}
		}
		return array() === $names ? __( 'No categories or tags chosen', 'pnscripts-tabcrest' ) : implode( ', ', $names );
	}
}
