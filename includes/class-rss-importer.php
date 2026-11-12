<?php
/**
 * RSS Feed Importer.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

defined( 'ABSPATH' ) || exit;

/**
 * RSS Importer class.
 *
 * Imports posts from multiple RSS feeds as standard WordPress posts on a
 * scheduled basis using WP-Cron.
 */
class RSS_Importer {

	/**
	 * The WP-Cron hook name. Each RSS feed passes its ID as an argument.
	 */
	const CRON_HOOK = 'newspack_lite_site_rss_import';

	/**
	 * The option name for storing all RSS feed configurations.
	 */
	const FEEDS_OPTION_NAME = 'newspack_lite_site_rss_importer';

	/**
	 * Transient key prefix for per-RSS-feed import locks.
	 */
	const LOCK_PREFIX = 'nls_rss_importing_';

	/**
	 * Lock time-to-live in seconds. If a process crashes mid-import, the lock
	 * auto-expires after this duration so the next cron tick can proceed.
	 */
	const LOCK_TTL = 300;

	/**
	 * Number of RSS feed items to check for duplicates in a single DB query.
	 */
	const GUID_BATCH_SIZE = 20;

	/**
	 * Maximum number of new posts to import in a single run.
	 */
	const MAX_ITEMS_PER_RUN = 50;

