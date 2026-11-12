<?php
/**
 * Tests for the RSS Entries admin page redirect and REST additions.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site_Settings;
use Newspack_Lite_Site\Post_Type;

/**
 * Covers the legacy list redirect target and the REST feed filter and fields.
 */
class Test_RSS_Entries_Admin extends Lite_Site_TestCase {

	/**
	 * The redirect targets the React page.
	 */
	public function test_redirect_target_points_to_page() {
		$target = Lite_Site_Settings::get_entries_redirect_target( [] );
		$this->assertSame( admin_url( 'admin.php?page=newspack-lite-site-rss-entries' ), $target );
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
}
