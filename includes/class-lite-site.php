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
}