	/**
	 * Initialize the importer functionality.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, [ __CLASS__, 'run_scheduled_import' ] );
		add_filter( 'cron_schedules', [ __CLASS__, 'register_cron_intervals' ] ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
	}

	/**
	 * Check whether the current user has permission to manage plugin options.
	 *
	 * @return bool True if the current user can manage options.
	 */
	public static function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register REST API routes for the RSS Feeds admin UI.
	 */
	public static function register_rest_routes() {
		$namespace = 'newspack-lite-site/v1';

		register_rest_route(
			$namespace,
			'/rss-feeds',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'rest_get_feeds' ],
					'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'rest_add_feed' ],
					'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
					'args'                => [
						'feed_url'  => [
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'esc_url_raw',
						],
						'interval'  => [
							'type'              => 'string',
							'default'           => 'daily',
							'sanitize_callback' => 'sanitize_key',
						],
						'author_id' => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
						'status'    => [
							'type'    => 'string',
							'enum'    => [ 'active', 'paused' ],
							'default' => 'active',
						],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/rss-feeds/(?P<id>[a-f0-9]{32})',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'rest_update_feed' ],
				'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
				'args'                => [
					'id'        => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					'interval'  => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'author_id' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'status'    => [
						'type' => 'string',
						'enum' => [ 'active', 'paused' ],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/rss-feeds/(?P<id>[a-f0-9]{32})/action',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'rest_feed_action' ],
				'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
				'args'                => [
					'id'     => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					'action' => [
						'type'     => 'string',
						'required' => true,
						'enum'     => [ 'pause', 'resume', 'delete', 'import' ],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/rss-feeds/delete',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'rest_delete_feeds' ],
				'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
				'args'                => [
					'ids' => [
						'type'              => 'array',
						'required'          => true,
						'items'             => [
							'type'    => 'string',
							'pattern' => '^[a-f0-9]{32}$',
						],
						'minItems'          => 1,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => static function ( $ids ) {
							return array_values( array_unique( array_map( 'sanitize_key', wp_parse_list( $ids ) ) ) );
						},
					],
				],
			]
		);
	}

	/**
	 * Build the REST-ready representation of a single RSS feed.
	 *
	 * Adds server-computed fields (author_name, interval_label, next_run) so the
	 * admin UI never needs to call WP functions directly.
	 *
	 * @param string $feed_id The RSS feed ID (md5 hash).
	 * @param array  $feed    The raw RSS feed data from the option.
	 * @return array REST-ready RSS feed object.
	 */
	private static function build_rest_feed( $feed_id, $feed ) {
		$interval_labels = self::get_interval_labels();
		$next_run        = wp_next_scheduled( self::CRON_HOOK, [ $feed_id ] );
		$author          = get_userdata( (int) ( $feed['author_id'] ?? 0 ) );

		return [
			'id'                 => $feed_id,
			'feed_url'           => $feed['feed_url'],
			'interval'           => $feed['interval'],
			'interval_label'     => $interval_labels[ $feed['interval'] ] ?? $feed['interval'],
			'author_id'          => (int) ( $feed['author_id'] ?? 0 ),
			'author_name'        => $author ? $author->display_name : '',
			'author_avatar_urls' => $author ? Post_Type::get_user_avatar_urls( $author->ID ) : [],
			'status'             => $feed['status'],
			'last_run'           => $feed['last_run'],
			'last_result'        => $feed['last_result'],
			'next_run'           => $next_run ? $next_run : null,
		];
	}

	/**
	 * Build the REST response containing the full list of RSS feeds.
	 *
	 * @param array $feeds RSS feeds keyed by feed ID.
	 * @return \WP_REST_Response
	 */
	private static function rest_feeds_response( $feeds ) {
		$data = [];

		foreach ( $feeds as $feed_id => $feed ) {
			$data[] = self::build_rest_feed( $feed_id, $feed );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * List all RSS feeds.
	 *
	 * Returns every configured feed enriched with computed fields (next run time,
	 * human-readable interval label, etc.) for consumption by the admin UI.
	 *
	 * @return \WP_REST_Response
	 */
	public static function rest_get_feeds() {
		return self::rest_feeds_response( self::get_feeds() );
	}

	/**
	 * Register and schedule a new RSS feed.
	 *
	 * Validates the RSS feed URL, author, import interval and optional status submitted by the admin UI,
	 * persists the RSS feed configuration, schedules the first WP-Cron import event unless the feed is added paused, and
	 * returns the full refreshed RSS feeds list.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_add_feed( \WP_REST_Request $request ) {
		$params    = $request->get_params();
		$feed_url  = $params['feed_url'] ?? '';
		$interval  = $params['interval'] ?? 'daily';
		$author_id = $params['author_id'] ?? 0;
		$status    = $params['status'] ?? 'active';

		if ( empty( $feed_url ) || ! self::is_safe_url( $feed_url ) ) {
			return new \WP_Error(
				'invalid_url',
				__( 'Please enter a valid RSS feed URL.', 'newspack-lite-site' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! $author_id || ! user_can( $author_id, 'publish_posts' ) ) {
			return new \WP_Error(
				'invalid_author',
				__( 'Please select a valid author.', 'newspack-lite-site' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! array_key_exists( $interval, self::get_interval_labels() ) ) {
			return new \WP_Error(
				'invalid_interval',
				__( 'Choose how often to import this RSS feed.', 'newspack-lite-site' ),
				[ 'status' => 400 ]
			);
		}

		$feed_id = self::get_feed_id( $feed_url );
		$feeds   = self::get_feeds();

		if ( isset( $feeds[ $feed_id ] ) ) {
			return new \WP_Error(
				'duplicate_feed',
				__( 'This RSS feed is already in the list.', 'newspack-lite-site' ),
				[ 'status' => 409 ]
			);
		}

		$feeds[ $feed_id ] = [
			'feed_url'    => $feed_url,
			'interval'    => $interval,
			'author_id'   => $author_id,
			'status'      => 'paused' === $status ? 'paused' : 'active',
			'last_run'    => null,
			'last_result' => null,
		];

		if ( 'paused' === $feeds[ $feed_id ]['status'] ) {
			wp_clear_scheduled_hook( self::CRON_HOOK, [ $feed_id ] );
			$scheduled = true;
		} elseif ( wp_next_scheduled( self::CRON_HOOK, [ $feed_id ] ) ) {
			$scheduled = true;
		} else {
			$scheduled = wp_schedule_event( time(), $interval, self::CRON_HOOK, [ $feed_id ] );
		}

		if ( false === $scheduled ) {
			return new \WP_Error(
				'schedule_failed',
				__( 'Failed to schedule the RSS feed.', 'newspack-lite-site' ),
				[ 'status' => 500 ]
			);
		}

		self::save_feeds( $feeds );

		return self::rest_feeds_response( $feeds );
	}

	/**
	 * Update a feed's interval, author and status.
	 *
	 * Only the fields present in the request are validated and changed. The URL is
	 * the feed's identity and cannot be edited. Scheduling follows the change: pausing
	 * clears the cron event, resuming schedules a fresh one, and changing the interval
	 * of an active feed keeps its next run time, capped at one new interval from now, so no
	 * import is triggered early and none is delayed past what the new interval allows.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_update_feed( \WP_REST_Request $request ) {
		$params  = $request->get_params();
		$feed_id = $params['id'] ?? '';
		$feeds   = self::get_feeds();

		if ( empty( $feed_id ) || ! isset( $feeds[ $feed_id ] ) ) {
			return new \WP_Error(
				'feed_not_found',
				__( 'Feed not found.', 'newspack-lite-site' ),
				[ 'status' => 404 ]
			);
		}

		$feed         = $feeds[ $feed_id ];
		$was_active   = 'active' === $feed['status'];
		$old_interval = $feed['interval'];

		if ( isset( $params['author_id'] ) ) {
			$author_id = $params['author_id'];

			if ( ! $author_id || ! user_can( $author_id, 'publish_posts' ) ) {
				return new \WP_Error(
					'invalid_author',
					__( 'Please select a valid author.', 'newspack-lite-site' ),
					[ 'status' => 400 ]
				);
			}

			$feed['author_id'] = $author_id;
		}

		if ( isset( $params['interval'] ) ) {
			if ( ! array_key_exists( $params['interval'], self::get_interval_labels() ) ) {
				return new \WP_Error(
					'invalid_interval',
					__( 'Choose how often to import this RSS feed.', 'newspack-lite-site' ),
					[ 'status' => 400 ]
				);
			}

			$feed['interval'] = $params['interval'];
		}

		if ( isset( $params['status'] ) ) {
			$feed['status'] = $params['status'];
		}

		$is_active = 'active' === $feed['status'];

		if ( $was_active && ! $is_active ) {
			wp_clear_scheduled_hook( self::CRON_HOOK, [ $feed_id ] );
		} elseif ( ! $was_active && $is_active ) {
			wp_clear_scheduled_hook( self::CRON_HOOK, [ $feed_id ] );

			if ( false === wp_schedule_event( time(), $feed['interval'], self::CRON_HOOK, [ $feed_id ] ) ) {
				return new \WP_Error(
					'schedule_failed',
					__( 'Failed to resume the RSS feed.', 'newspack-lite-site' ),
					[ 'status' => 500 ]
				);
			}
		} elseif ( $is_active && $feed['interval'] !== $old_interval ) {
			$schedules = wp_get_schedules();
			$original  = wp_next_scheduled( self::CRON_HOOK, [ $feed_id ] );
			$original  = $original ? $original : time();
			$next_run  = $original;

			if ( isset( $schedules[ $feed['interval'] ]['interval'] ) ) {
				$next_run = min( $next_run, time() + (int) $schedules[ $feed['interval'] ]['interval'] );
			}

			wp_clear_scheduled_hook( self::CRON_HOOK, [ $feed_id ] );

			if ( false === wp_schedule_event( $next_run, $feed['interval'], self::CRON_HOOK, [ $feed_id ] ) ) {
				if ( false === wp_schedule_event( $original, $old_interval, self::CRON_HOOK, [ $feed_id ] ) ) {
					$feeds[ $feed_id ]['status'] = 'paused';
					self::save_feeds( $feeds );

					return new \WP_Error(
						'schedule_failed',
						__( 'Couldn\'t update the RSS feed, so it has been paused.', 'newspack-lite-site' ),
						[ 'status' => 500 ]
					);
				}

				return new \WP_Error(
					'schedule_failed',
					__( 'Failed to update the RSS feed.', 'newspack-lite-site' ),
					[ 'status' => 500 ]
				);
			}
		}

		$feeds[ $feed_id ] = $feed;
		self::save_feeds( $feeds );

		return self::rest_feeds_response( $feeds );
	}

	/**
	 * Pause, resume, import or delete a feed.
	 *
	 * Applies the action requested by the admin UI to the feed identified by its ID.
	 * Pausing unschedules the WP-Cron event; resuming reschedules it; importing runs the scheduled import now; deleting removes
	 * the feed entirely.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_feed_action( $request ) {
		$params      = $request->get_params();
		$feed_id     = $params['id'] ?? '';
		$feed_action = $params['action'] ?? '';
		$feeds       = self::get_feeds();

		if ( empty( $feed_id ) || ! isset( $feeds[ $feed_id ] ) ) {
			return new \WP_Error(
				'feed_not_found',
				__( 'Feed not found.', 'newspack-lite-site' ),
				[ 'status' => 404 ]
			);
		}

		if ( 'import' === $feed_action ) {
			if ( 'active' !== $feeds[ $feed_id ]['status'] ) {
				return new \WP_Error(
					'feed_paused',
					__( 'Resume the RSS feed to import it.', 'newspack-lite-site' ),
					[ 'status' => 400 ]
				);
			}

			if ( ! self::run_scheduled_import( $feed_id ) ) {
				return new \WP_Error(
					'feed_busy',
					__( 'This RSS feed is already importing. Try again in a few minutes.', 'newspack-lite-site' ),
					[ 'status' => 409 ]
				);
			}

			return self::rest_feeds_response( self::get_feeds() );
		}

		switch ( $feed_action ) {
			case 'pause':
				$feeds[ $feed_id ]['status'] = 'paused';
				wp_clear_scheduled_hook( self::CRON_HOOK, [ $feed_id ] );
				break;

			case 'resume':
				$feeds[ $feed_id ]['status'] = 'active';
				wp_clear_scheduled_hook( self::CRON_HOOK, [ $feed_id ] );
				$scheduled = wp_schedule_event( time(), $feeds[ $feed_id ]['interval'], self::CRON_HOOK, [ $feed_id ] );

				if ( false === $scheduled ) {
					$feeds[ $feed_id ]['status'] = 'paused';
					self::save_feeds( $feeds );
					return new \WP_Error(
						'schedule_failed',
						__( 'Failed to resume the RSS feed.', 'newspack-lite-site' ),
						[ 'status' => 500 ]
					);
				}
				break;

			case 'delete':
				wp_clear_scheduled_hook( self::CRON_HOOK, [ $feed_id ] );
				unset( $feeds[ $feed_id ] );
				break;
		}

		self::save_feeds( $feeds );

		return self::rest_feeds_response( $feeds );
	}

	/**
	 * Delete several RSS feeds at once.
	 *
	 * Unknown IDs are ignored, so repeating a request is safe. Returns the full refreshed list.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public static function rest_delete_feeds( \WP_REST_Request $request ) {
		$feeds = self::get_feeds();

		foreach ( (array) $request->get_param( 'ids' ) as $feed_id ) {
			if ( isset( $feeds[ $feed_id ] ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK, [ $feed_id ] );
				unset( $feeds[ $feed_id ] );
			}
		}

		self::save_feeds( $feeds );

		return self::rest_feeds_response( $feeds );
	}

	/**
	 * Register additional WP-Cron intervals not included in WordPress core.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public static function register_cron_intervals( $schedules ) {
		$custom_intervals = [
			'every_5_minutes'  => [
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 Minutes', 'newspack-lite-site' ),
			],
			'every_10_minutes' => [
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 10 Minutes', 'newspack-lite-site' ),
			],
			'every_30_minutes' => [
				'interval' => 30 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 30 Minutes', 'newspack-lite-site' ),
			],
			'weekly'           => [
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'newspack-lite-site' ),
			],
		];

		foreach ( $custom_intervals as $key => $schedule ) {
			if ( ! isset( $schedules[ $key ] ) ) {
				$schedules[ $key ] = $schedule;
			}
		}

		return $schedules;
	}

	/**
	 * Get all configured RSS feeds.
	 *
	 * @return array Associative array of RSS feeds keyed by feed ID.
	 */
	public static function get_feeds() {
		$option = get_option( self::FEEDS_OPTION_NAME, [] );
		return isset( $option['feeds'] ) ? $option['feeds'] : [];
	}

	/**
	 * Persist the RSS feeds array to the database.
	 *
	 * @param array $feeds The RSS feeds array to save.
	 */
	private static function save_feeds( $feeds ) {
		update_option( self::FEEDS_OPTION_NAME, [ 'feeds' => $feeds ] );
	}

	/**
	 * Generate a feed ID from a URL.
	 *
	 * @param string $feed_url The RSS feed URL.
	 * @return string MD5 hash of the URL.
	 */
	public static function get_feed_id( $feed_url ) {
		return md5( $feed_url );
	}

	/**
	 * Return all supported import intervals with their translated display labels.
	 *
	 * @return array Associative array of interval keys to translated labels.
	 */
	public static function get_interval_labels() {
		return [
			'every_5_minutes'  => __( 'Every 5 Minutes', 'newspack-lite-site' ),
			'every_10_minutes' => __( 'Every 10 Minutes', 'newspack-lite-site' ),
			'every_30_minutes' => __( 'Every 30 Minutes', 'newspack-lite-site' ),
			'hourly'           => __( 'Hourly', 'newspack-lite-site' ),
			'twicedaily'       => __( 'Twice Daily', 'newspack-lite-site' ),
			'daily'            => __( 'Daily', 'newspack-lite-site' ),
			'weekly'           => __( 'Weekly', 'newspack-lite-site' ),
		];
	}

	/**
	 * Run the scheduled import for a specific RSS feed.
	 *
	 * @param string $feed_id The RSS feed ID to import.
	 * @return bool True if the import ran, false if it was skipped (locked, paused or missing feed).
	 */
	public static function run_scheduled_import( $feed_id = '' ) {
		if ( empty( $feed_id ) ) {
			return false;
		}

		$lock_key = self::LOCK_PREFIX . $feed_id;

		// Prevent concurrent runs for this RSS feed.
		if ( get_transient( $lock_key ) ) {
			return false;
		}

		set_transient( $lock_key, time(), self::LOCK_TTL );

		$feeds = self::get_feeds();

		if ( ! isset( $feeds[ $feed_id ] ) || 'active' !== $feeds[ $feed_id ]['status'] ) {
			delete_transient( $lock_key );
			return false;
		}

		$feed   = $feeds[ $feed_id ];
		$result = self::run_import( $feed['feed_url'], (int) $feed['author_id'] );

		wp_cache_delete( 'alloptions', 'options' );
		$feeds = self::get_feeds();

		// RSS feed deleted mid-run; skip write to avoid creating a ghost entry.
		if ( ! isset( $feeds[ $feed_id ] ) ) {
			delete_transient( $lock_key );
			return false;
		}

		$feeds[ $feed_id ]['last_run']    = time();
		$feeds[ $feed_id ]['last_result'] = is_wp_error( $result )
			? array_filter(
				[
					'error'  => $result->get_error_message(),
					'detail' => $result->get_error_data( 'feed_error' ),
				]
			)
			: $result;

		self::save_feeds( $feeds );

		delete_transient( $lock_key );

		return true;
	}

	/**
	 * Run the RSS import for a given RSS feed URL.
	 *
	 * Iterates feed items newest-first, importing each as a WordPress post.
	 * Stops on first duplicate. Also stops if approaching the PHP
	 * max_execution_time to avoid a fatal timeout.
	 *
	 * @param string $feed_url  The RSS feed URL to import from.
	 * @param int    $author_id WordPress user ID to assign as post author.
	 * @return array|\WP_Error Array with 'imported', 'failed' and 'up_to_date' keys (plus 'note' for an empty feed), or WP_Error on failure.
	 */
	public static function run_import( $feed_url, $author_id = 0 ) {
		if ( empty( $feed_url ) || ! self::is_safe_url( $feed_url ) ) {
			return new \WP_Error( 'invalid_url', __( 'A valid RSS feed URL is required.', 'newspack-lite-site' ) );
		}

		if ( ! $author_id || ! user_can( $author_id, 'publish_posts' ) ) {
			return new \WP_Error( 'invalid_author', __( 'The author set for this feed can no longer publish posts. Edit the feed to choose another author.', 'newspack-lite-site' ) );
		}

		add_filter( 'pre_http_request', [ __CLASS__, 'block_ssrf_request' ], 10, 3 );
		add_filter( 'http_request_args', [ __CLASS__, 'cap_import_request_args' ], 10, 2 ); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args -- not modifying timeout.

		try {
			include_once ABSPATH . WPINC . '/feed.php';

			$feed = fetch_feed( $feed_url );

			if ( is_wp_error( $feed ) ) {
				return new \WP_Error(
					'feed_error',
					__( "Couldn't reach this RSS feed.", 'newspack-lite-site' ),
					$feed->get_error_message()
				);
			}

			$items = $feed->get_items();

			if ( empty( $items ) ) {
				return [
					'imported'   => 0,
					'failed'     => 0,
					'up_to_date' => true,
					'note'       => __( 'The RSS feed contains no items.', 'newspack-lite-site' ),
				];
			}

			$imported   = 0;
			$failed     = 0;
			$up_to_date = false;
			$start_time = time();
			$time_limit = (int) ini_get( 'max_execution_time' );
			$batches    = array_chunk( $items, self::GUID_BATCH_SIZE );

			foreach ( $batches as $batch ) {
				if ( $imported >= self::MAX_ITEMS_PER_RUN ) {
					break;
				}

				$existing_guids = self::get_existing_guids( $batch );

				foreach ( $batch as $item ) {
					if ( $imported >= self::MAX_ITEMS_PER_RUN ) {
						break 2;
					}

					// Timeout protection: stop if within 15 seconds of the PHP time limit.
					if ( $time_limit > 0 && ( time() - $start_time ) > ( $time_limit - 15 ) ) {
						break 2;
					}

					$result = self::import_item( $item, $feed_url, $author_id, $existing_guids );

					if ( 'imported' === $result ) {
						$imported++;
					} elseif ( 'exists' === $result ) {
						$up_to_date = true;
					} else {
						$failed++;
					}
				}
			}

			return [
				'imported'   => $imported,
				'failed'     => $failed,
				'up_to_date' => $up_to_date,
			];
		} finally {
			remove_filter( 'pre_http_request', [ __CLASS__, 'block_ssrf_request' ], 10 );
			remove_filter( 'http_request_args', [ __CLASS__, 'cap_import_request_args' ], 10 );
		}
	}

	/**
	 * Derive a stable GUID for a RSS feed item.
	 *
	 * Falls back from RSS GUID → permalink → title+date hash so items without an
	 * explicit <guid> element are still tracked across runs.
	 *
	 * @param \SimplePie_Item $item The RSS feed item.
	 * @return string Non-empty GUID.
	 */
	private static function get_item_guid( $item ): string {
		$guid = $item->get_id();
		if ( ! empty( $guid ) ) {
			return $guid;
		}

		$permalink = $item->get_permalink();
		if ( ! empty( $permalink ) ) {
			return 'nls_url:' . md5( $permalink );
		}

		$title = $item->get_title() ?? '';
		$date  = $item->get_date( 'c' ) ?? '';
		return 'nls_hash:' . md5( $title . $date );
	}

	/**
	 * Fetch the set of already-imported GUIDs for a batch of RSS feed items.
	 *
	 * @param \SimplePie_Item[] $items Batch of RSS feed items to check.
	 * @return array<string, true> Map of existing GUIDs.
	 */
	private static function get_existing_guids( array $items ): array {
		$guids = array_values(
			array_map( fn( $item ) => self::get_item_guid( $item ), $items )
		);

		if ( empty( $guids ) ) {
			return [];
		}

		$existing_posts = get_posts(
			[
				'post_type'              => Post_Type::POST_TYPE,
				'post_status'            => [ 'publish', 'draft', 'pending', 'future', 'private', 'trash' ],
				'posts_per_page'         => count( $guids ),
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => '_rss_import_guid',
						'value'   => $guids,
						'compare' => 'IN',
					],
				],
			]
		);

		$existing_guids = [];
		foreach ( $existing_posts as $post ) {
			$guid = get_post_meta( $post->ID, '_rss_import_guid', true );
			if ( $guid ) {
				$existing_guids[ $guid ] = true;
			}
		}

		return $existing_guids;
	}

	/**
	 * Import a single RSS feed item as a WordPress post.
	 *
	 * @param \SimplePie_Item     $item           The RSS feed item to import.
	 * @param string              $feed_url       The RSS feed URL this item came from.
	 * @param int                 $author_id      WordPress user ID to assign as post author.
	 * @param array<string, true> $existing_guids Pre-fetched map of already-imported GUIDs.
	 * @return string 'imported', 'exists', or 'failed'.
	 */
	private static function import_item( $item, $feed_url, $author_id = 0, array $existing_guids = [] ) {
		$guid = self::get_item_guid( $item );

		if ( isset( $existing_guids[ $guid ] ) ) {
			return 'exists';
		}

		$title   = wp_strip_all_tags( $item->get_title() ?? '' );
		$content = $item->get_content() ?? '';
		$date    = $item->get_date( 'Y-m-d H:i:s' );

		// Fall back to current time if the RSS feed item has no date.
		if ( empty( $date ) ) {
			$date = current_time( 'mysql' );
		}

		$post_id = wp_insert_post(
			[
				'post_title'   => $title,
				'post_content' => wp_kses_post( $content ),
				'post_date'    => $date,
				'post_status'  => 'publish',
				'post_type'    => Post_Type::POST_TYPE,
				'post_author'  => $author_id,
			],
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 'failed';
		}

		update_post_meta( $post_id, '_rss_import_guid', $guid );

		$source_url = $item->get_permalink();
		if ( ! empty( $source_url ) ) {
			update_post_meta( $post_id, '_rss_import_source_url', esc_url_raw( $source_url ) );
		}

		update_post_meta( $post_id, '_rss_import_feed_url', esc_url_raw( $feed_url ) );

		// Detect and sideload the featured image.
		$image_url = self::get_featured_image_url( $item );
		if ( ! empty( $image_url ) && self::is_safe_url( $image_url ) ) {
			self::import_featured_image( $image_url, $post_id, $title );
		}

		return 'imported';
	}

	/**
	 * Detect the best featured image URL from a RSS feed item.
	 *
	 * Priority order:
	 * 1. <media:thumbnail> — most explicit declaration from publisher.
	 * 2. <enclosure> or <media:content> with an image MIME type.
	 * 3. First <img> tag found in the item body content.
	 *
	 * @param \SimplePie_Item $item The RSS feed item.
	 * @return string Image URL, or empty string if none found.
	 */
	private static function get_featured_image_url( $item ) {
		$enclosure = $item->get_enclosure();

		if ( $enclosure ) {
			// Priority 1: explicit media:thumbnail.
			$thumbnail = $enclosure->get_thumbnail();
			if ( ! empty( $thumbnail ) && self::is_safe_url( $thumbnail ) ) {
				return $thumbnail;
			}

			// Priority 2: enclosure or media:content with an image MIME type.
			$link      = $enclosure->get_link();
			$mime_type = $enclosure->get_type();
			if ( ! empty( $link ) && self::is_safe_url( $link ) && ! empty( $mime_type ) && str_starts_with( $mime_type, 'image/' ) ) {
				return $link;
			}
		}

		// Also check all enclosures — the first may not have an image but others might.
		$enclosures = $item->get_enclosures();
		if ( ! empty( $enclosures ) ) {
			foreach ( $enclosures as $enc ) {
				$thumbnail = $enc->get_thumbnail();
				if ( ! empty( $thumbnail ) && self::is_safe_url( $thumbnail ) ) {
					return $thumbnail;
				}

				$link      = $enc->get_link();
				$mime_type = $enc->get_type();
				if ( ! empty( $link ) && self::is_safe_url( $link ) && ! empty( $mime_type ) && str_starts_with( $mime_type, 'image/' ) ) {
					return $link;
				}
			}
		}

		// Priority 3: first <img> src found in the RSS feed item body.
		$content = $item->get_content() ?? '';
		if ( ! empty( $content ) ) {
			preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
			if ( ! empty( $matches[1] ) ) {
				return $matches[1];
			}
		}

		return '';
	}

	/**
	 * Download a remote image and set it as the featured image for a post.
	 *
	 * @param string $image_url The remote image URL to sideload.
	 * @param int    $post_id   The post to attach the image to.
	 * @param string $title     Used as the image alt/title attribute.
	 */
	private static function import_featured_image( $image_url, $post_id, $title ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $image_url, $post_id, $title, 'id' );

		if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * Check whether a URL resolves to a safe, publicly routable address.
	 *
	 * Validates the URL structure, resolves the hostname, and rejects private,
	 * reserved, and link-local ranges to prevent SSRF.
	 *
	 * @param string $url URL to validate.
	 * @return bool True if safe to fetch, false otherwise.
	 */
	private static function is_safe_url( string $url ): bool {
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return false;
		}

		$ip = gethostbyname( $host );

		$validated_ip = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );

		return (bool) $validated_ip;
	}

	/**
	 * Cap redirect count and response size for all HTTP requests during an import run.
	 *
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public static function cap_import_request_args( $args, $url ) {
		$args['redirection']         = min( (int) $args['redirection'], 2 );
		$args['limit_response_size'] = 3 * 1024 * 1024; // 3MB.
		return $args;
	}

	/**
	 * Prevent requests to private or reserved addresses during import runs.
	 *
	 * Hooked to pre_http_request for the duration of each import so every outbound
	 * fetch — feed and images alike — is validated before the request is made.
	 *
	 * @param false|array|\WP_Error $preempt Return value to short-circuit the request.
	 * @param array                 $args    Request arguments.
	 * @param string                $url     Request URL.
	 * @return false|array|\WP_Error
	 */
	public static function block_ssrf_request( $preempt, $args, $url ) {
		if ( ! self::is_safe_url( $url ) ) {
			return new \WP_Error( 'ssrf_blocked', __( 'This address is on a private network, so it can\'t be imported.', 'newspack-lite-site' ) );
		}
		return $preempt;
	}
}
