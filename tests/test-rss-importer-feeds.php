<?php
/**
 * Tests for RSS feed configuration, cron intervals and import guards.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\RSS_Importer;

/**
 * Covers the feed registry, the custom cron schedules, and the guard clauses
 * that stop an import before any outbound request is made.
 */
class Test_RSS_Importer_Feeds extends Lite_Site_TestCase {

	/**
	 * A safe, routable feed URL.
	 */
	const PUBLIC_FEED = 'http://93.184.216.34/feed.xml';

	/**
	 * Clear the feed registry between tests.
	 */
	public function tear_down() {
		delete_option( RSS_Importer::FEEDS_OPTION_NAME );
		parent::tear_down();
	}

	/**
	 * Store a feed registry directly, bypassing the REST layer.
	 *
	 * @param array $feeds Feeds keyed by feed ID.
	 */
	private function seed_feeds( array $feeds ) {
		update_option( RSS_Importer::FEEDS_OPTION_NAME, [ 'feeds' => $feeds ] );
	}

	/**
	 * Build a feed registry entry.
	 *
	 * @param string $status Feed status, 'active' or 'paused'.
	 * @return array The feed entry.
	 */
	private function make_feed( $status = 'active' ) {
		return [
			'feed_url'    => self::PUBLIC_FEED,
			'interval'    => 'daily',
			'author_id'   => 1,
			'status'      => $status,
			'last_run'    => null,
			'last_result' => null,
		];
	}

