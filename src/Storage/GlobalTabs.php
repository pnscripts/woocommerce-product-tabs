<?php
/**
 * Global (reusable) tabs: post type, meta and a cached index for the storefront.
 *
 * @package Pnscripts\ProductTabs
 */

declare(strict_types=1);

namespace Pnscripts\ProductTabs\Storage;

use Pnscripts\ProductTabs\Domain\TabSanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Global tabs are posts of a private post type edited in the block editor. The storefront never
 * queries them: it reads one non-autoloaded option (the index), rebuilt whenever a tab changes.
 *
 * @phpstan-import-type GlobalTab from TabSanitizer
 */
final class GlobalTabs {

	public const POST_TYPE   = 'pnscripts_ptab';
	public const INDEX       = 'pnscripts_product_tabs_index';
	public const META_TYPE   = '_pnscripts_product_tabs_type';
	public const META_FAQ    = '_pnscripts_product_tabs_faq';
	public const META_SCHEMA = '_pnscripts_product_tabs_schema';
	public const META_SCOPE  = '_pnscripts_product_tabs_scope';
	public const META_CATS   = '_pnscripts_product_tabs_categories';
	public const META_TAGS   = '_pnscripts_product_tabs_tags';
	public const META_PRIO   = '_pnscripts_product_tabs_priority';
	public const META_YIKES  = '_pnscripts_product_tabs_yikes_id';

	/**
	 * Maximum global tabs kept in the index.
	 */
	public const MAX = 500;

