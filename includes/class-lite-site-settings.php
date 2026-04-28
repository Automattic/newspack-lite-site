<?php
/**
 * Lite Site settings and admin page.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

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
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
	}

	/**
	 * Register settings.
	 *
	 * Registers the plugin option and exposes fields for general settings (enabled state,
	 * URL base, post count, categories, footer HTML, GA4 ID) and appearance (primary colour, font).
	 */
	public static function register_settings() {
		register_setting(
			'newspack_lite_site',
			self::OPTION_NAME,
			[
				'type'              => 'object',
				'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
			]
		);

		// General settings: controls how the lite site behaves and what content it serves.
		add_settings_section(
			'newspack_lite_site_main',
			__( 'Settings', 'newspack-lite-site' ),
			'__return_null',
			'newspack_lite_site'
		);

		// Toggle to activate or deactivate the lite site feature entirely.
		add_settings_field(
			'enabled',
			__( 'Enable Lite Site', 'newspack-lite-site' ),
			[ __CLASS__, 'render_enabled_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		// URL slug appended to post permalinks to serve the lite version.
		add_settings_field(
			'url_base',
			__( 'URL Base', 'newspack-lite-site' ),
			[ __CLASS__, 'render_url_base_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		// Maximum number of posts shown on the lite site archive page.
		add_settings_field(
			'number_of_posts',
			__( 'Number of posts to display', 'newspack-lite-site' ),
			[ __CLASS__, 'render_number_of_posts_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		// Restricts the archive to specific categories; empty means all categories are included.
		add_settings_field(
			'categories',
			__( 'Categories', 'newspack-lite-site' ),
			[ __CLASS__, 'render_categories_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		// Custom HTML injected into the footer of every lite site page.
		add_settings_field(
			'footer_html',
			__( 'Footer HTML', 'newspack-lite-site' ),
			[ __CLASS__, 'render_footer_html_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		// GA4 Measurement ID for tracking on lite pages.
		add_settings_field(
			'ga4_measurement_id',
			__( 'GA4 Measurement ID', 'newspack-lite-site' ),
			[ __CLASS__, 'render_ga4_measurement_id_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		// Appearance settings: controls the visual style of lite site pages.
		add_settings_section(
			'newspack_lite_site_appearance',
			__( 'Appearance', 'newspack-lite-site' ),
			'__return_null',
			'newspack_lite_site'
		);

		// Overrides the theme's primary colour on lite pages.
		add_settings_field(
			'primary_color',
			__( 'Primary Color', 'newspack-lite-site' ),
			[ __CLASS__, 'render_primary_color_field' ],
			'newspack_lite_site',
			'newspack_lite_site_appearance'
		);

		// URL (or <link> tag) for loading a web font from an external provider on lite pages.
		add_settings_field(
			'font_import_url',
			__( 'Font Import URL', 'newspack-lite-site' ),
			[ __CLASS__, 'render_font_import_url_field' ],
			'newspack_lite_site',
			'newspack_lite_site_appearance'
		);

		// CSS font-family name applied to body text; must match the imported font.
		add_settings_field(
			'font_body',
			__( 'Body Font', 'newspack-lite-site' ),
			[ __CLASS__, 'render_font_body_field' ],
			'newspack_lite_site',
			'newspack_lite_site_appearance'
		);
	}

	/**
	 * Enqueue admin assets for the plugin's settings pages.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		$settings_hook = 'toplevel_page_newspack-lite-site';
		$import_hook   = 'lite-site_page_newspack-lite-site-rss-import';

		if ( ! in_array( $hook_suffix, [ $settings_hook, $import_hook ], true ) ) {
			return;
		}

		wp_enqueue_style(
			'newspack-lite-site-settings',
			plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/settings-style.css',
			[],
			filemtime( NEWSPACK_LITE_SITE_PLUGIN_DIR . 'dist/settings-style.css' )
		);

		if ( $settings_hook === $hook_suffix ) {
			$theme_color   = Lite_Site::get_theme_primary_color();
			$default_color = 'currentcolor' !== $theme_color ? $theme_color : '#808080';

			wp_enqueue_script(
				'newspack-lite-site-settings',
				plugin_dir_url( NEWSPACK_LITE_SITE_PLUGIN_FILE ) . 'dist/settings.js',
				[],
				filemtime( NEWSPACK_LITE_SITE_PLUGIN_DIR . 'dist/settings.js' ),
				true
			);

			wp_localize_script(
				'newspack-lite-site-settings',
				'nlsAdmin',
				[
					'defaultColor' => $default_color,
				]
			);
		}
	}

	/**
	 * Add menu page.
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

		// Settings & Appearance: general plugin settings and visual customisation.
		add_submenu_page(
			'newspack-lite-site',
			__( 'Settings & Appearance', 'newspack-lite-site' ),
			__( 'Settings & Appearance', 'newspack-lite-site' ),
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
	 * Render settings page.
	 */
	public static function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings & Appearance', 'newspack-lite-site' ); ?></h1>
			<p><?php esc_html_e( 'Lite Site is a text-only version of this website that loads faster and uses less data.', 'newspack-lite-site' ); ?></p>
			<p><?php esc_html_e( 'It\'s designed to allow your readers to still be able to access your content despite connectivity issues, poor network coverage, or in the event of natural disasters and emergencies.', 'newspack-lite-site' ); ?></p>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'newspack_lite_site' );
				do_settings_sections( 'newspack_lite_site' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render RSS Feed Import page.
	 */
	public static function render_import_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'RSS Feed Import', 'newspack-lite-site' ); ?></h1>
			<?php self::render_import_section(); ?>
		</div>
		<?php
	}

	/**
	 * Render enabled field.
	 */
	public static function render_enabled_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]"
				value="1"
				<?php checked( ! empty( $settings['enabled'] ) ); ?>
			>
			<?php esc_html_e( 'Enable lite site feature', 'newspack-lite-site' ); ?>
		</label>
		<?php
	}

	/**
	 * Render URL base field.
	 */
	public static function render_url_base_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$url_base = ! empty( $settings['url_base'] ) ? $settings['url_base'] : 'lite';
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[url_base]"
			value="<?php echo esc_attr( $url_base ); ?>"
			class="regular-text"
		>
		<p class="description">
			<?php
			printf(
				/* translators: %s: is the site URL without a trailing slash, ex: https://example.com */
				esc_html__( 'The URL base for the lite site (e.g. "lite" for %s/article-slug/lite).', 'newspack-lite-site' ),
				esc_url( untrailingslashit( home_url() ) )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render number of posts field.
	 */
	public static function render_number_of_posts_field() {
		$settings        = get_option( self::OPTION_NAME, [] );
		$number_of_posts = ! empty( $settings['number_of_posts'] ) ? intval( $settings['number_of_posts'] ) : 20;
		?>
		<input
			type="number"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[number_of_posts]"
			value="<?php echo esc_attr( $number_of_posts ); ?>"
			min="1"
			max="100"
			step="1"
		>
		<?php
	}

	/**
	 * Render categories field.
	 */
	public static function render_categories_field() {
		$settings            = get_option( self::OPTION_NAME, [] );
		$selected_categories = ! empty( $settings['categories'] ) ? (array) $settings['categories'] : [];
		$categories          = get_categories( [ 'hide_empty' => false ] );
		?>
		<select
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[categories][]"
			multiple
			class="regular-text nls-categories-select"
		>
			<option value="" <?php selected( empty( $selected_categories ) ); ?>>
				<?php esc_html_e( 'All categories', 'newspack-lite-site' ); ?>
			</option>
			<?php foreach ( $categories as $category ) : ?>
				<option
					value="<?php echo esc_attr( $category->term_id ); ?>"
					<?php selected( in_array( $category->term_id, $selected_categories, true ) ); ?>
				>
					<?php echo esc_html( $category->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Select categories to include.', 'newspack-lite-site' ); ?>
		</p>
		<?php
	}

	/**
	 * Render footer HTML field.
	 */
	public static function render_footer_html_field() {
		$settings    = get_option( self::OPTION_NAME, [] );
		$footer_html = ! empty( $settings['footer_html'] ) ? $settings['footer_html'] : '';
		?>
		<textarea
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[footer_html]"
			rows="5"
			class="large-text"
		><?php echo esc_textarea( $footer_html ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'HTML to be displayed in the footer of lite site pages.', 'newspack-lite-site' ); ?>
		</p>
		<?php
	}

	/**
	 * Render GA4 Measurement ID field.
	 */
	public static function render_ga4_measurement_id_field() {
		$settings           = get_option( self::OPTION_NAME, [] );
		$ga4_measurement_id = ! empty( $settings['ga4_measurement_id'] ) ? $settings['ga4_measurement_id'] : '';
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ga4_measurement_id]"
			value="<?php echo esc_attr( $ga4_measurement_id ); ?>"
			class="regular-text"
			placeholder="G-XXXXXXXXXX"
		>
		<p class="description">
			<?php esc_html_e( 'Google Analytics 4 Measurement ID. Since lite pages strip all scripts, this is used to re-inject GA4 tracking.', 'newspack-lite-site' ); ?>
		</p>
		<?php
	}

	/**
	 * Render primary color field.
	 */
	public static function render_primary_color_field() {
		$settings      = get_option( self::OPTION_NAME, [] );
		$saved_color   = ! empty( $settings['primary_color'] ) ? $settings['primary_color'] : '';
		$has_override  = ! empty( $saved_color );
		$theme_color   = Lite_Site::get_theme_primary_color();
		$default_color = 'currentcolor' !== $theme_color ? $theme_color : '#808080';
		$picker_value  = $has_override ? $saved_color : $default_color;
		?>
		<input
			type="color"
			id="nls-primary-color-picker"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[primary_color]"
			value="<?php echo esc_attr( $picker_value ); ?>"
		>
		<button type="button" id="nls-reset-color" class="button">
			<?php esc_html_e( 'Reset to default', 'newspack-lite-site' ); ?>
		</button>
		<?php
	}

	/**
	 * Render font import URL field.
	 */
	public static function render_font_import_url_field() {
		$settings         = get_option( self::OPTION_NAME, [] );
		$font_import_url  = ! empty( $settings['font_import_url'] ) ? $settings['font_import_url'] : '';
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[font_import_url]"
			value="<?php echo esc_attr( $font_import_url ); ?>"
			class="large-text"
			placeholder="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap"
		>
		<p class="description">
			<?php esc_html_e( 'URL or &lt;link&gt; tag from your font provider (Google Fonts, Adobe Fonts, etc.). The font will be loaded on lite site pages.', 'newspack-lite-site' ); ?>
		</p>
		<?php
	}

	/**
	 * Render body font field.
	 */
	public static function render_font_body_field() {
		$settings  = get_option( self::OPTION_NAME, [] );
		$font_body = ! empty( $settings['font_body'] ) ? $settings['font_body'] : '';
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[font_body]"
			value="<?php echo esc_attr( $font_body ); ?>"
			class="regular-text"
			placeholder="Open Sans"
		>
		<p class="description">
			<?php esc_html_e( 'Font name to use for body text, must match the imported font (e.g. "Open Sans"). Leave empty to use the system font.', 'newspack-lite-site' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $settings The settings to sanitize.
	 * @return array The sanitized settings.
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
			'number_of_posts'    => min( 100, max( 1, intval( $settings['number_of_posts'] ) ) ),
			'categories'         => ! empty( $settings['categories'] ) ? array_map( 'intval', $settings['categories'] ) : [],
			'footer_html'        => wp_kses_post( $settings['footer_html'] ),
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

	/**
	 * Render the RSS importer configuration section.
	 *
	 * Displays an "Add Feed" form, followed by a management table of all
	 * configured feeds with pause/resume, edit interval, and delete actions.
	 */
	public static function render_import_section() {
		$feeds           = RSS_Importer::get_feeds();
		$cron_disabled   = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$date_format     = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$interval_labels = RSS_Importer::get_interval_labels();

		$notice = get_transient( 'nls_rss_importer_notice' );
		if ( $notice ) {
			delete_transient( 'nls_rss_importer_notice' );
		}
		?>
		<?php if ( $cron_disabled ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php esc_html_e( 'WP-Cron is disabled. Automatic imports require a server cron job.', 'newspack-lite-site' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> inline is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>
		<?php

		self::render_add_feed_form( $interval_labels );
		self::render_feeds_table( $feeds, $date_format, $interval_labels, $cron_disabled );
	}

	/**
	 * Render the Add Feed form.
	 *
	 * @param array $interval_labels Associative array of interval keys to labels.
	 */
	private static function render_add_feed_form( $interval_labels ) {
		?>
		<h2><?php esc_html_e( 'Add Feed', 'newspack-lite-site' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'nls_rss_add_feed' ); ?>
			<input type="hidden" name="action" value="nls_rss_add_feed">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="rss_importer_feed_url"><?php esc_html_e( 'Feed URL', 'newspack-lite-site' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							id="rss_importer_feed_url"
							name="rss_importer_feed_url"
							class="large-text"
							placeholder="https://example.com/feed/"
							required
						>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rss_importer_interval"><?php esc_html_e( 'Frequency', 'newspack-lite-site' ); ?></label>
					</th>
					<td>
						<select id="rss_importer_interval" name="rss_importer_interval">
							<?php foreach ( $interval_labels as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'daily', $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Add Feed', 'newspack-lite-site' ), 'primary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render the Scheduled Feeds management table.
	 *
	 * @param array  $feeds           All configured feeds.
	 * @param string $date_format     WordPress date+time format string.
	 * @param array  $interval_labels Associative array of interval keys to labels.
	 * @param bool   $cron_disabled   Whether WP-Cron is disabled.
	 */
	private static function render_feeds_table( $feeds, $date_format, $interval_labels, $cron_disabled ) {
		?>
		<hr class="nls-section-divider">

		<h2><?php esc_html_e( 'Scheduled Feeds', 'newspack-lite-site' ); ?></h2>

		<?php if ( empty( $feeds ) ) : ?>
			<p><?php esc_html_e( 'No feeds configured. Add one above.', 'newspack-lite-site' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Feed URL', 'newspack-lite-site' ); ?></th>
						<th scope="col" class="nls-col-frequency"><?php esc_html_e( 'Frequency', 'newspack-lite-site' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last Run', 'newspack-lite-site' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Next Run', 'newspack-lite-site' ); ?></th>
						<th scope="col" class="nls-col-status"><?php esc_html_e( 'Status', 'newspack-lite-site' ); ?></th>
						<th scope="col" class="nls-col-actions"><?php esc_html_e( 'Actions', 'newspack-lite-site' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $feeds as $feed_id => $feed ) : ?>
						<?php self::render_feed_row( $feed_id, $feed, $date_format, $interval_labels, $cron_disabled ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a single row in the feeds table.
	 *
	 * @param string $feed_id         The feed ID.
	 * @param array  $feed            The feed configuration array.
	 * @param string $date_format     WordPress date+time format string.
	 * @param array  $interval_labels Associative array of interval keys to labels.
	 * @param bool   $cron_disabled   Whether WP-Cron is disabled.
	 */
	private static function render_feed_row( $feed_id, $feed, $date_format, $interval_labels, $cron_disabled ) {
		$next_run  = wp_next_scheduled( RSS_Importer::CRON_HOOK, [ $feed_id ] );
		$is_active = 'active' === $feed['status'];
		?>
		<tr>
			<td><strong><?php echo esc_html( $feed['feed_url'] ); ?></strong></td>
			<td><?php echo esc_html( $interval_labels[ $feed['interval'] ] ?? $feed['interval'] ); ?></td>
			<td>
				<?php
				if ( is_null( $feed['last_run'] ) ) {
					echo '<em>' . esc_html__( 'Never', 'newspack-lite-site' ) . '</em>';
				} elseif ( isset( $feed['last_result']['error'] ) ) {
					printf(
						/* translators: 1: date/time of last run, 2: error message */
						esc_html__( '%1$s — Error: %2$s', 'newspack-lite-site' ),
						esc_html( wp_date( $date_format, $feed['last_run'] ) ),
						esc_html( $feed['last_result']['error'] )
					);
				} else {
					$imported   = absint( $feed['last_result']['imported'] ?? 0 );
					$failed     = absint( $feed['last_result']['failed'] ?? 0 );
					$up_to_date = ! empty( $feed['last_result']['up_to_date'] );
					$date_str   = wp_date( $date_format, $feed['last_run'] );

					if ( $up_to_date && 0 === $imported ) {
						printf(
							/* translators: %s: date/time of last run */
							esc_html__( '%s — Up to date', 'newspack-lite-site' ),
							esc_html( $date_str )
						);
					} elseif ( $up_to_date ) {
						printf(
							/* translators: 1: date/time of last run, 2: number imported */
							esc_html__( '%1$s — %2$d imported, up to date', 'newspack-lite-site' ),
							esc_html( $date_str ),
							absint( $imported )
						);
					} elseif ( $failed > 0 ) {
						printf(
							/* translators: 1: date/time of last run, 2: number imported, 3: number failed */
							esc_html__( '%1$s — %2$d imported, %3$d failed', 'newspack-lite-site' ),
							esc_html( $date_str ),
							absint( $imported ),
							absint( $failed )
						);
					} else {
						printf(
							/* translators: 1: date/time of last run, 2: number imported */
							esc_html__( '%1$s — %2$d imported', 'newspack-lite-site' ),
							esc_html( $date_str ),
							absint( $imported )
						);
					}
				}
				?>
			</td>
			<td>
				<?php if ( $next_run && ! $cron_disabled ) : ?>
					<?php echo esc_html( wp_date( $date_format, $next_run ) ); ?>
				<?php else : ?>
					&mdash;
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $is_active ) : ?>
					<span class="nls-feed-status--active"><?php esc_html_e( 'Active', 'newspack-lite-site' ); ?></span>
				<?php else : ?>
					<span class="nls-feed-status--paused"><?php esc_html_e( 'Paused', 'newspack-lite-site' ); ?></span>
				<?php endif; ?>
			</td>
			<td>
				<?php
				$toggle_action = $is_active ? 'pause' : 'resume';
				$toggle_label  = $is_active ? __( 'Pause', 'newspack-lite-site' ) : __( 'Resume', 'newspack-lite-site' );
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nls-inline-form">
					<?php wp_nonce_field( 'nls_rss_feed_action' ); ?>
					<input type="hidden" name="action" value="nls_rss_feed_action">
					<input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed_id ); ?>">
					<input type="hidden" name="feed_action" value="<?php echo esc_attr( $toggle_action ); ?>">
					<?php submit_button( $toggle_label, 'small', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nls-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this feed?', 'newspack-lite-site' ) ); ?>');">
					<?php wp_nonce_field( 'nls_rss_feed_action' ); ?>
					<input type="hidden" name="action" value="nls_rss_feed_action">
					<input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed_id ); ?>">
					<input type="hidden" name="feed_action" value="delete">
					<?php submit_button( __( 'Delete', 'newspack-lite-site' ), 'small delete', 'submit', false ); ?>
				</form>
			</td>
		</tr>
		<?php
	}
}
