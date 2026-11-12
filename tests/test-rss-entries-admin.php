<?php
/**
 * Tests for the RSS admin page, its redirects, and the RSS entries REST additions.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site_Settings;
use Newspack_Lite_Site\Post_Type;
use Newspack_Lite_Site\RSS_Importer;

/**
 * Covers the RSS menu and tabs, the legacy redirects, and the REST feed filter and fields.
 */
class Test_RSS_Entries_Admin extends Lite_Site_TestCase {

	/**
	 * The legacy list redirect targets the Entries tab of the RSS page.
	 */
	public function test_redirect_target_points_to_entries_tab() {
		$target = Lite_Site_Settings::get_entries_redirect_target( [] );
		$this->assertSame( admin_url( 'admin.php?page=newspack-lite-site-rss#/entries' ), $target );
	}

	/**
	 * The former RSS Feeds and RSS Entries pages redirect to their tab, and other pages don't redirect.
	 */
	public function test_legacy_page_redirect_targets() {
		$this->assertSame(
			admin_url( 'admin.php?page=newspack-lite-site-rss#/feeds' ),
			Lite_Site_Settings::get_legacy_page_redirect_target( 'newspack-lite-site-rss-import', [ 'add' => '1' ] )
		);
		$this->assertSame(
			admin_url( 'admin.php?page=newspack-lite-site-rss#/entries' ),
			Lite_Site_Settings::get_legacy_page_redirect_target( 'newspack-lite-site-rss-entries', [] )
		);
		$this->assertNull( Lite_Site_Settings::get_legacy_page_redirect_target( 'newspack-lite-site', [] ) );
		$this->assertNull( Lite_Site_Settings::get_legacy_page_redirect_target( Lite_Site_Settings::RSS_PAGE_SLUG, [] ) );
	}

	/**
	 * The former RSS Entries page keeps its list and result args on the way to the Entries tab.
	 */
	public function test_legacy_entries_page_forwards_args() {
		$target = Lite_Site_Settings::get_legacy_page_redirect_target(
			'newspack-lite-site-rss-entries',
			[
				'page'        => 'newspack-lite-site-rss-entries',
				'post_status' => 'draft',
				'author'      => '3',
				'trashed'     => '1',
				'ids'         => '12',
			]
		);
		$this->assertStringStartsWith( admin_url( 'admin.php?page=newspack-lite-site-rss&' ), $target );
		$this->assertStringContainsString( 'post_status=draft', $target );
		$this->assertStringContainsString( 'author=3', $target );
		$this->assertStringContainsString( 'trashed=1', $target );
		$this->assertStringContainsString( 'ids=12', $target );
		$this->assertStringEndsWith( '#/entries', $target );
	}

	/**
	 * Only the supported args are forwarded, encoded, and empty ones are dropped.
	 */
	public function test_redirect_target_forwards_supported_args() {
		$target = Lite_Site_Settings::get_entries_redirect_target(
			[
				'post_status' => 'draft',
				's'           => 'a&b=c',
				'author'      => '',
				'orderby'     => 'title',
			]
		);
		$this->assertStringContainsString( 'post_status=draft', $target );
		$this->assertStringContainsString( 's=a%26b%3Dc', $target );
		$this->assertStringNotContainsString( 'author=', $target );
		$this->assertStringNotContainsString( 'orderby', $target );
		$this->assertStringEndsWith( '#/entries', $target );
	}

	/**
	 * Result counts and post IDs are forwarded as integers, so the page can confirm the action and offer Undo.
	 */
	public function test_redirect_target_forwards_result_args() {
		$target = Lite_Site_Settings::get_entries_redirect_target(
			[
				'trashed'   => '2',
				'ids'       => '12,abc,0,34',
				'untrashed' => 'x',
				'deleted'   => '0',
			]
		);
		$this->assertStringContainsString( 'trashed=2', $target );
		$this->assertStringContainsString( 'ids=12,34', $target );
		$this->assertStringNotContainsString( 'untrashed', $target );
		$this->assertStringNotContainsString( 'deleted', $target );

		$target = Lite_Site_Settings::get_entries_redirect_target(
			[
				'trashed' => [ '1' ],
				'ids'     => [ '12' ],
			]
		);
		$this->assertStringNotContainsString( 'trashed', $target );
		$this->assertStringNotContainsString( 'ids', $target );
	}

