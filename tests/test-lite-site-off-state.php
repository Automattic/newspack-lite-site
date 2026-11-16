<?php
/**
 * Tests for what Lite Site's addresses and offline page do before Lite Site
 * has been turned on, while it is on, and once it has been turned off.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;
use Newspack_Lite_Site\Lite_Site_Settings;
use Newspack_Lite_Site\Post_Type;

/**
 * Covers the rewrite rules, request routing, off-state redirects, offline
 * template and the record of Lite Site having been turned on.
 */
class Test_Lite_Site_Off_State extends Lite_Site_TestCase {

	/**
	 * Start from a site that has never had Lite Site on, under pretty
	 * permalinks. The RSS entry post type is registered again so it gets
	 * pretty permalinks too.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME );
		self::remove_lite_rewrite_rules();
		$this->set_permalink_structure( '/%postname%/' );
		Post_Type::register();
		self::flush_rules();
	}

	/**
	 * Before Lite Site has been turned on, it registers no rewrite rules and
	 * no query vars, and its address is an unknown address.
	 */
	public function test_never_enabled_registers_no_routes() {
		Lite_Site::register_rewrite_rules();
		self::flush_rules();

		$this->assertArrayNotHasKey( '^lite/?$', $GLOBALS['wp_rewrite']->wp_rewrite_rules(), 'No rewrite rule is registered for the Lite Site address.' );
		$this->assertNotContains( 'is_lite', Lite_Site::register_query_vars( [] ), 'No Lite Site query var is registered.' );

		$this->go_to( '/lite/' );

		$this->assertFalse( Lite_Site::is_lite_site_request(), 'The request is not routed to Lite Site.' );
		$this->assertTrue( is_404(), 'The address is not found.' );
	}