	/**
	 * Send a PATCH request to the update route as an administrator.
	 *
	 * @param string $feed_id Feed ID.
	 * @param array  $params  Request parameters.
	 * @return WP_REST_Response The REST response.
	 */
	private function update_feed( $feed_id, array $params ) {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$request = new WP_REST_Request( 'PATCH', '/newspack-lite-site/v1/rss-feeds/' . $feed_id );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Seed an active feed with a scheduled cron event.
	 *
	 * @param int $next_run Timestamp of the first run.
	 * @return string The feed ID.
	 */
	private function seed_scheduled_feed( $next_run ) {
		$feed_id = RSS_Importer::get_feed_id( self::PUBLIC_FEED );
		$this->seed_feeds( [ $feed_id => $this->make_feed( 'active' ) ] );
		wp_schedule_event( $next_run, 'daily', RSS_Importer::CRON_HOOK, [ $feed_id ] );

		return $feed_id;
	}

	/**
	 * Feed IDs are the MD5 of the feed URL and are stable.
	 */
	public function test_feed_id_is_stable_md5_of_url() {
		$this->assertSame( md5( self::PUBLIC_FEED ), RSS_Importer::get_feed_id( self::PUBLIC_FEED ), 'The feed ID should be the URL hash.' );
		$this->assertNotSame(
			RSS_Importer::get_feed_id( self::PUBLIC_FEED ),
			RSS_Importer::get_feed_id( 'http://93.184.216.34/other.xml' ),
			'Different URLs should produce different IDs.'
		);
	}

	/**
	 * An empty registry reads back as an empty array rather than null.
	 */
	public function test_get_feeds_defaults_to_empty_array() {
		$this->assertSame( [], RSS_Importer::get_feeds(), 'An unconfigured registry should be an empty array.' );
	}

	/**
	 * Every interval offered by the UI has a label.
	 */
	public function test_interval_labels_cover_all_supported_intervals() {
		$labels = RSS_Importer::get_interval_labels();

		foreach ( [ 'every_5_minutes', 'every_10_minutes', 'every_30_minutes', 'hourly', 'twicedaily', 'daily', 'weekly' ] as $interval ) {
			$this->assertArrayHasKey( $interval, $labels, sprintf( 'The %s interval should have a label.', $interval ) );
			$this->assertNotEmpty( $labels[ $interval ], sprintf( 'The %s label should not be empty.', $interval ) );
		}
	}

	/**
	 * The custom cron schedules are added with the right durations.
	 */
	public function test_registers_custom_cron_intervals() {
		$schedules = RSS_Importer::register_cron_intervals( [] );

		$this->assertSame( 5 * MINUTE_IN_SECONDS, $schedules['every_5_minutes']['interval'], 'Five minute interval mismatch.' );
		$this->assertSame( 10 * MINUTE_IN_SECONDS, $schedules['every_10_minutes']['interval'], 'Ten minute interval mismatch.' );
		$this->assertSame( 30 * MINUTE_IN_SECONDS, $schedules['every_30_minutes']['interval'], 'Thirty minute interval mismatch.' );
		$this->assertSame( WEEK_IN_SECONDS, $schedules['weekly']['interval'], 'Weekly interval mismatch.' );
	}

	/**
	 * Schedules registered elsewhere are not overwritten.
	 */
	public function test_does_not_clobber_existing_cron_intervals() {
		$existing = [
			'weekly' => [
				'interval' => 1,
				'display'  => 'Someone else owns this',
			],
		];

		$schedules = RSS_Importer::register_cron_intervals( $existing );

		$this->assertSame( 1, $schedules['weekly']['interval'], 'An existing schedule should be left alone.' );
		$this->assertArrayHasKey( 'every_5_minutes', $schedules, 'Other custom schedules should still be added.' );
	}

	/**
	 * Core schedules pass through untouched.
	 */
	public function test_preserves_core_cron_intervals() {
		$schedules = RSS_Importer::register_cron_intervals( wp_get_schedules() );

		$this->assertArrayHasKey( 'hourly', $schedules, 'Core schedules should survive.' );
		$this->assertArrayHasKey( 'twicedaily', $schedules, 'Core schedules should survive.' );
	}

	/**
	 * An import without a usable URL fails before any request is attempted.
	 */
	public function test_import_rejects_missing_url() {
		$result = RSS_Importer::run_import( '', 1 );

		$this->assertInstanceOf( WP_Error::class, $result, 'An empty URL should be rejected.' );
		$this->assertSame( 'invalid_url', $result->get_error_code(), 'The invalid URL code should be returned.' );
	}

	/**
	 * An import targeting a private address is refused.
	 */
	public function test_import_rejects_unsafe_url() {
		$result = RSS_Importer::run_import( 'http://127.0.0.1/feed.xml', 1 );

		$this->assertInstanceOf( WP_Error::class, $result, 'A loopback URL should be rejected.' );
		$this->assertSame( 'invalid_url', $result->get_error_code(), 'The invalid URL code should be returned.' );
	}

	/**
	 * An import without an author is refused.
	 */
	public function test_import_rejects_missing_author() {
		$result = RSS_Importer::run_import( self::PUBLIC_FEED, 0 );

		$this->assertInstanceOf( WP_Error::class, $result, 'A missing author should be rejected.' );
		$this->assertSame( 'invalid_author', $result->get_error_code(), 'The invalid author code should be returned.' );
	}

	/**
	 * An author who cannot publish is refused.
	 */
	public function test_import_rejects_author_without_publish_capability() {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		$result = RSS_Importer::run_import( self::PUBLIC_FEED, $subscriber_id );

		$this->assertInstanceOf( WP_Error::class, $result, 'A subscriber should not be a valid import author.' );
		$this->assertSame( 'invalid_author', $result->get_error_code(), 'The invalid author code should be returned.' );
	}

	/**
	 * A scheduled run without a feed ID does nothing and takes no lock.
	 */
	public function test_scheduled_import_ignores_missing_feed_id() {
		RSS_Importer::run_scheduled_import( '' );

		$this->assertFalse( get_transient( RSS_Importer::LOCK_PREFIX ), 'No lock should be taken without a feed ID.' );
	}

	/**
	 * A scheduled run for an unknown feed releases its lock and stops.
	 */
	public function test_scheduled_import_releases_lock_for_unknown_feed() {
		$feed_id = md5( 'http://93.184.216.34/unknown.xml' );

		RSS_Importer::run_scheduled_import( $feed_id );

		$this->assertFalse(
			get_transient( RSS_Importer::LOCK_PREFIX . $feed_id ),
			'The lock should be released when the feed is not configured.'
		);
	}

	/**
	 * A paused feed is skipped and its lock released.
	 */
	public function test_scheduled_import_skips_paused_feed() {
		$feed_id = RSS_Importer::get_feed_id( self::PUBLIC_FEED );
		$this->seed_feeds( [ $feed_id => $this->make_feed( 'paused' ) ] );

		RSS_Importer::run_scheduled_import( $feed_id );

		$feeds = RSS_Importer::get_feeds();
		$this->assertNull( $feeds[ $feed_id ]['last_run'], 'A paused feed should not record a run.' );
		$this->assertFalse( get_transient( RSS_Importer::LOCK_PREFIX . $feed_id ), 'The lock should be released.' );
	}

	/**
	 * A feed already being imported is skipped, so two cron ticks never run
	 * the same import concurrently.
	 */
	public function test_scheduled_import_skips_locked_feed() {
		$feed_id = RSS_Importer::get_feed_id( self::PUBLIC_FEED );
		$this->seed_feeds( [ $feed_id => $this->make_feed( 'active' ) ] );
		set_transient( RSS_Importer::LOCK_PREFIX . $feed_id, time(), RSS_Importer::LOCK_TTL );

		RSS_Importer::run_scheduled_import( $feed_id );

		$feeds = RSS_Importer::get_feeds();
		$this->assertNull( $feeds[ $feed_id ]['last_run'], 'A locked feed should not be imported.' );
		$this->assertNotFalse(
			get_transient( RSS_Importer::LOCK_PREFIX . $feed_id ),
			'The in-progress lock should be left in place for the running import.'
		);
	}

	/**
	 * Updating the interval and author saves both and returns the feed list.
	 */
	public function test_update_feed_changes_interval_and_author() {
		$feed_id   = RSS_Importer::get_feed_id( self::PUBLIC_FEED );
		$author_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->seed_feeds( [ $feed_id => $this->make_feed( 'paused' ) ] );

		$response = $this->update_feed(
			$feed_id,
			[
				'interval'  => 'weekly',
				'author_id' => $author_id,
			]
		);
		$feeds    = RSS_Importer::get_feeds();
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'A valid update should succeed.' );
		$this->assertSame( 'weekly', $feeds[ $feed_id ]['interval'], 'The interval should be saved.' );
		$this->assertSame( $author_id, $feeds[ $feed_id ]['author_id'], 'The author should be saved.' );
		$this->assertSame( self::PUBLIC_FEED, $feeds[ $feed_id ]['feed_url'], 'The URL should be untouched.' );
		$this->assertCount( 1, $data, 'The full feed list should be returned.' );
		$this->assertSame( 'weekly', $data[0]['interval'], 'The response should reflect the change.' );
	}

