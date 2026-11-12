<?php
/**
 * Lite Site settings and admin page.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

defined( 'ABSPATH' ) || exit;

/**
 * Lite Site Settings class.
 */
class Lite_Site_Settings {

	/**
	 * The option name for storing settings.
	 */
	const OPTION_NAME = 'newspack_lite_site_settings';

	/**
	 * Slug of the hidden RSS Entries admin page.
	 */
	const ENTRIES_PAGE_SLUG = 'newspack-lite-site-rss-entries';

	/**
	 * Hook suffix of the hidden RSS Entries admin page.
	 */
	const ENTRIES_HOOK_SUFFIX = 'admin_page_newspack-lite-site-rss-entries';

	/**
	 * Hook suffix of the top-level Lite Site page, which is the Settings page.
	 */
	const TOPLEVEL_HOOK_SUFFIX = 'toplevel_page_newspack-lite-site';

	/**
	 * Menu icon: the `feather` glyph from newspack-icons.
	 */
	const MENU_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M8.6 18L7.35 19.69C7.32333 19.73 7.29333 19.7667 7.26 19.8C7.22 19.8333 7.18 19.8633 7.14 19.89C7.1 19.9167 7.05667 19.94 7.01 19.96C6.96333 19.9733 6.91333 19.9833 6.86 19.99C6.81333 19.9967 6.76667 20 6.72 20C6.66667 20 6.61667 19.9933 6.57 19.98C6.52333 19.9667 6.47667 19.95 6.43 19.93C6.39 19.91 6.35 19.8833 6.31 19.85C6.27 19.8233 6.23333 19.7933 6.2 19.76C6.16667 19.72 6.13667 19.68 6.11 19.64C6.08333 19.6 6.06 19.5567 6.04 19.51C6.02667 19.4633 6.01667 19.4133 6.01 19.36C6.00333 19.3133 6 19.2667 6 19.22C6 19.1667 6.00667 19.1167 6.02 19.07C6.03333 19.0233 6.05 18.9767 6.07 18.93C6.09 18.89 6.11667 18.85 6.15 18.81L7.39 17.12C7.32333 16.7733 7.28333 16.4033 7.27 16.01C7.25667 15.4633 7.29 14.8933 7.37 14.3C7.56333 12.9667 7.98667 11.6933 8.64 10.48C9.40667 9.05333 10.4367 7.83 11.73 6.81C13.21 5.64333 14.98 4.78667 17.04 4.24L17.94 4L17.98 4.93C18.08 7.05667 17.7933 9.00333 17.12 10.77C16.5333 12.31 15.6767 13.6567 14.55 14.81C13.59 15.7967 12.5 16.5833 11.28 17.17C10.7467 17.43 10.2133 17.6333 9.68 17.78C9.3 17.8867 8.94 17.96 8.6 18ZM8.86 14.49C9.43333 13.63 10.0867 12.7733 10.82 11.92C11.7 10.9067 12.6167 9.98667 13.57 9.16C14.53 8.33333 15.4867 7.64667 16.44 7.1C16.4733 6.72667 16.4933 6.34667 16.5 5.96C15.0333 6.44667 13.7533 7.12333 12.66 7.99C11.5267 8.88333 10.6267 9.95 9.96 11.19C9.39333 12.2367 9.02667 13.3367 8.86 14.49ZM16.08 9.1C15.5733 9.46 15.0633 9.86 14.55 10.3C13.65 11.0733 12.7867 11.94 11.96 12.9C11.2067 13.7667 10.55 14.6333 9.99 15.5C9.81667 15.7667 9.66 16.02 9.52 16.26C9.88667 16.1467 10.2567 16 10.63 15.82C11.69 15.3133 12.64 14.63 13.48 13.77C14.46 12.7567 15.2067 11.58 15.72 10.24C15.86 9.86667 15.98 9.48667 16.08 9.1Z"/></svg>';

	/**
	 * Legacy list table query args forwarded to the RSS Entries page.
	 */
	const FORWARDED_LEGACY_ARGS = [ 'post_status', 's', 'author' ];

