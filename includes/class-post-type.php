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
	}

	/**
	 * Registers the nls_rss_entry custom post type.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => [
					'name'          => __( 'RSS Entries', 'newspack-lite-site' ),
					'singular_name' => __( 'RSS Entry', 'newspack-lite-site' ),
					'add_new_item'  => __( 'Add RSS Entry', 'newspack-lite-site' ),
					'edit_item'     => __( 'Edit RSS Entry', 'newspack-lite-site' ),
					'new_item'      => __( 'New RSS Entry', 'newspack-lite-site' ),
					'view_item'     => __( 'View RSS Entry', 'newspack-lite-site' ),
					'search_items'  => __( 'Search RSS Entries', 'newspack-lite-site' ),
					'not_found'     => __( 'No RSS entries found.', 'newspack-lite-site' ),
				],
				'description'         => __( 'Posts imported from external RSS feeds by Newspack Lite Site.', 'newspack-lite-site' ),
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
}
