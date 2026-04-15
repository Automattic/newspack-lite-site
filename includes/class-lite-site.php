<?php
/**
 * Lite Site functionality
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

/**
 * Lite Site class
 */
class Lite_Site {
	/**
	 * The option name for storing settings
	 */
	const OPTION_NAME = 'newspack_lite_site_settings';

	/**
	 * Initialize the lite site functionality
	 */
	public static function init() {
		// Only register rewrite rules if the feature is enabled.
		if ( self::is_enabled() ) {
			add_action( 'init', [ __CLASS__, 'register_rewrite_rules' ] );
			add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
			add_action( 'template_redirect', [ __CLASS__, 'handle_lite_site_templates' ] );
		}
	}

	/**
	 * Check if the lite site feature is enabled
	 */
	public static function is_enabled() {
		$settings = get_option( self::OPTION_NAME, [] );
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Get the URL base (slug used for lite pages)
	 */
	public static function get_url_base() {
		$settings = get_option( self::OPTION_NAME, [] );
		return ! empty( $settings['url_base'] ) ? $settings['url_base'] : 'lite';
	}

	/**
	 * Register rewrite rules for lite site pages
	 */
	public static function register_rewrite_rules() {
		$url_base = self::get_url_base();

		// Archive: /{url_base}.
		add_rewrite_rule(
			'^' . $url_base . '/?$',
			'index.php?is_lite=archive',
			'top'
		);

		// Single: /{post-slug}/{url_base}.
		add_rewrite_rule(
			'^(.+)/' . $url_base . '/?$',
			'index.php?is_lite=single&lite_path=$matches[1]',
			'top'
		);
	}

	/**
	 * Register custom query variables
	 *
	 * @param array $vars The array of query variables.
	 * @return array The modified array of query variables.
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'is_lite';
		$vars[] = 'lite_path';
		return $vars;
	}

	/**
	 * Handle template routing for lite site pages
	 */
	public static function handle_lite_site_templates() {
		$is_lite = get_query_var( 'is_lite' );

		if ( ! $is_lite ) {
			return;
		}

		// Disable all other output.
		remove_all_actions( 'wp_head' );
		remove_all_actions( 'wp_footer' );

		if ( 'archive' === $is_lite ) {
			include_once NEWSPACK_LITE_SITE_PLUGIN_DIR . 'templates/archive.php';
			exit;
		}

		if ( 'single' === $is_lite ) {
			include_once NEWSPACK_LITE_SITE_PLUGIN_DIR . 'templates/single.php';
			exit;
		}
	}
}
