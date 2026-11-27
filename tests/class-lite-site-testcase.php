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
		foreach ( $this->theme_json_filters as [ $hook, $callback ] ) {
			remove_filter( $hook, $callback );
		}
		$this->theme_json_filters = [];
		$this->restore_theme();
		parent::tear_down();
	}

	/**
	 * Theme directories and stylesheet to put back after use_block_theme().
	 *
	 * @var array|null
	 */
	private $theme_to_restore = null;

	/**
	 * Theme JSON filters added by filter_theme_json(), removed after each test.
	 *
	 * @var array
	 */
	private $theme_json_filters = [];

	/**
	 * Merge data into one theme.json origin for the rest of the test.
	 *
	 * @param string $hook Filter, such as wp_theme_json_data_theme or wp_theme_json_data_user.
	 * @param array  $data theme.json data to merge in.
	 */
	protected function filter_theme_json( string $hook, array $data ) {
		$callback = static function ( $theme_json ) use ( $data ) {
			return $theme_json->update_with( array_merge( [ 'version' => 3 ], $data ) );
		};
		add_filter( $hook, $callback );
		$this->theme_json_filters[] = [ $hook, $callback ];
		wp_clean_theme_json_cache();
		self::reset_settings_cache();
	}

	/**
	 * Switch to the fixture block theme in tests/fixtures/themes.
	 */
	protected function use_block_theme() {
		global $wp_theme_directories;
		$this->theme_to_restore = [
			'directories' => $wp_theme_directories,
			'stylesheet'  => get_stylesheet(),
		];
		register_theme_directory( __DIR__ . '/fixtures/themes' );
		search_theme_directories( true );
		switch_theme( 'nls-block-theme' );
		wp_clean_theme_json_cache();
		self::reset_settings_cache();
		$this->assertTrue( wp_is_block_theme(), 'The fixture theme should be a block theme.' );
	}

	/**
	 * Undo use_block_theme().
	 */
	private function restore_theme() {
		if ( null === $this->theme_to_restore ) {
			return;
		}
		global $wp_theme_directories;
		switch_theme( $this->theme_to_restore['stylesheet'] );
		$wp_theme_directories = $this->theme_to_restore['directories']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		search_theme_directories( true );
		wp_clean_theme_json_cache();
		self::reset_settings_cache();
		$this->theme_to_restore = null;
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
		foreach ( [ 'settings', 'theme_styles' ] as $name ) {
			$property = new ReflectionProperty( Lite_Site::class, $name );
			$property->setAccessible( true );
			$property->setValue( null, null );
		}
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
