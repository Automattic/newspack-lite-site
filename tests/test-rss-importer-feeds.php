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
}