	/**
	 * Before Lite Site has been turned on, a page whose slug is the Lite Site
	 * address is served there.
	 */
	public function test_never_enabled_serves_page_at_lite_address() {
		$page_id = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'lite',
			]
		);
		Lite_Site::register_rewrite_rules();
		self::flush_rules();

		$this->go_to( '/lite/' );

		$this->assertFalse( Lite_Site::is_lite_site_request(), 'The request is not routed to Lite Site.' );
		$this->assertTrue( is_page( $page_id ), 'The page with that slug is served.' );
	}

	/**
	 * Rewrite rules saved while Lite Site registered them for every site are
	 * flushed once on a site that has never had it on.
	 */
	public function test_never_enabled_flushes_rules_saved_for_lite_site() {
		self::register_lite_rewrite_rules();
		self::flush_rules();
		$this->assertArrayHasKey( '^lite/?$', get_option( 'rewrite_rules' ), 'The saved rules start with the Lite Site rules.' );

		delete_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME );
		self::remove_lite_rewrite_rules();
		Lite_Site::register_rewrite_rules();

		$this->assertArrayNotHasKey( '^lite/?$', get_option( 'rewrite_rules' ), 'The saved Lite Site rules are flushed.' );
	}

	/**
	 * On a site that has never had Lite Site on, rewrite rules that hold no
	 * Lite Site rule at its path are not flushed.
	 *
	 * @dataProvider data_saved_rules_without_lite_site
	 *
	 * @param array|null $saved_rules Rules to save first, or null to keep the rules the test started with.
	 */
	public function test_never_enabled_does_not_flush_rules_without_lite_site( $saved_rules ) {
		if ( null !== $saved_rules ) {
			update_option( 'rewrite_rules', $saved_rules );
		}
		delete_transient( 'nls_flush_rewrite_rules' );
		$flushes = did_action( 'generate_rewrite_rules' );

		Lite_Site::register_rewrite_rules();

		$this->assertSame( $flushes, did_action( 'generate_rewrite_rules' ), 'The rules are not flushed.' );
	}

	/**
	 * Saved rules with no Lite Site rule at its path.
	 *
	 * @return array[]
	 */
	public function data_saved_rules_without_lite_site() {
		return [
			'clean rules'                => [ null ],
			'another rule at that regex' => [ [ '^lite/?$' => 'index.php?pagename=lite' ] ],
		];
	}

	/**
	 * While Lite Site is on, its addresses are routed to it as before.
	 */
	public function test_enabled_routes_lite_addresses() {
		self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_name'   => 'a-story',
			]
		);
		self::set_settings( [ 'enabled' => true ] );
		Lite_Site::register_rewrite_rules();
		self::flush_rules();

		$this->go_to( '/lite/' );
		$this->assertSame( 'archive', get_query_var( 'is_lite' ), 'The Lite Site home is routed to Lite Site.' );

		$this->go_to( '/lite/a-story/' );
		$this->assertSame( 'single', get_query_var( 'is_lite' ), 'An article is routed to Lite Site.' );
		$this->assertSame( 'a-story', get_query_var( 'lite_path' ), 'The article path is passed on.' );
	}

	/**
	 * Once Lite Site has been turned off, an article redirects temporarily to
	 * its full version.
	 *
	 * @dataProvider data_article_post_types
	 *
	 * @param string $post_type Post type of the article.
	 */
	public function test_was_enabled_redirects_article_to_permalink( $post_type ) {
		$post_id = self::factory()->post->create(
			[
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_name'   => 'a-story',
			]
		);
		$this->turn_off_after_enabling();

		$path = wp_parse_url( get_permalink( $post_id ), PHP_URL_PATH );
		$this->assertSame( [ get_permalink( $post_id ), 302 ], $this->get_redirect( '/lite' . $path ), 'The article redirects temporarily to its full version.' );
	}

	/**
	 * Article post types Lite Site serves.
	 *
	 * @return array[]
	 */
	public function data_article_post_types() {
		return [
			'post'      => [ 'post' ],
			'page'      => [ 'page' ],
			'RSS entry' => [ Post_Type::POST_TYPE ],
		];
	}

	/**
	 * Once Lite Site has been turned off, its other addresses redirect
	 * temporarily to the homepage.
	 *
	 * @dataProvider data_non_article_paths
	 *
	 * @param string $path Lite Site address.
	 */
	public function test_was_enabled_redirects_other_addresses_to_homepage( $path ) {
		$this->turn_off_after_enabling();

		$this->assertSame( [ home_url( '/' ), 302 ], $this->get_redirect( $path ), 'The address redirects temporarily to the homepage.' );
	}

	/**
	 * Once Lite Site has been turned off, an article it left out still
	 * redirects to its full version.
	 *
	 * @dataProvider data_left_out_articles
	 *
	 * @param array $post_args Arguments the post is created with, beyond its slug and status.
	 * @param bool  $excluded  Whether the post's category is excluded.
	 */
	public function test_was_enabled_redirects_left_out_article_to_permalink( $post_args, $excluded ) {
		$category_id = self::factory()->category->create();
		$post_id     = self::factory()->post->create(
			array_merge(
				[
					'post_status'   => 'publish',
					'post_name'     => 'a-story',
					'post_category' => [ $category_id ],
				],
				$post_args
			)
		);
		$this->turn_off_after_enabling( $excluded ? [ 'excluded_categories' => [ $category_id ] ] : [] );

		$this->assertNull( Lite_Site::resolve_post( 'a-story' ), 'Lite Site leaves the article out.' );
		$this->assertSame( [ get_permalink( $post_id ), 302 ], $this->get_redirect( '/lite/a-story/' ), 'The article redirects to its full version.' );
	}

	/**
	 * Articles Lite Site leaves out.
	 *
	 * @return array[]
	 */
	public function data_left_out_articles() {
		return [
			'excluded category'  => [ [], true ],
			'password-protected' => [ [ 'post_password' => 'secret' ], false ],
		];
	}

	/**
	 * Once Lite Site has been turned off, an address whose cached lookup holds
	 * an unpublished post redirects to the homepage.
	 *
	 * @dataProvider data_unpublished_statuses
	 *
	 * @param string $status Post status.
	 */
	public function test_was_enabled_redirects_unpublished_post_to_homepage( $status ) {
		$post_id = self::factory()->post->create(
			[
				'post_status' => $status,
				'post_name'   => 'a-story',
			]
		);
		$this->turn_off_after_enabling();
		$this->go_to( '/lite/a-story/' );
		wp_cache_set( self::invoke_private( Lite_Site::class, 'get_post_lookup_cache_key', [ 'a-story' ] ), $post_id, 'newspack_lite_site' );

		$this->assertSame( [ home_url( '/' ), 302 ], $this->capture_redirect(), 'The address redirects to the homepage.' );
	}

	/**
	 * Statuses of posts readers can't open.
	 *
	 * @return array[]
	 */
	public function data_unpublished_statuses() {
		return [
			'private' => [ 'private' ],
			'draft'   => [ 'draft' ],
		];
	}

	/**
	 * Once Lite Site has been turned off, an article whose permalink is on
	 * another site redirects to the homepage.
	 */
	public function test_was_enabled_redirects_article_with_external_permalink_to_homepage() {
		self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_name'   => 'a-story',
			]
		);
		$this->turn_off_after_enabling();
		$external_permalink = function () {
			return 'https://other-site.example.test/a-story/';
		};
		add_filter( 'post_link', $external_permalink );

		$redirect = $this->get_redirect( '/lite/a-story/' );
		remove_filter( 'post_link', $external_permalink );

		$this->assertSame( [ home_url( '/' ), 302 ], $redirect, 'The address redirects to the homepage.' );
	}

	/**
	 * Lite Site addresses that aren't articles.
	 *
	 * @return array[]
	 */
	public function data_non_article_paths() {
		return [
			'home'         => [ '/lite/' ],
			'pagination'   => [ '/lite/page/2/' ],
			'unresolvable' => [ '/lite/no-such-story/' ],
		];
	}

	/**
	 * The Lite Site offline template is used only while Lite Site is on.
	 */
	public function test_offline_template_applies_only_when_enabled() {
		$this->assertSame( 'theme-offline.php', apply_filters( 'offline_template', 'theme-offline.php' ), 'Before Lite Site has been on, the offline template is left alone.' );

		self::set_settings( [ 'enabled' => true ] );
		$this->assertSame( Lite_Site::get_offline_template(), apply_filters( 'offline_template', 'theme-offline.php' ), 'While Lite Site is on, its offline template is used.' );

		self::set_settings( [ 'enabled' => false ] );
		$this->assertSame( 'theme-offline.php', apply_filters( 'offline_template', 'theme-offline.php' ), 'Once Lite Site is off, the offline template is left alone.' );
	}

	/**
	 * The PWA plugin's precached offline page changes revision when Lite Site
	 * is turned on or off, so readers' devices fetch it again.
	 */
	public function test_offline_precache_revision_varies_by_state() {
		$entry = [
			'url'      => home_url( '/?wp_error_template=offline' ),
			'revision' => 'pwa',
		];

		$off_revision = apply_filters( 'wp_offline_error_precache_entry', $entry )['revision'];
		self::set_settings( [ 'enabled' => true ] );
		$on_revision = apply_filters( 'wp_offline_error_precache_entry', $entry )['revision'];

		$this->assertStringStartsWith( 'pwa', $off_revision, 'The PWA plugin\'s revision is kept.' );
		$this->assertNotSame( $off_revision, $on_revision, 'The revision differs between on and off.' );
		$this->assertStringContainsString( md5_file( Lite_Site::get_offline_template() ), $on_revision, 'The revision includes the offline template.' );
		$this->assertFalse( apply_filters( 'wp_offline_error_precache_entry', false ), 'Turning precaching off is kept.' );
	}

	/**
	 * Saving the settings with Lite Site on records that it has been on, in
	 * an autoloaded option, and saving them with it off does not.
	 */
	public function test_enabling_records_was_enabled() {
		update_option( Lite_Site_Settings::OPTION_NAME, [ 'enabled' => false ] );
		$this->assertFalse( get_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME ), 'Saving with Lite Site off records nothing.' );

		update_option( Lite_Site_Settings::OPTION_NAME, [ 'enabled' => true ] );
		$this->assertNotEmpty( get_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME ), 'Turning Lite Site on records it.' );

		wp_cache_delete( 'alloptions', 'options' );
		$this->assertArrayHasKey( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME, wp_load_alloptions(), 'The record is autoloaded.' );

		update_option( Lite_Site_Settings::OPTION_NAME, [ 'enabled' => false ] );
		$this->assertNotEmpty( get_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME ), 'Turning Lite Site off keeps the record.' );
	}

	/**
	 * Creating the settings with Lite Site on records that it has been on.
	 */
	public function test_first_save_enabled_records_was_enabled() {
		delete_option( Lite_Site_Settings::OPTION_NAME );
		update_option( Lite_Site_Settings::OPTION_NAME, [ 'enabled' => true ] );

		$this->assertNotEmpty( get_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME ), 'The first save with Lite Site on records it.' );
	}

	/**
	 * A site that already has Lite Site on gets the record when it loads.
	 */
	public function test_load_records_was_enabled_for_enabled_site() {
		self::set_settings( [ 'enabled' => true ] );
		delete_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME );

		Lite_Site::register_rewrite_rules();

		$this->assertNotEmpty( get_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME ), 'Loading with Lite Site on records it.' );
	}

	/**
	 * Rebuild and save the rewrite rules.
	 */
	private static function flush_rules() {
		flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Tests rebuild the rules they assert on.
	}

	/**
	 * Turn Lite Site on, register its rules, then turn it off.
	 *
	 * @param array $settings Other settings to save with Lite Site off.
	 */
	private function turn_off_after_enabling( array $settings = [] ) {
		self::set_settings( [ 'enabled' => true ] );
		self::set_settings( array_merge( $settings, [ 'enabled' => false ] ) );
		Lite_Site::register_rewrite_rules();
		self::flush_rules();
	}

	/**
	 * Request a Lite Site address and capture the redirect the template
	 * handler sends.
	 *
	 * @param string $path Address requested.
	 * @return array The redirect location and status.
	 */
	private function get_redirect( $path ) {
		$this->go_to( $path );
		return $this->capture_redirect();
	}

	/**
	 * Capture the redirect the template handler sends for the current
	 * request, without the exit that follows it.
	 *
	 * @return array The redirect location and status.
	 */
	private function capture_redirect() {
		$redirect = null;
		$capture  = function ( $location, $status ) use ( &$redirect ) {
			$redirect = [ $location, $status ];
			throw new WPDieException( 'Redirected.' );
		};
		add_filter( 'wp_redirect', $capture, 10, 2 );

		try {
			Lite_Site::handle_lite_site_templates();
		} catch ( WPDieException $e ) {
			unset( $e );
		} finally {
			remove_filter( 'wp_redirect', $capture, 10 );
		}

		$this->assertNotNull( $redirect, 'The request is redirected.' );
		return $redirect;
	}
}
