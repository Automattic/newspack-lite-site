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
			Lite_Site::OPTION_NAME,
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
	}

	/**
	 * Render enabled field
	 */
	public static function render_enabled_field() {
	}

	/**
	 * Render URL base field
	 */
	public static function render_url_base_field() {
	}

	/**
	 * Render number of posts field
	 */
	public static function render_number_of_posts_field() {
	}

	/**
	 * Render categories field
	 */
	public static function render_categories_field() {
	}

	/**
	 * Render footer HTML field
	 */
	public static function render_footer_html_field() {
	}

	/**
	 * Render GA4 Measurement ID field
	 */
	public static function render_ga4_measurement_id_field() {
	}
}
