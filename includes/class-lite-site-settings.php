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
	 * Initialize the settings functionality.
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_styles' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_scripts' ] );
		add_action( 'admin_bar_menu', [ __CLASS__, 'add_admin_bar_link' ], 100 );
		add_filter( 'admin_body_class', [ __CLASS__, 'admin_header_body_class' ] );
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
				'default'           => [],
				'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
				'show_in_rest'      => [
					'schema' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => [
							'enabled'            => [
								'type'    => 'boolean',
								'default' => false,
							],
							'url_base'           => [
								'type'    => 'string',
								'default' => 'lite',
							],
							'posts_per_page'     => [
								'type'    => 'integer',
								'default' => 10,
								'minimum' => 1,
								'maximum' => 100,
							],
							'categories'         => [
								'type'    => 'array',
								'default' => [],
								'items'   => [ 'type' => 'integer' ],
							],
							'footer_html'        => [
								'type'    => 'string',
								'default' => '',
							],
							'ga4_measurement_id' => [
								'type'    => 'string',
								'default' => '',
							],
							'primary_color'      => [
								'type'    => 'string',
								'default' => '',
							],
							'font_import_url'    => [
								'type'    => 'string',
								'default' => '',
							],
							'font_body'          => [
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
		$allowed = [
			'toplevel_page_newspack-lite-site',
			'lite-site_page_newspack-lite-site-rss-import',
		];

		if ( ! in_array( $hook_suffix, $allowed, true ) ) {
			return;
		}

		// Admin page styles.
		$style_file = NEWSPACK_LITE_SITE_PLUGIN_DIR . 'dist/style.css';
		wp_enqueue_style(
			'newspack-lite-site-style',
			plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/style.css',
			[],
			file_exists( $style_file ) ? filemtime( $style_file ) : ''
		);

		// App bundle CSS extracted from the single JS bundle by webpack.
		$asset_file = NEWSPACK_LITE_SITE_PLUGIN_DIR . 'dist/index.asset.php';
		$version    = file_exists( $asset_file )
			? ( require $asset_file )['version']
			: '';

		wp_enqueue_style(
			'newspack-lite-site',
			plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/index.css',
			[],
			$version
		);
	}

	/**
	 * Enqueue admin scripts for the plugin's admin pages.
	 *
	 * A single JS bundle loads on both the Settings and RSS Feed Import pages.
	 * Bootstrap data for both sections is passed in one wp_localize_script call.
	 * Static config (e.g. interval labels) is defined in JS.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueue_admin_scripts( $hook_suffix ) {
		$allowed = [
			'toplevel_page_newspack-lite-site',
			'lite-site_page_newspack-lite-site-rss-import',
		];

		if ( ! in_array( $hook_suffix, $allowed, true ) ) {
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
				'defaultColor' => $default_color,
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
		// Top-level "Lite Site" menu entry in the WP admin sidebar.
		add_menu_page(
			__( 'Lite Site', 'newspack-lite-site' ),
			__( 'Lite Site', 'newspack-lite-site' ),
			'manage_options',
			'newspack-lite-site',
			[ __CLASS__, 'render_settings_page' ],
			'dashicons-admin-site',
			26
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

		// RSS Feed Import: manage scheduled RSS feeds that pull external content.
		add_submenu_page(
			'newspack-lite-site',
			__( 'RSS Feed Import', 'newspack-lite-site' ),
			__( 'RSS Feed Import', 'newspack-lite-site' ),
			'manage_options',
			'newspack-lite-site-rss-import',
			[ __CLASS__, 'render_import_page' ]
		);
	}

	/**
	 * Add a "View Lite Site" link to the admin toolbar on the Lite Site settings screen.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 */
	public static function add_admin_bar_link( $wp_admin_bar ) {
		if ( ! Lite_Site::is_enabled() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'toplevel_page_newspack-lite-site' !== $screen->id ) {
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
	 * Add body class to scope Newspack admin styles to the plugin's admin pages.
	 *
	 * @param string $classes Existing body classes.
	 * @return string Modified body classes.
	 */
	public static function admin_header_body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return $classes;
		}

		$our_hooks = [
			'toplevel_page_newspack-lite-site',
			'lite-site_page_newspack-lite-site-rss-import',
		];

		if ( in_array( $screen->id, $our_hooks, true ) ) {
			$classes .= ' newspack-lite-admin-header';
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
	 * Render the Settings page.
	 *
	 * Mount point for the React settings app.
	 */
	public static function render_settings_page() {
		echo '<div id="newspack-lite-app" data-page="settings"></div>';
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
	 * Sanitize and validate settings before saving.
	 *
	 * Flushes rewrite rules when url_base or enabled changes, since both affect
	 * URL routing. Sanitizes each field with the appropriate WordPress function.
	 *
	 * @param array $settings Raw settings array from the REST API.
	 * @return array Sanitized settings ready to be stored in the database.
	 */
	public static function sanitize_settings( $settings ) {
		$old_settings = get_option( self::OPTION_NAME, [] );

		// Only flush rewrite rules when settings that affect URL routing change.
		$url_base_changed = ( $old_settings['url_base'] ?? '' ) !== sanitize_title( $settings['url_base'] ?? '' );
		$enabled_changed  = ! empty( $old_settings['enabled'] ) !== ! empty( $settings['enabled'] );

		if ( $url_base_changed || $enabled_changed ) {
			flush_rewrite_rules(); // phpcs:ignore
		}

		// Handle "All categories" selection.
		if ( ! empty( $settings['categories'] ) && in_array( '', $settings['categories'], true ) ) {
			$settings['categories'] = [];
		}

		return [
			'enabled'            => ! empty( $settings['enabled'] ),
			'url_base'           => sanitize_title( $settings['url_base'] ),
			'posts_per_page'     => min( 100, max( 1, intval( $settings['posts_per_page'] ?? get_option( 'posts_per_page', 10 ) ) ) ),
			'categories'         => ! empty( $settings['categories'] ) ? array_map( 'intval', $settings['categories'] ) : [],
			'footer_html'        => wp_kses_post( trim( $settings['footer_html'] ) ),
			'ga4_measurement_id' => sanitize_text_field( $settings['ga4_measurement_id'] ),
			'primary_color'      => ! empty( $settings['primary_color'] ) ? ( sanitize_hex_color( $settings['primary_color'] ) ?? '' ) : '',
			'font_import_url'    => self::sanitize_font_import_url( $settings['font_import_url'] ?? '' ),
			'font_body'          => sanitize_text_field( $settings['font_body'] ?? '' ),
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