	/**
	 * The forwarded result becomes the action, count and IDs the page confirms.
	 */
	public function test_get_forwarded_result() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$first     = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		$second    = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		$published = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		wp_trash_post( $first );
		wp_trash_post( $second );

		$this->assertSame(
			[
				'action' => 'trash',
				'count'  => 2,
				'ids'    => [ $first, $second ],
			],
			Lite_Site_Settings::get_forwarded_result(
				[
					'trashed' => '1',
					'ids'     => $first . ',abc,' . $second . ',' . $published,
				]
			)
		);
		$this->assertSame(
			[
				'action' => 'trash',
				'count'  => 1,
				'ids'    => [],
			],
			Lite_Site_Settings::get_forwarded_result(
				[
					'trashed' => '1',
					'ids'     => (string) $published,
				]
			)
		);
		$this->assertSame(
			[
				'action' => 'delete',
				'count'  => 3,
				'ids'    => [],
			],
			Lite_Site_Settings::get_forwarded_result( [ 'deleted' => '3' ] )
		);
		$this->assertNull( Lite_Site_Settings::get_forwarded_result( [ 'trashed' => [ '1' ] ] ) );
		$this->assertNull( Lite_Site_Settings::get_forwarded_result( [ 'ids' => '12' ] ) );
	}

	/**
	 * Plain GET requests are redirected; real actions and other methods are not.
	 *
	 * @dataProvider redirect_requests
	 *
	 * @param array  $query    Query args.
	 * @param string $method   Request method.
	 * @param bool   $expected Whether the request should redirect.
	 */
	public function test_should_redirect_legacy_entries_list( array $query, string $method, bool $expected ) {
		$this->assertSame( $expected, Lite_Site_Settings::should_redirect_legacy_entries_list( $query, $method ) );
	}

	/**
	 * Legacy list requests and whether each should redirect.
	 *
	 * @return array
	 */
	public function redirect_requests(): array {
		return [
			'plain GET'           => [ [ 'post_type' => Post_Type::POST_TYPE ], 'GET', true ],
			'no-op bulk actions'  => [
				[
					'action'  => '-1',
					'action2' => '-1',
				],
				'GET',
				true,
			],
			'trash action'        => [ [ 'action' => 'trash' ], 'GET', false ],
			'bottom trash action' => [
				[
					'action'  => '-1',
					'action2' => 'trash',
				],
				'GET',
				false,
			],
			'array action'        => [ [ 'action' => [ 'trash' ] ], 'GET', false ],
			'POST'                => [ [], 'POST', false ],
		];
	}

	/**
	 * Dispatch a collection request for RSS entries.
	 *
	 * @param array $params Request params.
	 * @return WP_REST_Response
	 */
	private function get_entries( array $params = [] ): WP_REST_Response {
		$request = new WP_REST_Request( 'GET', '/wp/v2/' . Post_Type::POST_TYPE );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	/**
	 * The feed collection param filters entries by feed ID, including entries from deleted feeds.
	 */
	public function test_feed_param_filters_by_feed_id() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$a = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		$b = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		update_post_meta( $a, '_rss_import_feed_url', 'https://a.example/feed?key=secret' );
		update_post_meta( $b, '_rss_import_feed_url', 'https://b.example/feed' );

		$data = $this->get_entries(
			[
				'context' => 'edit',
				'feed'    => md5( 'https://a.example/feed?key=secret' ),
			]
		)->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( $a, $data[0]['id'] );
		$this->assertSame( md5( 'https://a.example/feed?key=secret' ), $data[0]['feed_id'] );
		$this->assertSame( 'a.example/feed', $data[0]['feed_label'] );
		$this->assertArrayNotHasKey( 'feed_url', $data[0] );
	}

	/**
	 * An unknown feed ID matches no entries rather than all of them.
	 */
	public function test_unknown_feed_id_returns_nothing() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$a = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		update_post_meta( $a, '_rss_import_feed_url', 'https://a.example/feed' );

		$response = $this->get_entries( [ 'feed' => md5( 'https://missing.example/feed' ) ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $response->get_data() );
	}

	/**
	 * Malformed feed values are rejected.
	 *
	 * @dataProvider invalid_feed_values
	 *
	 * @param mixed $value Feed param value.
	 */
	public function test_invalid_feed_param_is_rejected( $value ) {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );

		$response = $this->get_entries( [ 'feed' => $value ] );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * Malformed feed param values.
	 *
	 * @return array
	 */
	public function invalid_feed_values(): array {
		return [
			'empty'  => [ '' ],
			'array'  => [ [ md5( 'https://a.example/feed' ) ] ],
			'url'    => [ 'https://a.example/feed' ],
			'random' => [ 'not-an-id' ],
		];
	}

	/**
	 * Feeds that share a host and path get distinct labels.
	 */
	public function test_colliding_feed_labels_are_disambiguated() {
		$a = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		$b = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		$c = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		update_post_meta( $a, '_rss_import_feed_url', 'https://a.example/feed?cat=1' );
		update_post_meta( $b, '_rss_import_feed_url', 'https://a.example/feed?cat=2' );
		update_post_meta( $c, '_rss_import_feed_url', 'https://b.example/feed/' );

		$feeds = Post_Type::get_entry_feeds();
		$id_a  = md5( 'https://a.example/feed?cat=1' );
		$id_b  = md5( 'https://a.example/feed?cat=2' );

		$this->assertSame( 'a.example/feed (' . substr( $id_a, 0, 7 ) . ')', $feeds[ $id_a ] );
		$this->assertSame( 'a.example/feed (' . substr( $id_b, 0, 7 ) . ')', $feeds[ $id_b ] );
		$this->assertSame( 'b.example/feed', $feeds[ md5( 'https://b.example/feed/' ) ] );
	}

	/**
	 * The entry list fields are only exposed in the edit context.
	 */
	public function test_entry_fields_are_edit_only() {
		$id = self::factory()->post->create(
			[
				'post_type'   => Post_Type::POST_TYPE,
				'post_status' => 'publish',
			]
		);
		update_post_meta( $id, '_rss_import_feed_url', 'https://a.example/feed?key=secret' );
		update_post_meta( $id, '_rss_import_source_url', 'https://a.example/story' );
		$fields = [ 'feed_id', 'feed_label', 'source_url', 'author_name', 'lite_url', 'author_avatar_urls' ];

		wp_set_current_user( 0 );
		$data = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/' . Post_Type::POST_TYPE ) )->get_data();
		$this->assertSame( $id, $data[0]['id'] );
		foreach ( $fields as $field ) {
			$this->assertArrayNotHasKey( $field, $data[0] );
		}

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/' . Post_Type::POST_TYPE );
		$request->set_param( 'context', 'edit' );
		$data = rest_do_request( $request )->get_data();
		foreach ( $fields as $field ) {
			$this->assertArrayHasKey( $field, $data[0] );
		}
		$this->assertSame( md5( 'https://a.example/feed?key=secret' ), $data[0]['feed_id'] );
	}

	/**
	 * Anonymous requests can't probe entries by feed.
	 */
	public function test_feed_param_ignored_for_anonymous_requests() {
		$a = self::factory()->post->create(
			[
				'post_type'   => Post_Type::POST_TYPE,
				'post_status' => 'publish',
			]
		);
		$b = self::factory()->post->create(
			[
				'post_type'   => Post_Type::POST_TYPE,
				'post_status' => 'publish',
			]
		);
		update_post_meta( $a, '_rss_import_feed_url', 'https://a.example/feed' );
		update_post_meta( $b, '_rss_import_feed_url', 'https://b.example/feed' );

		wp_set_current_user( 0 );
		$data = $this->get_entries( [ 'feed' => md5( 'https://a.example/feed' ) ] )->get_data();

		$this->assertCount( 2, $data );
	}

	/**
	 * Restoring an entry through the REST API clears its trash meta.
	 */
	public function test_rest_restore_clears_trash_meta() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$id = self::factory()->post->create(
			[
				'post_type'   => Post_Type::POST_TYPE,
				'post_status' => 'publish',
			]
		);
		wp_trash_post( $id );
		$this->assertSame( 'publish', get_post_meta( $id, '_wp_trash_meta_status', true ) );
		$this->assertNotEmpty( get_post_meta( $id, '_wp_trash_meta_time', true ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/' . Post_Type::POST_TYPE . '/' . $id );
		$request->set_param( 'status', 'draft' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( '', get_post_meta( $id, '_wp_trash_meta_status', true ) );
		$this->assertSame( '', get_post_meta( $id, '_wp_trash_meta_time', true ) );
	}

	/**
	 * Store one feed.
	 */
	private function add_feed() {
		update_option( RSS_Importer::FEEDS_OPTION_NAME, [ 'feeds' => [ 'abc' => [ 'url' => 'https://example.org/feed' ] ] ] );
	}

	/**
	 * With no feeds or entries, an administrator gets the Feeds tab and no tabs to switch between.
	 */
	public function test_rss_data_without_feeds_or_entries() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( RSS_Importer::FEEDS_OPTION_NAME );
		$id = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		wp_trash_post( $id );

		$this->assertSame(
			[
				'canManageFeeds' => true,
				'hasFeeds'       => false,
				'hasEntries'     => false,
				'defaultTab'     => 'feeds',
			],
			Lite_Site_Settings::get_rss_data()
		);
	}

	/**
	 * A feed with nothing imported yet shows the tabs and still opens on Feeds.
	 */
	public function test_rss_data_with_a_feed_and_no_entries() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->add_feed();

		$data = Lite_Site_Settings::get_rss_data();
		$this->assertTrue( $data['hasFeeds'] );
		$this->assertFalse( $data['hasEntries'] );
		$this->assertSame( 'feeds', $data['defaultTab'] );
	}

	/**
	 * Entries open the page on the Entries tab, even when their feeds were deleted.
	 */
	public function test_rss_data_with_entries_and_no_feeds() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( RSS_Importer::FEEDS_OPTION_NAME );
		self::factory()->post->create(
			[
				'post_type'   => Post_Type::POST_TYPE,
				'post_status' => 'draft',
			]
		);

		$data = Lite_Site_Settings::get_rss_data();
		$this->assertFalse( $data['hasFeeds'] );
		$this->assertTrue( $data['hasEntries'] );
		$this->assertSame( 'entries', $data['defaultTab'] );
	}

	/**
	 * A user who can't manage options gets the Entries tab only.
	 */
	public function test_rss_data_for_editors() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->add_feed();

		$this->assertSame(
			[
				'canManageFeeds' => false,
				'hasFeeds'       => false,
				'hasEntries'     => false,
				'defaultTab'     => 'entries',
			],
			Lite_Site_Settings::get_rss_data()
		);
	}

	/**
	 * The flags are nested in the localized data, so they reach the page as booleans.
	 */
	public function test_rss_data_is_localized_nested() {
		$GLOBALS['wp_scripts'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		Lite_Site_Settings::enqueue_admin_scripts( Lite_Site_Settings::RSS_HOOK_SUFFIX );

		$script                = wp_scripts()->get_data( 'newspack-lite-site', 'data' );
		$GLOBALS['wp_scripts'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->assertStringContainsString( '"rss":{"canManageFeeds":true,"hasFeeds":false,"hasEntries":false,"defaultTab":"feeds"}', $script );
		$this->assertStringNotContainsString( 'importUrl', $script );
	}

	/**
	 * The entry editor screens highlight the RSS item, and other screens keep their own.
	 */
	public function test_submenu_file_maps_entries_list_to_rss() {
		$this->assertSame( Lite_Site_Settings::RSS_PAGE_SLUG, Lite_Site_Settings::filter_entries_submenu_file( 'edit.php?post_type=' . Post_Type::POST_TYPE ) );
		$this->assertSame( 'edit.php', Lite_Site_Settings::filter_entries_submenu_file( 'edit.php' ) );
		$this->assertNull( Lite_Site_Settings::filter_entries_submenu_file( null ) );
	}

	/**
	 * The RSS entry list, edit and add screens highlight the RSS item, whatever submenu file core picked.
	 *
	 * The screens are set directly, since setting one through core fires the list table redirect.
	 */
	public function test_submenu_file_maps_entry_screens_to_rss() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		$GLOBALS['current_screen'] = WP_Screen::get( 'edit-' . Post_Type::POST_TYPE ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->assertSame( Lite_Site_Settings::RSS_PAGE_SLUG, Lite_Site_Settings::filter_entries_submenu_file( 'edit.php?post_type=' . Post_Type::POST_TYPE ) );

		$GLOBALS['current_screen'] = WP_Screen::get( Post_Type::POST_TYPE ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->assertSame( 'post', get_current_screen()->base );
		$this->assertSame( Lite_Site_Settings::RSS_PAGE_SLUG, Lite_Site_Settings::filter_entries_submenu_file( 'post-new.php?post_type=' . Post_Type::POST_TYPE ) );
		$this->assertSame( Lite_Site_Settings::RSS_PAGE_SLUG, Lite_Site_Settings::filter_entries_submenu_file( null ) );

		$GLOBALS['current_screen'] = WP_Screen::get( 'edit-post' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->assertSame( 'edit.php', Lite_Site_Settings::filter_entries_submenu_file( 'edit.php' ) );
	}

	/**
	 * The admin_page_access_denied hook sends a former page to its tab, and leaves other pages alone.
	 */
	public function test_legacy_page_redirects_through_the_hook() {
		$redirect = static function ( $location ) {
			throw new RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		};
		add_filter( 'wp_redirect', $redirect );

		$GLOBALS['plugin_page'] = 'newspack-lite-site-rss-import'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		try {
			do_action( 'admin_page_access_denied' );
			$this->fail( 'The former RSS Feeds page should redirect.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( admin_url( 'admin.php?page=newspack-lite-site-rss#/feeds' ), $e->getMessage() );
		}

		$GLOBALS['plugin_page'] = 'some-other-page'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		do_action( 'admin_page_access_denied' );

		remove_filter( 'wp_redirect', $redirect );
		$this->assertTrue( true );
	}

	/**
	 * Build the Lite Site menu the way WordPress does, then run the plugin's admin_menu callbacks in priority order.
	 *
	 * @param string $role                   User role to build the menu for.
	 * @param bool   $with_post_type_submenu Whether core adds the RSS entries submenu item first.
	 */
	private function build_menu( string $role, bool $with_post_type_submenu = true ) {
		global $menu, $submenu, $_registered_pages;
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$menu              = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu           = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_registered_pages = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );

		if ( $with_post_type_submenu ) {
			_add_post_type_submenus();
		}

		global $wp_filter;
		$callbacks = $wp_filter['admin_menu']->callbacks;
		ksort( $callbacks );
		foreach ( $callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $callback ) {
				$function = $callback['function'];
				if ( is_array( $function ) && Lite_Site_Settings::class === $function[0] ) {
					call_user_func( $function );
				}
			}
		}
	}

	/**
	 * Restore the admin menu globals so a failing assertion cannot leak them into other tests.
	 */
	public function tear_down() {
		global $menu, $submenu, $_registered_pages, $admin_page_hooks, $_parent_pages, $_wp_menu_nopriv, $_wp_submenu_nopriv, $pagenow, $plugin_page, $parent_file, $current_screen;
		$menu               = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu            = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_registered_pages  = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$admin_page_hooks   = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_parent_pages      = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_wp_menu_nopriv    = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_wp_submenu_nopriv = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$pagenow            = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$plugin_page        = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$parent_file        = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$current_screen     = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		delete_option( RSS_Importer::FEEDS_OPTION_NAME );
		parent::tear_down();
	}

	/**
	 * The Lite Site submenu is RSS then Settings, with no separate RSS Entries or RSS Feeds item.
	 */
	public function test_admin_menu_has_rss_and_settings() {
		$this->build_menu( 'administrator' );

		global $menu, $submenu, $_registered_pages;
		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $menu[26][6] );
		$this->assertSame(
			[ Lite_Site_Settings::RSS_PAGE_SLUG, 'newspack-lite-site' ],
			array_column( array_values( $submenu['newspack-lite-site'] ), 2 )
		);
		$this->assertSame( 'RSS', array_values( $submenu['newspack-lite-site'] )[0][0] );
		$this->assertArrayHasKey( Lite_Site_Settings::RSS_HOOK_SUFFIX, $_registered_pages );
	}

	/**
	 * Users who can edit RSS entries but not manage options can open the RSS page.
	 */
	public function test_admin_menu_lets_editors_open_rss() {
		$this->build_menu( 'editor' );

		global $submenu;
		$rss = array_values(
			array_filter(
				$submenu['newspack-lite-site'] ?? [],
				fn( $item ) => Lite_Site_Settings::RSS_PAGE_SLUG === $item[2]
			)
		);
		$this->assertCount( 1, $rss );
		$this->assertTrue( current_user_can( $rss[0][1] ) );
		$this->assertFalse( current_user_can( 'manage_options' ) );
	}

	/**
	 * Without the post type's own submenu item, the Lite Site submenu still has no duplicate top-level link.
	 */
	public function test_admin_menu_has_no_duplicate_parent_item() {
		$this->build_menu( 'administrator', false );

		global $submenu;
		$this->assertSame(
			[ Lite_Site_Settings::RSS_PAGE_SLUG, 'newspack-lite-site' ],
			array_column( array_values( $submenu['newspack-lite-site'] ), 2 )
		);
		$this->assertNotContains( 'Lite Site', array_column( $submenu['newspack-lite-site'], 0 ) );
	}

	/**
	 * An editor can open the RSS page, with core's access arrays built as wp-admin/includes/menu.php builds them.
	 */
	public function test_editor_can_access_rss_page() {
		global $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv, $pagenow, $plugin_page;
		$this->build_menu( 'editor' );

		$_wp_submenu_nopriv = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_wp_menu_nopriv    = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		foreach ( $menu as $id => $data ) {
			if ( ! current_user_can( $data[1] ) ) {
				$_wp_menu_nopriv[ $data[2] ] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
			if ( ! empty( $submenu[ $data[2] ] ) && 1 === count( $submenu[ $data[2] ] ) && reset( $submenu[ $data[2] ] )[2] === $data[2] ) {
				unset( $submenu[ $data[2] ] );
			}
			if ( empty( $submenu[ $data[2] ] ) && isset( $_wp_menu_nopriv[ $data[2] ] ) ) {
				unset( $menu[ $id ] );
			}
		}

		$pagenow     = 'admin.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$plugin_page = Lite_Site_Settings::RSS_PAGE_SLUG; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertArrayHasKey( 'newspack-lite-site', $_wp_menu_nopriv );
		$this->assertTrue( user_can_access_admin_page() );

		$plugin_page = 'newspack-lite-site'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['parent_file'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->assertFalse( user_can_access_admin_page() );
	}
}
