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
		add_action( 'init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_styles' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_scripts' ] );
		add_action( 'admin_bar_menu', [ __CLASS__, 'add_admin_bar_link' ], 100 );
		add_action( 'in_admin_header', [ __CLASS__, 'render_admin_header' ] );
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
		$settings_hook = 'toplevel_page_newspack-lite-site';
		$import_hook   = 'lite-site_page_newspack-lite-site-rss-import';

		if ( ! in_array( $hook_suffix, [ $settings_hook, $import_hook ], true ) ) {
			return;
		}

		// Admin header CSS shared across the Settings and RSS Feed Import pages.
		$header_asset_file = NEWSPACK_LITE_SITE_PLUGIN_DIR . 'dist/admin-header.asset.php';
		$header_version    = file_exists( $header_asset_file )
			? ( require $header_asset_file )['version']
			: '';

		wp_enqueue_style(
			'newspack-lite-site-header',
			plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/admin-header.css',
			[ 'wp-components' ],
			$header_version
		);

		// Shared admin page CSS.
		wp_enqueue_style(
			'newspack-lite-site-style',
			plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/style.css',
			[],
			filemtime( NEWSPACK_LITE_SITE_PLUGIN_DIR . 'dist/style.css' )
		);

		// Settings page CSS.
		if ( $settings_hook === $hook_suffix ) {
			$index_asset_file = NEWSPACK_LITE_SITE_PLUGIN_DIR . 'dist/index.asset.php';
			$index_version    = file_exists( $index_asset_file )
				? ( require $index_asset_file )['version']
				: '';

			wp_enqueue_style(
				'newspack-lite-site-app',
				plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/index.css',
				[],
				$index_version
			);
		}
	}

	/**
	 * Enqueue admin scripts for the plugin's pages.
	 *
	 * The admin-header bundle is shared across both pages; each page also receives
	 * its own app bundle with bootstrap data.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueue_admin_scripts( $hook_suffix ) {
		$settings_hook = 'toplevel_page_newspack-lite-site';
		$import_hook   = 'lite-site_page_newspack-lite-site-rss-import';

		if ( ! in_array( $hook_suffix, [ $settings_hook, $import_hook ], true ) ) {
			return;
		}

		// Admin header bundle, enqueued on the Settings and RSS Feed Import pages.
		$header_asset_file = NEWSPACK_LITE_SITE_PLUGIN_DIR . 'dist/admin-header.asset.php';
		$header_asset      = file_exists( $header_asset_file )
			? require $header_asset_file
			: [
				'dependencies' => [ 'wp-element' ],
				'version'      => '',
			];

		wp_enqueue_script(
			'newspack-lite-site-header',
			plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/admin-header.js',
			$header_asset['dependencies'],
			$header_asset['version'],
			true
		);

		$is_settings = ( $settings_hook === $hook_suffix );
		$current_tab = sanitize_key( filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS ) ?? 'general' );

		$tabs = [];
		if ( $is_settings ) {
			$tabs = [
				[
					'id'       => 'general',
					'label'    => __( 'General', 'newspack-lite-site' ),
					'href'     => admin_url( 'admin.php?page=newspack-lite-site' ),
					'isActive' => ( 'general' === $current_tab || '' === $current_tab ),
				],
				[
					'id'       => 'appearance',
					'label'    => __( 'Appearance', 'newspack-lite-site' ),
					'href'     => admin_url( 'admin.php?page=newspack-lite-site&tab=appearance' ),
					'isActive' => ( 'appearance' === $current_tab ),
				],
			];
		}

		$title = $is_settings
			? __( 'Settings', 'newspack-lite-site' )
			: __( 'RSS Feed Import', 'newspack-lite-site' );

		wp_localize_script(
			'newspack-lite-site-header',
			'NewspackLiteSiteAdminHeader',
			[
				'title' => $title,
				'tabs'  => $tabs,
			]
		);

		// RSS Import app bundle, enqueued on the RSS Feed Import page only.
		if ( ! $is_settings ) {
			$interval_labels = RSS_Importer::get_interval_labels();
			$rss_asset_file  = NEWSPACK_LITE_SITE_PLUGIN_DIR . 'dist/rss-feed-import.asset.php';
			$rss_asset       = file_exists( $rss_asset_file )
				? require $rss_asset_file
				: [
					'dependencies' => [],
					'version'      => '',
				];

			wp_enqueue_script(
				'newspack-lite-site-rss-import',
				plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/rss-feed-import.js',
				$rss_asset['dependencies'],
				$rss_asset['version'],
				true
			);

			wp_enqueue_style(
				'newspack-lite-site-rss-import',
				plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/rss-feed-import.css',
				[ 'wp-components' ],
				$rss_asset['version']
			);

			wp_localize_script(
				'newspack-lite-site-rss-import',
				'NewspackLiteSiteRssImport',
				[
					'cronDisabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
					'intervals'    => array_values(
						array_map(
							fn( $value, $label ) => compact( 'value', 'label' ),
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
			return;
		}

		// Settings app bundle, enqueued on the Settings page only.
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

		wp_set_script_translations( 'newspack-lite-site', 'newspack-lite-site' );

		// Build hierarchy-ordered category list for the React categories field.
		$all_categories     = get_categories( [ 'hide_empty' => false ] );
		$ordered_categories = self::build_ordered_categories( $all_categories, 0, 0 );

		$theme_color   = Lite_Site::get_theme_primary_color();
		$default_color = 'currentcolor' !== $theme_color ? $theme_color : '#808080';

		wp_localize_script(
			'newspack-lite-site',
			'NewspackLiteSiteSettings',
			[
				'defaultColor' => $default_color,
				'tab'          => $current_tab,
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
	 * Add body class for admin header pages (enables sticky positioning in CSS).
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
	 * Render the Newspack-style admin header skeleton.
	 *
	 * Hooked to `in_admin_header` so it renders outside `.wrap`, giving the
	 * header full viewport width. The React admin-header bundle mounts into
	 * #newspack-lite-admin-header and replaces this loading skeleton.
	 *
	 * SVG path data matches the NewspackIcon React component (viewBox 0 0 24 24).
	 */
	public static function render_admin_header() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$settings_hook = 'toplevel_page_newspack-lite-site';
		$import_hook   = 'lite-site_page_newspack-lite-site-rss-import';

		if ( ! in_array( $screen->id, [ $settings_hook, $import_hook ], true ) ) {
			return;
		}

		$is_settings = ( $settings_hook === $screen->id );
		$title       = $is_settings
			? __( 'Settings', 'newspack-lite-site' )
			: __( 'RSS Feed Import', 'newspack-lite-site' );
		?>
		<div id="newspack-lite-admin-header">
			<div class="newspack-lite-header">
				<div class="newspack-lite-header__inner">
					<div class="newspack-lite-title">
						<svg xmlns="http://www.w3.org/2000/svg" height="36" width="36" viewBox="0 0 24 24" class="newspack-lite-icon" aria-hidden="true" focusable="false">
							<path fill-rule="evenodd" clip-rule="evenodd" d="M24 12C24 18.6271 18.6271 24 12 24C5.37213 24 0 18.6271 0 12C0 5.3729 5.3729 0 12 0C18.6271 0 24 5.3729 24 12ZM17.4545 17.4546L6.54545 6.54545V17.4545H8.72727V11.8182L14.3636 17.4546H17.4545ZM11.2727 8.18182H17.4545V6.54545H9.63636L11.2727 8.18182ZM17.4545 11.2727H14.3636L12.7273 9.63636H17.4545V11.2727ZM17.4545 12.7273V14.3636L15.8182 12.7273H17.4545Z"/>
						</svg>
						<div><h2><?php echo esc_html( $title ); ?></h2></div>
					</div>
				</div>
			</div>
		</div>
		<?php if ( $is_settings ) : ?>
		<div id="newspack-lite-tabs-nav"></div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the Settings page.
	 *
	 * Mount point for the React settings app. Title and tab nav are rendered by render_admin_header().
	 */
	public static function render_settings_page() {
		?>
		<div class="wrap">
			<div id="newspack-lite-settings-app"></div>
		</div>
		<?php
	}

	/**
	 * Render the RSS Feed Import page.
	 *
	 * Mount point for the React RSS import app. Title is rendered by render_admin_header().
	 */
	public static function render_import_page() {
		?>
		<div class="wrap">
			<div id="newspack-lite-rss-import-app"></div>
		</div>
		<?php
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
		$url_base_changed = ( $old_settings['url_base'] ?? '' ) !== sanitize_title( $settings['url_base'] );
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
