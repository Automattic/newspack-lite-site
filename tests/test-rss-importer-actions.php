<?php
/**
 * Tests for the RSS feed import and bulk delete REST actions.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\RSS_Importer;

/**
 * Covers the manual import action and the bulk delete route.
 */
class Test_RSS_Importer_Actions extends Lite_Site_TestCase {

	/**
	 * A safe, routable feed URL.
	 */
	const PUBLIC_FEED = 'http://93.184.216.34/feed.xml';

	/**
	 * A second safe, routable feed URL.
	 */
	const OTHER_FEED = 'http://93.184.216.34/other.xml';

	/**
	 * Author ID used for seeded feeds.
	 *
	 * @var int
	 */
	private $author_id;

	/**
	 * Create an administrator to own imported posts and make requests.
	 */
	public function set_up() {
		parent::set_up();
		$this->author_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->author_id );
	}

	/**
	 * Clear the registry, cron events and filters between tests.
	 */
	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		delete_option( RSS_Importer::FEEDS_OPTION_NAME );
		parent::tear_down();
	}

	/**
	 * Build a feed registry entry.
	 *
	 * @param string $url    Feed URL.
	 * @param string $status Feed status.
	 * @return array The feed entry.
	 */
	private function make_feed( $url, $status = 'active' ) {
		return [
			'feed_url'    => $url,
			'interval'    => 'daily',
			'author_id'   => $this->author_id,
			'status'      => $status,
			'last_run'    => null,
			'last_result' => null,
		];
	}

	/**
	 * Store feeds for the given URLs and return their IDs.
	 *
	 * @param array  $urls   Feed URLs.
	 * @param string $status Feed status.
	 * @return string[] Feed IDs.
	 */
	private function seed( array $urls, $status = 'active' ) {
		$feeds = [];
		foreach ( $urls as $url ) {
			$feeds[ RSS_Importer::get_feed_id( $url ) ] = $this->make_feed( $url, $status );
		}
		update_option( RSS_Importer::FEEDS_OPTION_NAME, [ 'feeds' => $feeds ] );
		return array_keys( $feeds );
	}

	/**
	 * Fake the feed fetch with a one-item RSS document.
	 */
	private function fake_feed() {
		add_filter(
			'pre_http_request',
			static function () {
				$body = '<?xml version="1.0"?><rss version="2.0"><channel><title>Test</title><link>http://93.184.216.34/</link><description>Test</description>'
					. '<item><title>Imported item</title><link>http://93.184.216.34/item-1</link><guid>item-1</guid><pubDate>Mon, 02 Nov 2026 10:00:00 +0000</pubDate><description>Body</description></item>'
					. '</channel></rss>';
				return [
					'headers'  => [ 'content-type' => 'application/rss+xml' ],
					'body'     => $body,
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
			}
		);
	}

	/**
	 * Fake a failing feed fetch.
	 */
	private function fake_failing_feed() {
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'Could not resolve host.' );
			}
		);
	}

	/**
	 * Send an action request.
	 *
	 * @param string $feed_id Feed ID.
	 * @param string $action  Action name.
	 * @return WP_REST_Response The REST response.
	 */
	private function action( $feed_id, $action ) {
		$request = new WP_REST_Request( 'POST', '/newspack-lite-site/v1/rss-feeds/' . $feed_id . '/action' );
		$request->set_body_params( [ 'action' => $action ] );
		return rest_do_request( $request );
	}

	/**
	 * Send a bulk delete request.
	 *
	 * @param array $ids Feed IDs.
	 * @return WP_REST_Response The REST response.
	 */
	private function bulk_delete( array $ids ) {
		$request = new WP_REST_Request( 'POST', '/newspack-lite-site/v1/rss-feeds/delete' );
		$request->set_body_params( [ 'ids' => $ids ] );
		return rest_do_request( $request );
	}

	/**
	 * Importing runs the feed and returns the refreshed list.
	 */
	public function test_import_runs_the_feed_and_returns_the_list() {
		$this->fake_feed();
		list( $feed_id ) = $this->seed( [ self::PUBLIC_FEED ] );

		$response = $this->action( $feed_id, 'import' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The import should succeed.' );
		$this->assertCount( 1, $data, 'The refreshed list should be returned.' );
		$this->assertNotNull( $data[0]['last_run'], 'The run time should be recorded.' );
		$this->assertSame( 1, $data[0]['last_result']['imported'], 'The item should be imported.' );
		$this->assertArrayNotHasKey( 'error', $data[0]['last_result'], 'There should be no error.' );
	}

	/**
	 * A failing fetch is recorded as the feed's error, not a failed request.
	 */
	public function test_import_records_a_failing_fetch_as_the_feed_error() {
		$this->fake_failing_feed();
		list( $feed_id ) = $this->seed( [ self::PUBLIC_FEED ] );

		$response = $this->action( $feed_id, 'import' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The request should succeed.' );
		$this->assertNotEmpty( $data[0]['last_result']['error'], 'The error should be recorded.' );
	}

	/**
	 * Fake an empty feed document.
	 */
	private function fake_empty_feed() {
		add_filter(
			'pre_http_request',
			static function () {
				return [
					'headers'  => [ 'content-type' => 'application/rss+xml' ],
					'body'     => '<?xml version="1.0"?><rss version="2.0"><channel><title>Test</title><link>http://93.184.216.34/</link><description>Test</description></channel></rss>',
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
			}
		);
	}

	/**
	 * An empty feed is recorded as up to date, not as an error.
	 */
	public function test_import_records_an_empty_feed_as_up_to_date_not_an_error() {
		$this->fake_empty_feed();
		list( $feed_id ) = $this->seed( [ self::PUBLIC_FEED ] );

		$data = $this->action( $feed_id, 'import' )->get_data();

		$this->assertArrayNotHasKey( 'error', $data[0]['last_result'], 'An empty feed is not an error.' );
		$this->assertSame( 0, $data[0]['last_result']['imported'], 'Nothing should be imported.' );
		$this->assertTrue( $data[0]['last_result']['up_to_date'], 'The feed should count as up to date.' );
		$this->assertTrue( $data[0]['last_result']['empty'], 'The empty-feed flag should be recorded.' );
	}

	/**
	 * The lock is released after a manual import.
	 */
	public function test_import_releases_the_lock() {
		$this->fake_feed();
		list( $feed_id ) = $this->seed( [ self::PUBLIC_FEED ] );

		$this->action( $feed_id, 'import' );

		$this->assertFalse( get_transient( RSS_Importer::LOCK_PREFIX . $feed_id ), 'The lock should be gone after the import.' );
	}

	/**
	 * The scheduled runner reports whether it ran.
	 */
	public function test_run_scheduled_import_reports_whether_it_ran() {
		$this->fake_feed();
		list( $feed_id ) = $this->seed( [ self::PUBLIC_FEED ] );

		$this->assertTrue( RSS_Importer::run_scheduled_import( $feed_id ), 'An active feed should run.' );
		$this->assertFalse( RSS_Importer::run_scheduled_import( md5( 'missing' ) ), 'A missing feed should be skipped.' );

		set_transient( RSS_Importer::LOCK_PREFIX . $feed_id, time(), RSS_Importer::LOCK_TTL );
		$this->assertFalse( RSS_Importer::run_scheduled_import( $feed_id ), 'A locked feed should be skipped.' );
	}

	/**
	 * A private-range feed URL is blocked on the REST path without any request.
	 */
	public function test_import_blocks_private_urls_without_a_request() {
		$requests = 0;
		add_filter(
			'pre_http_request',
			static function ( $pre ) use ( &$requests ) {
				++$requests;
				return $pre;
			},
			1
		);
		list( $feed_id ) = $this->seed( [ 'http://10.0.0.5/feed.xml' ] );

		$data = $this->action( $feed_id, 'import' )->get_data();

		$this->assertNotEmpty( $data[0]['last_result']['error'], 'The blocked URL should record an error.' );
		$this->assertSame( 0, $requests, 'No outbound request should be made.' );
	}

	/**
	 * A feed deleted during the import is a 404, not a busy feed.
	 */
	public function test_import_of_a_feed_deleted_mid_run_returns_404() {
		list( $feed_id ) = $this->seed( [ self::PUBLIC_FEED ] );
		add_filter(
			'pre_http_request',
			static function ( $pre ) {
				delete_option( RSS_Importer::FEEDS_OPTION_NAME );
				return new WP_Error( 'http_request_failed', 'Gone.' );
			}
		);

		$response = $this->action( $feed_id, 'import' );

		$this->assertSame( 404, $response->get_status(), 'A feed deleted mid-run should be a 404.' );
		$this->assertFalse( get_transient( RSS_Importer::LOCK_PREFIX . $feed_id ), 'The lock should be released.' );
	}

	/**
	 * An unknown feed is a 404.
	 */
	public function test_import_unknown_feed_returns_404() {
		$this->fake_feed();

		$response = $this->action( md5( 'missing' ), 'import' );

		$this->assertSame( 404, $response->get_status(), 'An unknown feed should be a 404.' );
	}

	/**
	 * A locked feed is not imported twice.
	 */
	public function test_import_respects_the_lock() {
		$this->fake_feed();
		list( $feed_id ) = $this->seed( [ self::PUBLIC_FEED ] );
		set_transient( RSS_Importer::LOCK_PREFIX . $feed_id, time(), RSS_Importer::LOCK_TTL );

		$response = $this->action( $feed_id, 'import' );

		$this->assertSame( 409, $response->get_status(), 'A locked feed should be refused.' );
		$this->assertNull( RSS_Importer::get_feeds()[ $feed_id ]['last_run'], 'The feed should not have run.' );
		$this->assertNotFalse( get_transient( RSS_Importer::LOCK_PREFIX . $feed_id ), 'The other run should keep its lock.' );
	}

	/**
	 * A paused feed is refused and left untouched.
	 */
	public function test_import_refuses_a_paused_feed() {
		$this->fake_feed();
		list( $feed_id ) = $this->seed( [ self::PUBLIC_FEED ], 'paused' );

		$response = $this->action( $feed_id, 'import' );
		$feeds    = RSS_Importer::get_feeds();

		$this->assertSame( 400, $response->get_status(), 'A paused feed should be refused.' );
		$this->assertSame( 'paused', $feeds[ $feed_id ]['status'], 'The feed should stay paused.' );
		$this->assertNull( $feeds[ $feed_id ]['last_run'], 'The feed should not have run.' );
	}

	/**
	 * Importing needs the manage_options capability.
	 */
	public function test_import_requires_manage_options() {
		$this->fake_feed();
		list( $feed_id ) = $this->seed( [ self::PUBLIC_FEED ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->assertSame( 403, $this->action( $feed_id, 'import' )->get_status(), 'An editor should be refused.' );
	}

	/**
	 * Bulk delete removes the feeds and clears their cron events.
	 */
	public function test_bulk_delete_removes_feeds_and_clears_cron() {
		$ids = $this->seed( [ self::PUBLIC_FEED, self::OTHER_FEED, 'http://93.184.216.34/third.xml' ] );
		foreach ( $ids as $id ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', RSS_Importer::CRON_HOOK, [ $id ] );
		}

		$response = $this->bulk_delete( [ $ids[0], $ids[1] ] );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Deleting should succeed.' );
		$this->assertSame( [ $ids[2] ], wp_list_pluck( $data, 'id' ), 'Only the unselected feed should remain.' );
		$this->assertSame( [ $ids[2] ], array_keys( RSS_Importer::get_feeds() ), 'The registry should match.' );
		$this->assertFalse( wp_get_scheduled_event( RSS_Importer::CRON_HOOK, [ $ids[0] ] ), 'The first cron event should be cleared.' );
		$this->assertFalse( wp_get_scheduled_event( RSS_Importer::CRON_HOOK, [ $ids[1] ] ), 'The second cron event should be cleared.' );
		$this->assertNotFalse( wp_get_scheduled_event( RSS_Importer::CRON_HOOK, [ $ids[2] ] ), 'The kept feed should stay scheduled.' );
	}

	/**
	 * Unknown IDs are ignored.
	 */
	public function test_bulk_delete_ignores_unknown_ids() {
		list( $feed_id ) = $this->seed( [ self::PUBLIC_FEED ] );

		$response = $this->bulk_delete( [ md5( 'missing' ), $feed_id ] );

		$this->assertSame( 200, $response->get_status(), 'Unknown IDs should not fail the request.' );
		$this->assertSame( [], $response->get_data(), 'The known feed should be deleted.' );
	}

	/**
	 * A comma-separated string of IDs is accepted.
	 */
	public function test_bulk_delete_accepts_a_comma_separated_string() {
		$ids = $this->seed( [ self::PUBLIC_FEED, self::OTHER_FEED ] );

		$request = new WP_REST_Request( 'POST', '/newspack-lite-site/v1/rss-feeds/delete' );
		$request->set_body_params( [ 'ids' => implode( ',', $ids ) ] );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status(), 'A comma-separated list should be accepted.' );
		$this->assertSame( [], $response->get_data(), 'Both feeds should be deleted.' );
	}

	/**
	 * Duplicate IDs are handled once.
	 */
	public function test_bulk_delete_handles_duplicate_ids() {
		$ids = $this->seed( [ self::PUBLIC_FEED, self::OTHER_FEED ] );

		$response = $this->bulk_delete( [ $ids[0], $ids[0] ] );

		$this->assertSame( 200, $response->get_status(), 'Duplicates should not fail the request.' );
		$this->assertSame( [ $ids[1] ], wp_list_pluck( $response->get_data(), 'id' ), 'Only the duplicated feed should be removed.' );
	}

	/**
	 * Malformed IDs are rejected.
	 */
	public function test_bulk_delete_rejects_malformed_ids() {
		$this->assertSame( 400, $this->bulk_delete( [ 'not-an-id' ] )->get_status(), 'A malformed ID should be rejected.' );
		$this->assertSame( 400, $this->bulk_delete( [] )->get_status(), 'An empty list should be rejected.' );
	}

	/**
	 * Bulk delete needs the manage_options capability.
	 */
	public function test_bulk_delete_requires_manage_options() {
		list( $feed_id ) = $this->seed( [ self::PUBLIC_FEED ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->assertSame( 403, $this->bulk_delete( [ $feed_id ] )->get_status(), 'An editor should be refused.' );
		$this->assertCount( 1, RSS_Importer::get_feeds(), 'Nothing should be deleted.' );
	}
}
