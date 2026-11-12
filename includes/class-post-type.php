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
		add_action( 'transition_post_status', [ __CLASS__, 'clear_trash_meta_on_restore' ], 10, 3 );
	}

	/**
	 * Registers the nls_rss_entry custom post type.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => [
					'name'                     => __( 'RSS Entries', 'newspack-lite-site' ),
					'singular_name'            => __( 'RSS Entry', 'newspack-lite-site' ),
					'add_new'                  => __( 'Add RSS Entry', 'newspack-lite-site' ),
					'add_new_item'             => __( 'Add RSS Entry', 'newspack-lite-site' ),
					'edit_item'                => __( 'Edit RSS Entry', 'newspack-lite-site' ),
					'new_item'                 => __( 'New RSS Entry', 'newspack-lite-site' ),
					'view_item'                => __( 'View RSS Entry', 'newspack-lite-site' ),
					'search_items'             => __( 'Search RSS Entries', 'newspack-lite-site' ),
					'not_found'                => __( 'No RSS entries found.', 'newspack-lite-site' ),
					'not_found_in_trash'       => __( 'No RSS entries found in the trash.', 'newspack-lite-site' ),
					'item_published'           => __( 'RSS entry published.', 'newspack-lite-site' ),
					'item_published_privately' => __( 'RSS entry published privately.', 'newspack-lite-site' ),
					'item_updated'             => __( 'RSS entry updated.', 'newspack-lite-site' ),
					'item_reverted_to_draft'   => __( 'RSS entry reverted to draft.', 'newspack-lite-site' ),
					'item_scheduled'           => __( 'RSS entry scheduled.', 'newspack-lite-site' ),
					'item_trashed'             => __( 'RSS entry moved to the trash.', 'newspack-lite-site' ),
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
	 * Clears the trash meta of an RSS entry that leaves the trash.
	 *
	 * Only core's untrash clears it otherwise, so an entry restored another way, such as through
	 * the REST API, would keep its old trash time and be purged early if trashed again.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       The post.
	 */
	public static function clear_trash_meta_on_restore( string $new_status, string $old_status, $post ): void {
		if ( 'trash' !== $old_status || 'trash' === $new_status || ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
			return;
		}
		delete_post_meta( $post->ID, '_wp_trash_meta_status' );
		delete_post_meta( $post->ID, '_wp_trash_meta_time' );
	}

	/**
	 * Registers the read-only REST fields used by the RSS entries list.
	 */
	public static function register_rest_fields(): void {
		$fields = [
			'feed_id'     => [ __CLASS__, 'get_entry_feed_id' ],
			'feed_label'  => [ __CLASS__, 'get_entry_feed_label' ],
			'source_url'  => fn( $post ) => (string) get_post_meta( $post['id'], '_rss_import_source_url', true ),
			'author_name' => [ __CLASS__, 'get_author_name' ],
			'lite_url'    => [ __CLASS__, 'get_lite_url' ],
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
	public static function get_author_avatar_urls( array $post ): array {
		return self::get_user_avatar_urls( (int) get_post_field( 'post_author', $post['id'] ) );
	}

	/**
	 * Get a user's avatar URLs, keyed by size, or an empty array when there is no user or the site hides avatars.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, string>
	 */
	public static function get_user_avatar_urls( int $user_id ): array {
		if ( ! $user_id || ! get_option( 'show_avatars' ) ) {
			return [];
		}
		return rest_get_avatar_urls( $user_id );
	}

	/**
	 * Get the display name of a post's author.
	 *
	 * @param array $post Prepared REST post data.
	 * @return string
	 */
	public static function get_author_name( array $post ): string {
		$user = get_userdata( (int) get_post_field( 'post_author', $post['id'] ) );
		return $user ? $user->display_name : '';
	}

	/**
	 * Get the Lite Site URL of a published post.
	 *
	 * @param array $post Prepared REST post data.
	 * @return string
	 */
	public static function get_lite_url( array $post ): string {
		if ( 'publish' !== get_post_status( $post['id'] ) || ! Lite_Site::is_enabled() ) {
			return '';
		}
		return (string) Lite_Site::get_lite_page_url( get_post( $post['id'] ) );
	}

	/**
	 * Get the ID of the feed a post was imported from.
	 *
	 * @param array $post Prepared REST post data.
	 * @return string
	 */
	public static function get_entry_feed_id( array $post ): string {
		$url = (string) get_post_meta( $post['id'], '_rss_import_feed_url', true );
		return '' !== $url ? RSS_Importer::get_feed_id( $url ) : '';
	}

	/**
	 * Get the label of the feed a post was imported from.
	 *
	 * @param array $post Prepared REST post data.
	 * @return string
	 */
	public static function get_entry_feed_label( array $post ): string {
		$id = self::get_entry_feed_id( $post );
		if ( '' === $id ) {
			return '';
		}
		return self::get_entry_feeds()[ $id ] ?? self::get_feed_label( (string) get_post_meta( $post['id'], '_rss_import_feed_url', true ) );
	}

	/**
	 * Get the distinct feed URLs RSS entries were imported from, including feeds that were since deleted.
	 *
	 * @return string[]
	 */
	public static function get_entry_feed_urls(): array {
		global $wpdb;

		$cache_key = 'entry_feed_urls:' . wp_cache_get_last_changed( 'posts' );
		$urls      = wp_cache_get( $cache_key, 'newspack-lite-site' );
		if ( is_array( $urls ) ) {
			return $urls;
		}

		$urls = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value <> '' AND p.post_type = %s",
				'_rss_import_feed_url',
				self::POST_TYPE
			)
		);
		wp_cache_set( $cache_key, $urls, 'newspack-lite-site' );
		return $urls;
	}

	/**
	 * Get the feeds RSS entries were imported from, as labels keyed by feed ID and sorted by label.
	 *
	 * Labels are the feed's host and path. Feeds that share a label get a short form of their ID appended.
	 *
	 * @return array<string, string>
	 */
	public static function get_entry_feeds(): array {
		$feeds = [];
		foreach ( self::get_entry_feed_urls() as $url ) {
			$feeds[ RSS_Importer::get_feed_id( $url ) ] = self::get_feed_label( $url );
		}

		$counts = array_count_values( $feeds );
		foreach ( $feeds as $id => $label ) {
			if ( $counts[ $label ] > 1 ) {
				$feeds[ $id ] = sprintf( '%1$s (%2$s)', $label, substr( $id, 0, 7 ) );
			}
		}

		uasort( $feeds, 'strcasecmp' );
		return $feeds;
	}

	/**
	 * Get the label of a feed URL: its host and path, without the scheme or query string.
	 *
	 * @param string $url Feed URL.
	 * @return string
	 */
	public static function get_feed_label( string $url ): string {
		$label = untrailingslashit( (string) wp_parse_url( $url, PHP_URL_HOST ) . (string) wp_parse_url( $url, PHP_URL_PATH ) );
		return '' !== $label ? $label : strtok( $url, '?' );
	}

	/**
	 * Adds the feed filter to the collection parameters.
	 *
	 * @param array $params Collection parameters.
	 * @return array
	 */
	public static function add_feed_collection_param( array $params ): array {
		$params['feed'] = [
			'description' => __( 'Limit results to entries imported from the feed with this ID.', 'newspack-lite-site' ),
			'type'        => 'string',
			'pattern'     => '^[a-f0-9]{32}$',
		];
		return $params;
	}

	/**
	 * Restricts the REST query to the requested feed.
	 *
	 * The feed ID is the MD5 hash of the stored feed URL, so entries from deleted feeds stay filterable.
	 *
	 * @param array            $args    WP_Query arguments.
	 * @param \WP_REST_Request $request The request.
	 * @return array
	 */
	public static function filter_rest_query_by_feed( array $args, \WP_REST_Request $request ): array {
		if ( ! current_user_can( get_post_type_object( self::POST_TYPE )->cap->edit_posts ) ) {
			return $args;
		}
		$feed = $request->get_param( 'feed' );
		if ( ! is_string( $feed ) || '' === $feed ) {
			return $args;
		}

		$url = '';
		foreach ( self::get_entry_feed_urls() as $candidate ) {
			if ( RSS_Importer::get_feed_id( $candidate ) === $feed ) {
				$url = $candidate;
				break;
			}
		}

		if ( '' === $url ) {
			$args['post__in'] = [ 0 ];
			return $args;
		}

		$args['meta_query'][] = [
			'key'   => '_rss_import_feed_url',
			'value' => $url,
		];
		return $args;
	}
}
