<?php
/**
 * Smoke tests covering the plugin bootstrap.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Initializer;
use Newspack_Lite_Site\Lite_Site;
use Newspack_Lite_Site\Lite_Site_Settings;
use Newspack_Lite_Site\Post_Type;
use Newspack_Lite_Site\RSS_Importer;

/**
 * Verify the plugin loads, autoloads its classes and wires up its hooks.
 */
class Test_Bootstrap extends Lite_Site_TestCase {

	/**
	 * The plugin's constants are defined once it loads.
	 */
	public function test_plugin_constants_are_defined() {
		$this->assertTrue( defined( 'NEWSPACK_LITE_SITE_PLUGIN_DIR' ), 'Plugin dir constant should be defined.' );
		$this->assertTrue( defined( 'NEWSPACK_LITE_SITE_PLUGIN_FILE' ), 'Plugin file constant should be defined.' );
	}

	/**
	 * Every feature class is autoloadable via Composer.
	 */
	public function test_feature_classes_exist() {
		$this->assertTrue( class_exists( Initializer::class ), 'Initializer should autoload.' );
		$this->assertTrue( class_exists( Lite_Site::class ), 'Lite_Site should autoload.' );
		$this->assertTrue( class_exists( Lite_Site_Settings::class ), 'Lite_Site_Settings should autoload.' );
		$this->assertTrue( class_exists( Post_Type::class ), 'Post_Type should autoload.' );
		$this->assertTrue( class_exists( RSS_Importer::class ), 'RSS_Importer should autoload.' );
	}

	/**
	 * The RSS entry post type is registered on `init`.
	 */
	public function test_post_type_is_registered() {
		$this->assertTrue( post_type_exists( Post_Type::POST_TYPE ), 'The RSS entry post type should be registered.' );
	}

	/**
	 * The post type exposes the capabilities the importer and admin UI rely on.
	 */
	public function test_post_type_configuration() {
		$post_type = get_post_type_object( Post_Type::POST_TYPE );

		$this->assertNotNull( $post_type, 'The post type object should be retrievable.' );
		$this->assertTrue( $post_type->show_in_rest, 'The post type should be exposed to the REST API.' );
		$this->assertTrue( $post_type->publicly_queryable, 'The post type should be publicly queryable.' );
		$this->assertFalse( $post_type->public, 'The post type should not be fully public.' );

		$this->assertTrue( post_type_supports( Post_Type::POST_TYPE, 'thumbnail' ), 'Featured images are required by the importer.' );
		$this->assertTrue( post_type_supports( Post_Type::POST_TYPE, 'author' ), 'Imported posts are assigned an author.' );
		$this->assertTrue( post_type_supports( Post_Type::POST_TYPE, 'custom-fields' ), 'Import metadata is stored as custom fields.' );

		$this->assertContains( 'category', $post_type->taxonomies, 'Categories should be available on imported entries.' );
		$this->assertContains( 'post_tag', $post_type->taxonomies, 'Tags should be available on imported entries.' );
	}

	/**
	 * The lite site request handling is hooked up.
	 */
	public function test_lite_site_hooks_are_registered() {
		$this->assertNotFalse(
			has_action( 'init', [ Lite_Site::class, 'register_rewrite_rules' ] ),
			'Rewrite rules should be registered on init.'
		);
		$this->assertNotFalse(
			has_filter( 'query_vars', [ Lite_Site::class, 'register_query_vars' ] ),
			'Custom query vars should be registered.'
		);
		$this->assertNotFalse(
			has_action( 'template_redirect', [ Lite_Site::class, 'handle_lite_site_templates' ] ),
			'Template routing should run on template_redirect.'
		);
		$this->assertNotFalse(
			has_action( 'save_post', [ Lite_Site::class, 'invalidate_page_cache' ] ),
			'The page cache should be invalidated when a post is saved.'
		);
	}

	/**
	 * The RSS importer's cron and REST hooks are wired up.
	 */
	public function test_rss_importer_hooks_are_registered() {
		$this->assertNotFalse(
			has_action( RSS_Importer::CRON_HOOK, [ RSS_Importer::class, 'run_scheduled_import' ] ),
			'The scheduled import should be bound to the cron hook.'
		);
		$this->assertNotFalse(
			has_filter( 'cron_schedules', [ RSS_Importer::class, 'register_cron_intervals' ] ),
			'Custom cron intervals should be registered.'
		);
		$this->assertNotFalse(
			has_action( 'rest_api_init', [ RSS_Importer::class, 'register_rest_routes' ] ),
			'REST routes should be registered.'
		);
	}

	/**
	 * The plugin's REST routes are exposed under its own namespace.
	 */
	public function test_rest_routes_are_registered() {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/newspack-lite-site/v1/rss-feeds', $routes, 'The feeds collection route should exist.' );
	}

	/**
	 * The offline template resolves to a file that ships with the plugin.
	 */
	public function test_offline_template_path_exists() {
		$this->assertFileExists( Lite_Site::get_offline_template(), 'The offline template should ship with the plugin.' );
	}
}
