<?php
/**
 * Lite Site settings and admin page
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

/**
 * Lite Site Settings class
 */
class Lite_Site_Settings {

	/**
	 * The option name for storing settings
	 */
	const OPTION_NAME = 'newspack_lite_site_settings';

	/**
	 * Initialize the settings functionality
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
	}

	/**
	 * Register settings
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

		add_settings_section(
			'newspack_lite_site_main',
			__( 'Settings', 'newspack-lite-site' ),
			'__return_null',
			'newspack_lite_site'
		);

		add_settings_field(
			'enabled',
			__( 'Enable Lite Site', 'newspack-lite-site' ),
			[ __CLASS__, 'render_enabled_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		add_settings_field(
			'url_base',
			__( 'URL Base', 'newspack-lite-site' ),
			[ __CLASS__, 'render_url_base_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		add_settings_field(
			'number_of_posts',
			__( 'Number of posts to display', 'newspack-lite-site' ),
			[ __CLASS__, 'render_number_of_posts_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		add_settings_field(
			'categories',
			__( 'Categories', 'newspack-lite-site' ),
			[ __CLASS__, 'render_categories_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		add_settings_field(
			'footer_html',
			__( 'Footer HTML', 'newspack-lite-site' ),
			[ __CLASS__, 'render_footer_html_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		add_settings_field(
			'ga4_measurement_id',
			__( 'GA4 Measurement ID', 'newspack-lite-site' ),
			[ __CLASS__, 'render_ga4_measurement_id_field' ],
			'newspack_lite_site',
			'newspack_lite_site_main'
		);

		add_settings_section(
			'newspack_lite_site_appearance',
			__( 'Appearance', 'newspack-lite-site' ),
			'__return_null',
			'newspack_lite_site'
		);

		add_settings_field(
			'primary_color',
			__( 'Primary Color', 'newspack-lite-site' ),
			[ __CLASS__, 'render_primary_color_field' ],
			'newspack_lite_site',
			'newspack_lite_site_appearance'
		);

		add_settings_field(
			'font_import_url',
			__( 'Font Import URL', 'newspack-lite-site' ),
			[ __CLASS__, 'render_font_import_url_field' ],
			'newspack_lite_site',
			'newspack_lite_site_appearance'
		);

		add_settings_field(
			'font_body',
			__( 'Body Font', 'newspack-lite-site' ),
			[ __CLASS__, 'render_font_body_field' ],
			'newspack_lite_site',
			'newspack_lite_site_appearance'
		);
	}

	/**
	 * Add menu page
	 */
	public static function add_menu_page() {
		add_options_page(
			__( 'Lite Site', 'newspack-plugin' ),
			__( 'Lite Site', 'newspack-plugin' ),
			'manage_options',
			'newspack-lite-site',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	/**
	 * Render settings page
	 */
	public static function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Lite Site', 'newspack-lite-site' ); ?></h1>
			<p><?php esc_html_e( 'Lite Site is a text-only version of this website that loads faster and uses less data.', 'newspack-lite-site' ); ?></p>
			<p><?php esc_html_e( 'It’s designed to allow your readers to still be able to access your content despite connectivity issues, poor network coverage, or in the event of natural disasters and emergencies.', 'newspack-lite-site' ); ?></p>
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
	 * Render enabled field
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
	 * Render URL base field
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
	 * Render number of posts field
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
	 * Render categories field
	 */
	public static function render_categories_field() {
		$settings            = get_option( self::OPTION_NAME, [] );
		$selected_categories = ! empty( $settings['categories'] ) ? (array) $settings['categories'] : [];
		$categories          = get_categories( [ 'hide_empty' => false ] );
		?>
		<select
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[categories][]"
			multiple
			class="regular-text"
			style="min-height: 100px;"
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
	 * Render footer HTML field
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
	 * Render GA4 Measurement ID field
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
	 * Render primary color field
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
		<button type="button" id="nls-reset-color" class="button" style="margin-left: 8px;">
			<?php esc_html_e( 'Reset to default', 'newspack-lite-site' ); ?>
		</button>
		<script>
			( function() {
				var picker       = document.getElementById( 'nls-primary-color-picker' );
				var resetBtn     = document.getElementById( 'nls-reset-color' );
				var defaultColor = '<?php echo esc_js( $default_color ); ?>';

				resetBtn.addEventListener( 'click', function() {
					picker.value = defaultColor;
				} );
			} )();
		</script>
		<?php
	}

	/**
	 * Render font import URL field
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
	 * Render body font field
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
	 * Sanitize settings
	 *
	 * @param array $settings The settings to sanitize.
	 * @return array The sanitized settings.
	 */
	public static function sanitize_settings( $settings ) {
		flush_rewrite_rules(); // phpcs:ignore

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
	 * Sanitize a font import URL or <link> tag — always stores just the URL
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
