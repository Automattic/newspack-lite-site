<?php
/**
 * Shared base test case for the Newspack Lite Site test suite.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;
use Newspack_Lite_Site\Lite_Site_Settings;

/**
 * Provides helpers for reaching private static members and for managing the
 * plugin's settings option, which `Lite_Site` caches in a private static
 * property for the lifetime of the request.
 */
abstract class Lite_Site_TestCase extends WP_UnitTestCase {

	/**
	 * Reset the settings cache before every test so option writes are visible.
	 */
	public function set_up() {
		parent::set_up();
		self::reset_settings_cache();
	}

	/**
	 * Clear the settings option and its in-memory cache after every test.
	 */
	public function tear_down() {
		delete_option( Lite_Site_Settings::OPTION_NAME );
		self::reset_settings_cache();
		self::remove_lite_rewrite_rules();
		parent::tear_down();
	}

	/**
	 * Register the lite rewrite rules, as a site that has had Lite Site on does.
	 *
	 * The rules take effect at the next rewrite flush, such as the one
	 * set_permalink_structure() runs.
	 */
	protected static function register_lite_rewrite_rules() {
		update_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME, true );
		Lite_Site::register_rewrite_rules();
	}

	/**
	 * Take the lite rewrite rules back out of `$wp_rewrite`, which keeps the
	 * rules added to it for the whole run.
	 */
	protected static function remove_lite_rewrite_rules() {
		global $wp_rewrite;
		foreach ( $wp_rewrite->extra_rules_top as $regex => $query ) {
			if ( 0 === strpos( $query, 'index.php?is_lite=' ) ) {
				unset( $wp_rewrite->extra_rules_top[ $regex ] );
			}
		}
	}

	/**
	 * Null out `Lite_Site::$settings` so the next read re-fetches the option.
	 *
	 * `Lite_Site::get_settings()` memoizes the option in a private static
	 * property, so without this a value read by an earlier test would leak
	 * into every later one.
	 */
	protected static function reset_settings_cache() {
		$property = new ReflectionProperty( Lite_Site::class, 'settings' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * Persist plugin settings and make them visible to `Lite_Site` immediately.
	 *
	 * The option is replaced wholesale rather than merged, so any key left out
	 * is simply absent and each getter falls back to its own default.
	 *
	 * @param array $settings Settings to store.
	 */
	protected static function set_settings( array $settings ) {
		update_option( Lite_Site_Settings::OPTION_NAME, $settings );
		self::reset_settings_cache();
	}

	/**
	 * Switch to `/%category%/%postname%/` permalinks.
	 *
	 * Core adds the %category% rewrite tag only when the category taxonomy
	 * is registered under a permalink structure, and the test bootstrap
	 * registered it before any was set. So the structure is set, the
	 * taxonomies are registered again, and the rules are rebuilt with the tag.
	 */
	protected function set_category_permalinks() {
		$this->set_permalink_structure( '/%category%/%postname%/' );
		create_initial_taxonomies();
		$this->set_permalink_structure( '/%category%/%postname%/' );
	}

	/**
	 * The page cache key the lite single handler builds for a request, once
	 * WordPress has routed it.
	 *
	 * @param string $request_uri Request URI, as the server reports it.
	 * @return string The transient key.
	 */
	protected function get_request_page_cache_key( $request_uri ) {
		$_SERVER['REQUEST_URI'] = $request_uri;
		$GLOBALS['wp']->parse_request();
		$cache_key = Lite_Site::get_request_page_cache_key();
		tests_reset__SERVER();
		return $cache_key;
	}

	/**
	 * Call a private or protected static method.
	 *
	 * Used only for pure helpers that have no public seam. Where a public
	 * wrapper already exists the tests exercise that instead.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @param string $method     Method name.
	 * @param array  $args       Positional arguments.
	 * @return mixed The method's return value.
	 */
	protected static function invoke_private( $class_name, $method, array $args = [] ) {
		$reflection = new ReflectionMethod( $class_name, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( null, $args );
	}
}