	/**
	 * Initialize the settings functionality.
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', [ __CLASS__, 'menu_order' ], 12 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_styles' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_scripts' ] );
		add_action( 'admin_bar_menu', [ __CLASS__, 'add_admin_bar_link' ], 100 );
		add_filter( 'admin_body_class', [ __CLASS__, 'admin_header_body_class' ] );
		add_action( 'current_screen', [ __CLASS__, 'maybe_redirect_legacy_entries_list' ] );
		add_action( 'current_screen', [ __CLASS__, 'set_title_for_entries_page' ] );
		add_filter( 'parent_file', [ __CLASS__, 'filter_entries_parent_file' ] );
		add_filter( 'submenu_file', [ __CLASS__, 'filter_entries_submenu_file' ] );
		add_action( 'updated_option', [ __CLASS__, 'handle_updated_option' ], 10, 3 );
		add_action( 'added_option', [ __CLASS__, 'handle_added_option' ], 10, 2 );
	}

	/**
	 * Register settings.
	 *
	 * Registers the plugin option with a full REST API schema so it is accessible
	 * via /wp/v2/settings. Sanitization is handled by sanitize_settings(), which
	 * fires on every update_option() call regardless of caller.
	 */
	public static function register_settings() {
		register_setting(
			'newspack_lite_site',
			self::OPTION_NAME,
			[
				'type'              => 'object',
				'default'           => [
					'enabled'                => false,
					'url_base'               => 'lite',
					'posts_per_page'         => 10,
					'categories'             => [],
					'include_subcategories'  => true,
					'tags'                   => [],
					'excluded_categories'    => [],
					'excluded_tags'          => [],
					'footer_html'            => '',
					'custom_css'             => '',
					'external_links_new_tab' => true,
					'ga4_measurement_id'     => '',
					'primary_color'          => '',
					'font_import_url'        => '',
					'font_body'              => '',
				],
				'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
				'show_in_rest'      => [
					'schema' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => [
							'enabled'                => [
								'type'    => 'boolean',
								'default' => false,
							],
							'url_base'               => [
								'type'    => 'string',
								'default' => 'lite',
							],
							'posts_per_page'         => [
								'type'    => 'integer',
								'default' => 10,
								'minimum' => 1,
								'maximum' => 100,
							],
							'categories'             => [
								'type'    => 'array',
								'default' => [],
								'items'   => [ 'type' => 'integer' ],
							],
							'include_subcategories'  => [
								'type'    => 'boolean',
								'default' => true,
							],
							'tags'                   => [
								'type'    => 'array',
								'default' => [],
								'items'   => [ 'type' => 'integer' ],
							],
							'excluded_categories'    => [
								'type'    => 'array',
								'default' => [],
								'items'   => [ 'type' => 'integer' ],
							],
							'excluded_tags'          => [
								'type'    => 'array',
								'default' => [],
								'items'   => [ 'type' => 'integer' ],
							],
							'footer_html'            => [
								'type'    => 'string',
								'default' => '',
							],
							'custom_css'             => [
								'type'    => 'string',
								'default' => '',
							],
							'external_links_new_tab' => [
								'type'    => 'boolean',
								'default' => true,
							],
							'ga4_measurement_id'     => [
								'type'    => 'string',
								'default' => '',
							],
							'primary_color'          => [
								'type'    => 'string',
								'default' => '',
							],
							'font_import_url'        => [
								'type'    => 'string',
								'default' => '',
							],
							'font_body'              => [
								'type'    => 'string',
								'default' => '',
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Enqueue admin styles for the plugin's admin pages.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueue_admin_styles( $hook_suffix ) {
		if ( ! self::is_plugin_admin_page( $hook_suffix ) ) {
			return;
		}

		$asset_file = NEWSPACK_LITE_SITE_PLUGIN_DIR . 'dist/index.asset.php';
		$version    = file_exists( $asset_file )
			? ( require $asset_file )['version']
			: '';

		wp_enqueue_style(
			'newspack-lite-site',
			plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/index.css',
			[ 'wp-components' ],
			$version
		);
	}

	/**
	 * Enqueue admin scripts for the plugin's admin pages.
	 *
	 * A single JS bundle loads on the Settings, RSS Entries and RSS Feed Import pages.
	 * Bootstrap data for all three is passed in one wp_localize_script call.
	 * Static config (e.g. interval labels) is defined in JS.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueue_admin_scripts( $hook_suffix ) {
		if ( ! self::is_plugin_admin_page( $hook_suffix ) ) {
			return;
		}

		$asset_file = NEWSPACK_LITE_SITE_PLUGIN_DIR . 'dist/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [
				'dependencies' => [],
				'version'      => '',
			];

		wp_enqueue_script(
			'newspack-lite-site',
			plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Build hierarchy-ordered category list for the React categories field.
		$all_categories     = get_categories( [ 'hide_empty' => false ] );
		$ordered_categories = self::build_ordered_categories( $all_categories, 0, 0 );

		$theme_color     = Lite_Site::get_theme_primary_color();
		$default_color   = 'currentcolor' !== $theme_color ? $theme_color : '#808080';
		$interval_labels = RSS_Importer::get_interval_labels();

		wp_localize_script(
			'newspack-lite-site',
			'newspackLiteSite',
			[
				'rssEntries'   => self::ENTRIES_HOOK_SUFFIX === $hook_suffix ? self::get_rss_entries_data() : null,
				'defaultColor' => $default_color,
				'homeUrl'      => home_url( '/' ),
				'categories'   => array_values(
					array_map(
						fn( $cat ) => [
							'id'    => $cat->term_id,
							'name'  => $cat->name,
							'depth' => $cat->depth,
						],
						$ordered_categories
					)
				),
				'cronDisabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'intervals'    => array_values(
					array_map(
						fn( $value, $label ) => [
							'value' => $value,
							'label' => $label,
						],
						array_keys( $interval_labels ),
						$interval_labels
					)
				),
				'authors'      => array_values(
					array_map(
						fn( $u ) => [
							'value' => (string) $u->ID,
							'label' => $u->display_name,
						],
						get_users(
							[
								'capability' => 'publish_posts',
								'fields'     => [ 'ID', 'display_name' ],
							]
						)
					)
				),
			]
		);
	}

	/**
	 * Register the top-level Lite Site menu and its Settings and RSS Feed Import subpages.
	 */
	public static function add_menu_page() {
		add_menu_page(
			'Lite Site',
			'Lite Site',
			'manage_options',
			'newspack-lite-site',
			[ __CLASS__, 'render_settings_page' ],
			'data:image/svg+xml;base64,' . base64_encode( self::MENU_ICON_SVG ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			26
		);

		// RSS Feed Import: manage scheduled RSS feeds that pull external content.
		add_submenu_page(
			'newspack-lite-site',
			__( 'RSS Feed Import', 'newspack-lite-site' ),
			__( 'RSS Feed Import', 'newspack-lite-site' ),
			'manage_options',
			'newspack-lite-site-rss-import',
			[ __CLASS__, 'render_import_page' ]
		);

		// Settings: general plugin settings and visual customisation.
		add_submenu_page(
			'newspack-lite-site',
			__( 'Settings', 'newspack-lite-site' ),
			__( 'Settings', 'newspack-lite-site' ),
			'manage_options',
			'newspack-lite-site',
			[ __CLASS__, 'render_settings_page' ]
		);

		self::register_entries_page();
	}

	/**
	 * Register the hidden RSS Entries page.
	 *
	 * It is added under the Lite Site menu and then removed from it, rather than
	 * registered with an empty parent, because core resets the parent file to an
	 * empty string for pages that live under an empty parent, which stops the
	 * Lite Site menu from highlighting.
	 */
	private static function register_entries_page() {
		global $_registered_pages;

		$post_type = get_post_type_object( Post_Type::POST_TYPE );
		if ( ! $post_type ) {
			return;
		}

		add_submenu_page(
			'newspack-lite-site',
			__( 'RSS Entries', 'newspack-lite-site' ),
			__( 'RSS Entries', 'newspack-lite-site' ),
			$post_type->cap->edit_posts,
			self::ENTRIES_PAGE_SLUG,
			[ __CLASS__, 'render_entries_page' ]
		);
		remove_submenu_page( 'newspack-lite-site', self::ENTRIES_PAGE_SLUG );

		add_action( self::ENTRIES_HOOK_SUFFIX, [ __CLASS__, 'render_entries_page' ] );
		$_registered_pages[ self::ENTRIES_HOOK_SUFFIX ] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Place the Lite Site menu directly below Newspack Network when it is present.
	 *
	 * @param string[] $menu_order Ordered top-level menu slugs.
	 * @return string[]
	 */
	public static function menu_order( $menu_order ): array {
		$anchor = array_search( 'newspack-network', $menu_order, true );
		$own    = array_search( 'newspack-lite-site', $menu_order, true );
		if ( false === $anchor || false === $own ) {
			return $menu_order;
		}
		array_splice( $menu_order, $own, 1 );
		$anchor = array_search( 'newspack-network', $menu_order, true );
		array_splice( $menu_order, $anchor + 1, 0, 'newspack-lite-site' );
		return $menu_order;
	}

	/**
	 * Add a "View Lite Site" link to the admin toolbar on the Lite Site screens.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 */
	public static function add_admin_bar_link( $wp_admin_bar ) {
		if ( ! Lite_Site::is_enabled() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! self::is_plugin_admin_page( $screen->id ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			[
				'id'    => 'view-lite-site',
				'title' => __( 'View Lite Site', 'newspack-lite-site' ),
				'href'  => esc_url( home_url( Lite_Site::get_url_base() ) ),
				'meta'  => [
					'target' => '_blank',
					'rel'    => 'noopener',
				],
			]
		);
	}

	/**
	 * Add body class to scope Newspack admin styles to the plugin's admin pages,
	 * and flag when they run alongside newspack-plugin, which brings the Newspack branding with it.
	 *
	 * @param string $classes Existing body classes.
	 * @return string Modified body classes.
	 */
	public static function admin_header_body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return $classes;
		}

		if ( self::is_plugin_admin_page( $screen->id ) ) {
			$classes .= ' newspack-lite-admin-header';
			if ( class_exists( '\Newspack\Newspack' ) ) {
				$classes .= ' newspack-lite-admin-header--bundled';
			}
		}

		return $classes;
	}

	/**
	 * Render the RSS Feed Import page.
	 *
	 * Mount point for the React RSS import app.
	 */
	public static function render_import_page() {
		echo '<div id="newspack-lite-app" data-page="rss-feed-import"></div>';
	}

	/**
	 * Render the RSS Entries page.
	 *
	 * Mount point for the React RSS entries app.
	 */
	public static function render_entries_page() {
		echo '<div id="newspack-lite-app" data-page="rss-entries"></div>';
	}

	/**
	 * Whether the current request is for the hidden RSS Entries page.
	 *
	 * @return bool
	 */
	private static function is_entries_page_request(): bool {
		global $plugin_page;
		return self::ENTRIES_PAGE_SLUG === $plugin_page;
	}

	/**
	 * Highlight the Lite Site menu on the hidden RSS Entries page.
	 *
	 * @param string $parent_file The parent menu file.
	 * @return string
	 */
	public static function filter_entries_parent_file( $parent_file ) {
		return self::is_entries_page_request() ? 'newspack-lite-site' : $parent_file;
	}

	/**
	 * Highlight the RSS Entries submenu item on the hidden RSS Entries page.
	 *
	 * @param string|null $submenu_file The submenu file.
	 * @return string|null
	 */
	public static function filter_entries_submenu_file( $submenu_file ) {
		return self::is_entries_page_request() ? 'edit.php?post_type=' . Post_Type::POST_TYPE : $submenu_file;
	}

	/**
	 * Restore the page title that WP drops for hidden admin pages.
	 *
	 * @param \WP_Screen $screen Current screen.
	 */
	public static function set_title_for_entries_page( $screen ) {
		if ( $screen instanceof \WP_Screen && self::ENTRIES_HOOK_SUFFIX === $screen->id ) {
			$GLOBALS['title'] = __( 'RSS Entries', 'newspack-lite-site' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Redirect the legacy RSS entries list table to the React page.
	 *
	 * @param \WP_Screen $screen Current screen.
	 */
	public static function maybe_redirect_legacy_entries_list( $screen ) {
		if ( ! is_admin() || ! $screen instanceof \WP_Screen || 'edit-' . Post_Type::POST_TYPE !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only nav check.
		$query  = wp_unslash( $_GET );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( ! self::should_redirect_legacy_entries_list( $query, $method ) ) {
			return;
		}

		wp_safe_redirect( self::get_entries_redirect_target( $query ) );
		exit;
	}

	/**
	 * Whether a request for the legacy RSS entries list should go to the React page.
	 *
	 * Only plain GET requests are redirected. Requests whose action or action2 carry a real
	 * value, rather than WP's -1 sentinel, are left to the classic screen.
	 *
	 * @param array  $query  Request query args.
	 * @param string $method Request method.
	 * @return bool
	 */
	public static function should_redirect_legacy_entries_list( array $query, string $method ): bool {
		if ( 'GET' !== strtoupper( $method ) ) {
			return false;
		}
		foreach ( [ 'action', 'action2' ] as $key ) {
			if ( ! isset( $query[ $key ] ) ) {
				continue;
			}
			if ( ! is_scalar( $query[ $key ] ) || ! in_array( (string) $query[ $key ], [ '', '-1' ], true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Build the React page URL, forwarding the supported legacy query args.
	 *
	 * @param array $query Legacy query args.
	 * @return string
	 */
	public static function get_entries_redirect_target( array $query ): string {
		$args = [ 'page' => self::ENTRIES_PAGE_SLUG ];
		foreach ( self::FORWARDED_LEGACY_ARGS as $key ) {
			if ( isset( $query[ $key ] ) && is_scalar( $query[ $key ] ) && '' !== (string) $query[ $key ] ) {
				$args[ $key ] = rawurlencode( sanitize_text_field( (string) $query[ $key ] ) );
			}
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Bootstrap data for the RSS Entries page.
	 *
	 * @return array
	 */
	private static function get_rss_entries_data(): array {
		global $wpdb;

		$feeds = [];
		foreach ( Post_Type::get_entry_feeds() as $id => $label ) {
			$feeds[] = [
				'value' => $id,
				'label' => $label,
			];
		}

		$tags = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DISTINCT t.term_id, t.name FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s AND p.post_type = %s ORDER BY t.name ASC",
				'post_tag',
				Post_Type::POST_TYPE
			)
		);

		return [
			'feeds'       => $feeds,
			'tags'        => array_map(
				fn( $tag ) => [
					'value' => (string) $tag->term_id,
					'label' => $tag->name,
				],
				$tags
			),
			'editUrl'     => admin_url( 'post.php' ),
			'addUrl'      => current_user_can( get_post_type_object( Post_Type::POST_TYPE )->cap->create_posts ) ? admin_url( 'post-new.php?post_type=' . Post_Type::POST_TYPE ) : '',
			'importUrl'   => admin_url( 'admin.php?page=newspack-lite-site-rss-import' ),
			'liteEnabled' => (bool) Lite_Site::is_enabled(),
		];
	}

	/**
	 * Render the Settings page.
	 *
	 * Mount point for the React settings app.
	 */
	public static function render_settings_page() {
		echo '<div id="newspack-lite-app" data-page="settings"></div>';
	}

	/**
	 * Check if the given hook belongs to a plugin admin page.
	 *
	 * @param string $hook Admin page hook suffix or screen ID.
	 * @return bool True if the hook matches one of the plugin's admin pages.
	 */
	private static function is_plugin_admin_page( string $hook ): bool {
		return in_array(
			$hook,
			[
				self::TOPLEVEL_HOOK_SUFFIX,
				'lite-site_page_newspack-lite-site-rss-import',
				self::ENTRIES_HOOK_SUFFIX,
			],
			true 
		);
	}

	/**
	 * Builds a hierarchically ordered category list with depth information.
	 *
	 * Categories are returned in tree order, where each parent category
	 * is immediately followed by its descendants recursively.
	 *
	 * Adds a temporary `depth` property to each WP_Term object representing
	 * its nesting level in the hierarchy.
	 *
	 * @param WP_Term[] $all_categories Flat array of category terms.
	 * @param int       $parent_id      Parent term ID to process.
	 * @param int       $depth          Current hierarchy depth. Top level is 0.
	 * @return WP_Term[] Ordered category list with added `depth` property.
	 */
	private static function build_ordered_categories( array $all_categories, int $parent_id, int $depth ): array {
		$ordered_categories = [];

		foreach ( $all_categories as $category ) {
			if ( (int) $category->parent !== $parent_id ) {
				continue;
			}

			$category->depth = $depth;
			$ordered_categories[] = $category;

			$children = self::build_ordered_categories(
				$all_categories,
				$category->term_id,
				$depth + 1
			);

			$ordered_categories = array_merge(
				$ordered_categories,
				$children
			);
		}

		return $ordered_categories;
	}

	/**
	 * Schedule a rewrite rule flush when the lite site is enabled/disabled or the URL base (Lite Site Suffix) changes.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous option value.
	 * @param mixed  $new_value New option value.
	 */
	public static function handle_updated_option( $option, $old_value, $new_value ) {
		if ( self::OPTION_NAME !== $option ) {
			return;
		}

		$url_base_changed = ( $old_value['url_base'] ?? '' ) !== ( $new_value['url_base'] ?? '' );
		$enabled_changed  = ! empty( $old_value['enabled'] ) !== ! empty( $new_value['enabled'] );

		if ( $url_base_changed || $enabled_changed ) {
			set_transient( 'nls_flush_rewrite_rules', true );
		}
	}

	/**
	 * Schedule a rewrite rule flush when the option is created for the first time.
	 *
	 * Covers the first-save case where update_option() falls back to add_option()
	 * and updated_option does not fire.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 */
	public static function handle_added_option( $option, $value ) {
		if ( self::OPTION_NAME !== $option ) {
			return;
		}
		set_transient( 'nls_flush_rewrite_rules', true );
	}

	/**
	 * Sanitize and validate settings before saving.
	 *
	 * @param array $settings Raw settings array from the REST API.
	 * @return array Sanitized settings ready to be stored in the database.
	 */
	public static function sanitize_settings( $settings ) {
		// Handle "All categories" selection.
		if ( ! empty( $settings['categories'] ) && in_array( '', $settings['categories'], true ) ) {
			$settings['categories'] = [];
		}

		return [
			'enabled'                => ! empty( $settings['enabled'] ),
			'url_base'               => sanitize_title( $settings['url_base'] ?? '' ),
			'posts_per_page'         => min( 100, max( 1, intval( $settings['posts_per_page'] ?? get_option( 'posts_per_page', 10 ) ) ) ),
			'categories'             => ! empty( $settings['categories'] ) ? array_map( 'intval', $settings['categories'] ) : [],
			'include_subcategories'  => isset( $settings['include_subcategories'] ) ? (bool) $settings['include_subcategories'] : true,
			'tags'                   => ! empty( $settings['tags'] ) ? array_map( 'intval', $settings['tags'] ) : [],
			'excluded_categories'    => ! empty( $settings['excluded_categories'] ) ? array_map( 'intval', $settings['excluded_categories'] ) : [],
			'excluded_tags'          => ! empty( $settings['excluded_tags'] ) ? array_map( 'intval', $settings['excluded_tags'] ) : [],
			'footer_html'            => wp_kses_post( trim( $settings['footer_html'] ?? '' ) ),
			'custom_css'             => $settings['custom_css'] ?? '',
			'external_links_new_tab' => isset( $settings['external_links_new_tab'] ) ? (bool) $settings['external_links_new_tab'] : true,
			'ga4_measurement_id'     => sanitize_text_field( $settings['ga4_measurement_id'] ?? '' ),
			'primary_color'          => ! empty( $settings['primary_color'] ) ? ( sanitize_hex_color( $settings['primary_color'] ) ?? '' ) : '',
			'font_import_url'        => self::sanitize_font_import_url( $settings['font_import_url'] ?? '' ),
			'font_body'              => sanitize_text_field( $settings['font_body'] ?? '' ),
		];
	}

	/**
	 * Sanitize a font import URL or <link> tag — always stores just the URL.
	 *
	 * @param string $value Raw input (URL or full <link> tag).
	 * @return string Sanitized URL, or empty string if invalid.
	 */
	private static function sanitize_font_import_url( $value ) {
		$value = trim( $value );
		if ( empty( $value ) ) {
			return '';
		}

		if ( str_contains( $value, '<link' ) ) {
			preg_match( '/href=["\']([^"\']+)["\']/', $value, $matches );
			$value = $matches[1] ?? '';
		}

		return esc_url_raw( $value );
	}
}