	/**
	 * The feed list includes the author's avatar URLs, and none when avatars are hidden.
	 */
	public function test_feed_response_includes_author_avatar_urls() {
		$feed_id = RSS_Importer::get_feed_id( self::PUBLIC_FEED );
		$this->seed_feeds( [ $feed_id => $this->make_feed( 'active' ) ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		update_option( 'show_avatars', 1 );
		$data = rest_do_request( new WP_REST_Request( 'GET', '/newspack-lite-site/v1/rss-feeds' ) )->get_data();
		$this->assertNotEmpty( $data[0]['author_avatar_urls'], 'The feed should include its author avatar URLs.' );

		update_option( 'show_avatars', 0 );
		$data = rest_do_request( new WP_REST_Request( 'GET', '/newspack-lite-site/v1/rss-feeds' ) )->get_data();
		$this->assertSame( [], $data[0]['author_avatar_urls'], 'Avatar URLs should be empty when avatars are hidden.' );
	}

	/**
	 * An author who cannot publish is rejected and nothing is saved.
	 */
	public function test_update_feed_rejects_invalid_author() {
		$feed_id       = RSS_Importer::get_feed_id( self::PUBLIC_FEED );
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$this->seed_feeds( [ $feed_id => $this->make_feed( 'paused' ) ] );

		$response = $this->update_feed( $feed_id, [ 'author_id' => $subscriber_id ] );
		$feeds    = RSS_Importer::get_feeds();

		$this->assertSame( 400, $response->get_status(), 'A subscriber should be rejected.' );
		$this->assertSame( 'invalid_author', $response->get_data()['code'], 'The invalid author code should be returned.' );
		$this->assertSame( 1, $feeds[ $feed_id ]['author_id'], 'The author should not change.' );
	}

	/**
	 * An unsupported interval is rejected and nothing is saved.
	 */
	public function test_update_feed_rejects_invalid_interval() {
		$feed_id = RSS_Importer::get_feed_id( self::PUBLIC_FEED );
		$this->seed_feeds( [ $feed_id => $this->make_feed( 'paused' ) ] );

		$response = $this->update_feed( $feed_id, [ 'interval' => 'every_second' ] );
		$feeds    = RSS_Importer::get_feeds();

		$this->assertSame( 400, $response->get_status(), 'An unknown interval should be rejected.' );
		$this->assertSame( 'invalid_interval', $response->get_data()['code'], 'The invalid interval code should be returned.' );
		$this->assertSame( 'daily', $feeds[ $feed_id ]['interval'], 'The interval should not change.' );
	}

	/**
	 * An unknown feed ID returns a 404.
	 */
	public function test_update_feed_returns_404_for_unknown_feed() {
		$response = $this->update_feed( md5( 'http://93.184.216.34/unknown.xml' ), [ 'interval' => 'weekly' ] );

		$this->assertSame( 404, $response->get_status(), 'An unknown feed should return 404.' );
		$this->assertSame( 'feed_not_found', $response->get_data()['code'], 'The not found code should be returned.' );
	}

	/**
	 * Pausing a feed clears its cron event.
	 */
	public function test_update_feed_pause_clears_cron_event() {
		$feed_id = $this->seed_scheduled_feed( time() + HOUR_IN_SECONDS );

		$response = $this->update_feed( $feed_id, [ 'status' => 'paused' ] );
		$feeds    = RSS_Importer::get_feeds();

		$this->assertSame( 200, $response->get_status(), 'Pausing should succeed.' );
		$this->assertSame( 'paused', $feeds[ $feed_id ]['status'], 'The feed should be paused.' );
		$this->assertFalse( wp_next_scheduled( RSS_Importer::CRON_HOOK, [ $feed_id ] ), 'The cron event should be cleared.' );
	}

	/**
	 * Resuming a paused feed schedules a cron event with the new interval.
	 */
	public function test_update_feed_resume_schedules_cron_event() {
		$feed_id = RSS_Importer::get_feed_id( self::PUBLIC_FEED );
		$this->seed_feeds( [ $feed_id => $this->make_feed( 'paused' ) ] );

		$response = $this->update_feed(
			$feed_id,
			[
				'status'   => 'active',
				'interval' => 'hourly',
			]
		);
		$feeds    = RSS_Importer::get_feeds();
		$event    = wp_get_scheduled_event( RSS_Importer::CRON_HOOK, [ $feed_id ] );

		$this->assertSame( 200, $response->get_status(), 'Resuming should succeed.' );
		$this->assertSame( 'active', $feeds[ $feed_id ]['status'], 'The feed should be active.' );
		$this->assertNotFalse( $event, 'A cron event should be scheduled.' );
		$this->assertSame( 'hourly', $event->schedule, 'The event should use the new interval.' );
	}

	/**
	 * Changing the interval of an active feed keeps its next run time.
	 */
	public function test_update_feed_interval_change_keeps_next_run() {
		$next_run = time() + 6 * HOUR_IN_SECONDS;
		$feed_id  = $this->seed_scheduled_feed( $next_run );

		$response = $this->update_feed( $feed_id, [ 'interval' => 'weekly' ] );
		$event    = wp_get_scheduled_event( RSS_Importer::CRON_HOOK, [ $feed_id ] );

		$this->assertSame( 200, $response->get_status(), 'Changing the interval should succeed.' );
		$this->assertNotFalse( $event, 'The cron event should still be scheduled.' );
		$this->assertSame( $next_run, $event->timestamp, 'The next run should not move.' );
		$this->assertSame( 'weekly', $event->schedule, 'The event should use the new interval.' );
	}

	/**
	 * Changing the interval of a paused feed schedules nothing.
	 */
	public function test_update_feed_interval_change_on_paused_feed_stays_unscheduled() {
		$feed_id = RSS_Importer::get_feed_id( self::PUBLIC_FEED );
		$this->seed_feeds( [ $feed_id => $this->make_feed( 'paused' ) ] );

		$response = $this->update_feed( $feed_id, [ 'interval' => 'weekly' ] );

		$this->assertSame( 200, $response->get_status(), 'Changing the interval should succeed.' );
		$this->assertFalse( wp_next_scheduled( RSS_Importer::CRON_HOOK, [ $feed_id ] ), 'A paused feed should stay unscheduled.' );
	}
}