	/**
	 * Request cache of the index.
	 *
	 * @var array<int, GlobalTab>|null
	 */
	private ?array $index = null;

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'flush' ) );
		add_action( 'deleted_post', array( $this, 'maybe_flush' ), 10, 2 );
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'on_meta_change' ), 10, 3 );
		add_action( 'added_post_meta', array( $this, 'on_meta_change' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'on_meta_change' ), 10, 3 );
	}

	/**
	 * Register the post type (block editor, product capabilities).
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'                => array(
					'name'               => __( 'Global product tabs', 'pnscripts-product-tabs' ),
					'singular_name'      => __( 'Global product tab', 'pnscripts-product-tabs' ),
					'menu_name'          => __( 'Product tabs', 'pnscripts-product-tabs' ),
					'all_items'          => __( 'Product tabs', 'pnscripts-product-tabs' ),
					'add_new'            => __( 'Add global tab', 'pnscripts-product-tabs' ),
					'add_new_item'       => __( 'Add global tab', 'pnscripts-product-tabs' ),
					'edit_item'          => __( 'Edit global tab', 'pnscripts-product-tabs' ),
					'new_item'           => __( 'New global tab', 'pnscripts-product-tabs' ),
					'search_items'       => __( 'Search global tabs', 'pnscripts-product-tabs' ),
					'not_found'          => __( 'No global tabs yet.', 'pnscripts-product-tabs' ),
					'not_found_in_trash' => __( 'No global tabs in the trash.', 'pnscripts-product-tabs' ),
					'item_updated'       => __( 'Global tab updated.', 'pnscripts-product-tabs' ),
					'item_published'     => __( 'Global tab published.', 'pnscripts-product-tabs' ),
				),
				'description'           => __( 'Reusable tabs shown on all products, on products in chosen categories or tags, or where added manually.', 'pnscripts-product-tabs' ),
				'public'                => false,
				'publicly_queryable'    => false,
				'exclude_from_search'   => true,
				'show_ui'               => true,
				'show_in_menu'          => 'edit.php?post_type=product',
				'show_in_nav_menus'     => false,
				'show_in_admin_bar'     => false,
				'show_in_rest'          => true,
				'rest_base'             => 'pnscripts-product-tabs',
				'rest_controller_class' => GlobalTabsRestController::class,
				'capability_type'       => 'product',
				'map_meta_cap'          => true,
				'hierarchical'          => false,
				'has_archive'           => false,
				'rewrite'               => false,
				'query_var'             => false,
				'supports'              => array( 'title', 'editor', 'revisions' ),
			)
		);
	}

	/**
	 * Published global tabs by id, ordered by priority then title.
	 *
	 * @return array<int, GlobalTab>
	 */
	public function all(): array {
		if ( null !== $this->index ) {
			return $this->index;
		}
		$stored = get_option( self::INDEX, false );
		if ( ! is_array( $stored ) ) {
			$stored = $this->rebuild();
		}
		$index = array();
		foreach ( $stored as $row ) {
			$tab = self::normalise( $row );
			if ( null !== $tab ) {
				$index[ $tab['id'] ] = $tab;
			}
		}
		$this->index = $index;
		return $index;
	}

	/**
	 * One global tab (published or not) read from the post, for the admin.
	 *
	 * @param int $id Post id.
	 * @return GlobalTab|null
	 */
	public function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		return $this->from_post( $post );
	}

	/**
	 * Rebuild and store the index (one query, then cached meta).
	 *
	 * @return list<GlobalTab>
	 */
	public function rebuild(): array {
		$posts = get_posts(
			array(
				'post_type'     => self::POST_TYPE,
				'post_status'   => 'publish',
				'numberposts'   => self::MAX,
				'orderby'       => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows' => true,
			)
		);
		$rows  = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$rows[] = $this->from_post( $post );
			}
		}
		usort(
			$rows,
			static fn ( array $a, array $b ): int => array( $a['priority'], $a['title'] ) <=> array( $b['priority'], $b['title'] )
		);
		update_option( self::INDEX, $rows, false );
		return $rows;
	}

	/**
	 * Drop the stored index; the next storefront request rebuilds it.
	 */
	public function flush(): void {
		$this->index = null;
		delete_option( self::INDEX );
	}

	/**
	 * Forget the in-memory copy only (long-running processes, tests).
	 */
	public function flush_request_cache(): void {
		$this->index = null;
	}

	/**
	 * Flush when one of our posts is deleted.
	 *
	 * @param int           $post_id Post id.
	 * @param \WP_Post|null $post    Post.
	 */
	public function maybe_flush( int $post_id, $post = null ): void {
		unset( $post_id );
		if ( $post instanceof \WP_Post && self::POST_TYPE === $post->post_type ) {
			$this->flush();
		}
	}

	/**
	 * Flush on any status change of our posts (publish, trash, restore, schedule).
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public function on_transition( string $new_status, string $old_status, $post ): void {
		unset( $new_status, $old_status );
		$this->maybe_flush( 0, $post );
	}

	/**
	 * Flush when a meta key of ours changes on a global tab.
	 *
	 * @param int|array<int> $meta_id  Meta id(s).
	 * @param int            $post_id  Post id.
	 * @param string         $meta_key Meta key.
	 */
	public function on_meta_change( $meta_id, $post_id, $meta_key = '' ): void {
		unset( $meta_id );
		if ( is_string( $meta_key ) && str_starts_with( $meta_key, '_pnscripts_product_tabs_' ) && self::POST_TYPE === get_post_type( (int) $post_id ) ) {
			$this->flush();
		}
	}

	/**
	 * Save the settings of a global tab.
	 *
	 * @param int                  $id   Post id.
	 * @param array<string, mixed> $data Untrusted settings: type, faq, schema, scope, categories, tags, priority.
	 * @param bool                 $unfiltered_html Whether the user may store unfiltered HTML.
	 */
	public function save_settings( int $id, array $data, bool $unfiltered_html ): void {
		$type = TabSanitizer::global_type( $data['type'] ?? '' );
		update_post_meta( $id, self::META_TYPE, $type );
		update_post_meta( $id, self::META_FAQ, wp_slash( TabSanitizer::faq_items( $data['faq'] ?? array(), $unfiltered_html ) ) );
		update_post_meta( $id, self::META_SCHEMA, TabSanitizer::bool( $data['schema'] ?? false ) ? 'yes' : 'no' );
		update_post_meta( $id, self::META_SCOPE, TabSanitizer::scope( $data['scope'] ?? '' ) );
		update_post_meta( $id, self::META_CATS, TabSanitizer::id_list( $data['categories'] ?? array() ) );
		update_post_meta( $id, self::META_TAGS, TabSanitizer::id_list( $data['tags'] ?? array() ) );
		update_post_meta( $id, self::META_PRIO, TabSanitizer::priority( $data['priority'] ?? null, 25 ) );
		$this->flush();
	}

	/**
	 * Build the tab array from a post (meta comes from the primed meta cache).
	 *
	 * @param \WP_Post $post Post.
	 * @return GlobalTab
	 */
	private function from_post( \WP_Post $post ): array {
		$id  = (int) $post->ID;
		$row = array(
			'id'         => $id,
			'title'      => $post->post_title,
			'slug'       => self::slug( $post ),
			'content'    => $post->post_content,
			'type'       => get_post_meta( $id, self::META_TYPE, true ),
			'faq'        => get_post_meta( $id, self::META_FAQ, true ),
			'schema'     => 'no' !== get_post_meta( $id, self::META_SCHEMA, true ),
			'scope'      => get_post_meta( $id, self::META_SCOPE, true ),
			'categories' => get_post_meta( $id, self::META_CATS, true ),
			'tags'       => get_post_meta( $id, self::META_TAGS, true ),
			'priority'   => get_post_meta( $id, self::META_PRIO, true ),
			'extra'      => array(),
		);

		/**
		 * Filters a global tab before it is cached in the index. Add-ons store their own rule data
		 * (schedules, attribute or role conditions) under the "extra" key.
		 *
		 * @param array    $row  Tab data.
		 * @param \WP_Post $post Post.
		 */
		$row = apply_filters( 'pnscripts_product_tabs_index_entry', $row, $post );

		return self::normalise( $row ) ?? array(
			'id'         => $id,
			'title'      => $post->post_title,
			'slug'       => self::slug( $post ),
			'content'    => '',
			'type'       => TabSanitizer::TYPE_CONTENT,
			'faq'        => array(),
			'schema'     => false,
			'scope'      => TabSanitizer::SCOPE_MANUAL,
			'categories' => array(),
			'tags'       => array(),
			'priority'   => 25,
			'extra'      => array(),
		);
	}

	/**
	 * Tab key of a global tab: its title as a slug (readable #tab-shipping anchors).
	 *
	 * @param \WP_Post $post Post.
	 */
	private static function slug( \WP_Post $post ): string {
		$slug = urldecode( sanitize_title( $post->post_title ) );
		return '' !== $slug ? $slug : 'tab-' . $post->ID;
	}

	/**
	 * Validate a cached row (the option may be stale or tampered with).
	 *
	 * @param mixed $row Row.
	 * @return GlobalTab|null
	 */
	public static function normalise( mixed $row ): ?array {
		if ( ! is_array( $row ) || ! isset( $row['id'] ) || ! is_numeric( $row['id'] ) || (int) $row['id'] <= 0 ) {
			return null;
		}
		$tab = array(
			'id'         => (int) $row['id'],
			'title'      => is_string( $row['title'] ?? null ) ? $row['title'] : '',
			'slug'       => is_string( $row['slug'] ?? null ) && '' !== $row['slug'] ? $row['slug'] : 'tab-' . (int) $row['id'],
			'content'    => is_string( $row['content'] ?? null ) ? $row['content'] : '',
			'type'       => TabSanitizer::global_type( $row['type'] ?? '' ),
			'faq'        => TabSanitizer::faq_items( $row['faq'] ?? array(), true ),
			'schema'     => TabSanitizer::bool( $row['schema'] ?? false ),
			'scope'      => TabSanitizer::scope( $row['scope'] ?? '' ),
			'categories' => TabSanitizer::id_list( $row['categories'] ?? array() ),
			'tags'       => TabSanitizer::id_list( $row['tags'] ?? array() ),
			'priority'   => TabSanitizer::priority( $row['priority'] ?? null, 25 ),
			'extra'      => isset( $row['extra'] ) && is_array( $row['extra'] ) ? $row['extra'] : array(),
		);
		return $tab;
	}
}
