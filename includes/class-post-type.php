<?php
/**
 * Register the nls_rss_entry custom post type.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

defined( 'ABSPATH' ) || exit;

/**
 * Handles registration of the nls_rss_entry custom post type.
 */
class Post_Type {

	const POST_TYPE = 'nls_rss_entry';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_fields' ] );
		add_filter( 'rest_' . self::POST_TYPE . '_collection_params', [ __CLASS__, 'add_feed_collection_param' ] );
		add_filter( 'rest_' . self::POST_TYPE . '_query', [ __CLASS__, 'filter_rest_query_by_feed' ], 10, 2 );
	}

	/**
	 * Registers the nls_rss_entry custom post type.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => [
					'name'                   => __( 'RSS Entries', 'newspack-lite-site' ),
					'singular_name'          => __( 'RSS Entry', 'newspack-lite-site' ),
					'add_new'                => __( 'Add RSS Entry', 'newspack-lite-site' ),
					'add_new_item'           => __( 'Add RSS Entry', 'newspack-lite-site' ),
					'edit_item'              => __( 'Edit RSS Entry', 'newspack-lite-site' ),
					'new_item'               => __( 'New RSS Entry', 'newspack-lite-site' ),
					'view_item'              => __( 'View RSS Entry', 'newspack-lite-site' ),
					'search_items'           => __( 'Search RSS Entries', 'newspack-lite-site' ),
					'not_found'              => __( 'No RSS entries found.', 'newspack-lite-site' ),
					'not_found_in_trash'     => __( 'No RSS entries found in the trash.', 'newspack-lite-site' ),
					'item_published'         => __( 'RSS entry published.', 'newspack-lite-site' ),
					'item_updated'           => __( 'RSS entry updated.', 'newspack-lite-site' ),
					'item_reverted_to_draft' => __( 'RSS entry reverted to draft.', 'newspack-lite-site' ),
					'item_scheduled'         => __( 'RSS entry scheduled.', 'newspack-lite-site' ),
					'item_trashed'           => __( 'RSS entry moved to the trash.', 'newspack-lite-site' ),
				],
				'description'         => __( "Posts imported from other sites' RSS feeds by Lite Site.", 'newspack-lite-site' ),
				'public'              => false,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_in_rest'        => true,
				'show_ui'             => true,
				'show_in_menu'        => 'newspack-lite-site',
				'show_in_nav_menus'   => false,
				'query_var'           => true,
				'rewrite'             => [ 'slug' => 'rss-entry' ],
				'can_export'          => true,
				'delete_with_user'    => false,
				'taxonomies'          => [ 'category', 'post_tag' ],
				'supports'            => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields' ],
			]
		);
	}

	/**
	 * Registers the read-only REST fields used by the RSS entries list.
	 */
	public static function register_rest_fields() {
		$fields = [
			'feed_url'     => fn( $post ) => (string) get_post_meta( $post['id'], '_rss_import_feed_url', true ),
			'source_url'   => fn( $post ) => (string) get_post_meta( $post['id'], '_rss_import_source_url', true ),
			'author_name'  => [ __CLASS__, 'get_author_name' ],
			'lite_url'     => [ __CLASS__, 'get_lite_url' ],
			'trash_status' => [ __CLASS__, 'get_trash_status' ],
		];

		foreach ( $fields as $name => $callback ) {
			register_rest_field(
				self::POST_TYPE,
				$name,
				[
					'get_callback' => $callback,
					'schema'       => [
						'type'     => 'string',
						'context'  => [ 'edit' ],
						'readonly' => true,
					],
				]
			);
		}

		register_rest_field(
			self::POST_TYPE,
			'author_avatar_urls',
			[
				'get_callback' => [ __CLASS__, 'get_author_avatar_urls' ],
				'schema'       => [
					'type'     => 'object',
					'context'  => [ 'edit' ],
					'readonly' => true,
				],
			]
		);
	}

	/**
	 * Get the avatar URLs of a post's author, keyed by size, or an empty array when the site hides avatars.
	 *
	 * @param array $post Prepared REST post data.
	 * @return array
	 */
	public static function get_author_avatar_urls( $post ): array {
		$author = (int) get_post_field( 'post_author', $post['id'] );
		if ( ! $author || ! get_option( 'show_avatars' ) ) {
			return [];
		}
		return rest_get_avatar_urls( $author );
	}

	/**
	 * Get the display name of a post's author.
	 *
	 * @param array $post Prepared REST post data.
	 * @return string
	 */
	public static function get_author_name( $post ): string {
		$user = get_userdata( (int) get_post_field( 'post_author', $post['id'] ) );
		return $user ? $user->display_name : '';
	}

	/**
	 * Get the Lite Site URL of a published post.
	 *
	 * @param array $post Prepared REST post data.
	 * @return string
	 */
	public static function get_lite_url( $post ): string {
		if ( 'publish' !== get_post_status( $post['id'] ) || ! Lite_Site::is_enabled() ) {
			return '';
		}
		return (string) Lite_Site::get_lite_page_url( get_post( $post['id'] ) );
	}

	/**
	 * Get the status a trashed post had before it was trashed.
	 *
	 * @param array $post Prepared REST post data.
	 * @return string
	 */
	public static function get_trash_status( $post ): string {
		if ( 'trash' !== get_post_status( $post['id'] ) ) {
			return '';
		}
		return (string) get_post_meta( $post['id'], '_wp_trash_meta_status', true );
	}

	/**
	 * Adds the feed filter to the collection parameters.
	 *
	 * @param array $params Collection parameters.
	 * @return array
	 */
	public static function add_feed_collection_param( $params ) {
		$params['feed'] = [
			'description'       => __( 'Limit results to entries imported from this feed URL.', 'newspack-lite-site' ),
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
		];
		return $params;
	}

	/**
	 * Restricts the REST query to the requested feed.
	 *
	 * @param array            $args    WP_Query arguments.
	 * @param \WP_REST_Request $request The request.
	 * @return array
	 */
	public static function filter_rest_query_by_feed( $args, $request ) {
		if ( ! current_user_can( get_post_type_object( self::POST_TYPE )->cap->edit_posts ) ) {
			return $args;
		}
		$feed = $request->get_param( 'feed' );
		if ( is_string( $feed ) && '' !== $feed ) {
			$args['meta_query'][] = [
				'key'   => '_rss_import_feed_url',
				'value' => $feed,
			];
		}
		return $args;
	}
}
