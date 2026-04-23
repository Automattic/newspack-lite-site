<?php
/**
 * RSS Feed Importer
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

/**
 * RSS Importer class.
 *
 * Imports posts from multiple RSS feeds as standard WordPress posts on a
 * scheduled basis using WP-Cron.
 */
class RSS_Importer {

	/**
	 * The WP-Cron hook name. Each feed passes its ID as an argument.
	 */
	const CRON_HOOK = 'newspack_lite_site_rss_import';

	/**
	 * The option name for storing all feed configurations.
	 */
	const FEEDS_OPTION_NAME = 'newspack_lite_site_rss_importer';

	/**
	 * Transient key prefix for per-feed import locks.
	 */
	const LOCK_PREFIX = 'nls_rss_importing_';

	/**
	 * Lock time-to-live in seconds. If a process crashes mid-import, the lock
	 * auto-expires after this duration so the next cron tick can proceed.
	 */
	const LOCK_TTL = 300;

	/**
	 * The supported import interval keys.
	 */
	const INTERVALS = [
		'every_5_minutes'  => null,
		'every_10_minutes' => null,
		'every_30_minutes' => null,
		'hourly'           => null,
		'twicedaily'       => null,
		'daily'            => null,
		'weekly'           => null,
	];

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, [ __CLASS__, 'run_scheduled_import' ] );
		add_filter( 'cron_schedules', [ __CLASS__, 'register_cron_intervals' ] );
		add_action( 'admin_post_nls_rss_add_feed', [ __CLASS__, 'handle_add_feed' ] );
		add_action( 'admin_post_nls_rss_feed_action', [ __CLASS__, 'handle_feed_action' ] );
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
	 * Get all configured feeds.
	 *
	 * @return array Associative array of feeds keyed by feed ID.
	 */
	public static function get_feeds() {
		$option = get_option( self::FEEDS_OPTION_NAME, [] );
		return isset( $option['feeds'] ) ? $option['feeds'] : [];
	}

	/**
	 * Persist the feeds array to the database.
	 *
	 * @param array $feeds The feeds array to save.
	 */
	private static function save_feeds( $feeds ) {
		update_option( self::FEEDS_OPTION_NAME, [ 'feeds' => $feeds ] );
	}

	/**
	 * Generate a feed ID from a URL.
	 *
	 * @param string $feed_url The feed URL.
	 * @return string MD5 hash of the URL.
	 */
	public static function get_feed_id( $feed_url ) {
		return md5( $feed_url );
	}

	/**
	 * Get the human-readable label for an interval key.
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
	 * Handle the "Add Feed" form submission.
	 */
	public static function handle_add_feed() {
		check_admin_referer( 'nls_rss_add_feed' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'newspack-lite-site' ) );
		}

		$feed_url = isset( $_POST['rss_importer_feed_url'] ) ? esc_url_raw( wp_unslash( $_POST['rss_importer_feed_url'] ) ) : '';
		$interval = isset( $_POST['rss_importer_interval'] ) ? sanitize_key( wp_unslash( $_POST['rss_importer_interval'] ) ) : 'daily';

		if ( empty( $feed_url ) || ! wp_http_validate_url( $feed_url ) ) {
			set_transient(
				'nls_rss_importer_notice',
				[
					'type'    => 'error',
					'message' => __( 'Please enter a valid feed URL.', 'newspack-lite-site' ),
				],
				60 
			);
			wp_safe_redirect( admin_url( 'admin.php?page=newspack-lite-site-rss-import' ) );
			exit;
		}

		if ( ! array_key_exists( $interval, self::INTERVALS ) ) {
			$interval = 'daily';
		}

		$feed_id = self::get_feed_id( $feed_url );
		$feeds   = self::get_feeds();

		if ( isset( $feeds[ $feed_id ] ) ) {
			set_transient(
				'nls_rss_importer_notice',
				[
					'type'    => 'error',
					'message' => __( 'This feed is already configured.', 'newspack-lite-site' ),
				],
				60 
			);
			wp_safe_redirect( admin_url( 'admin.php?page=newspack-lite-site-rss-import' ) );
			exit;
		}

		$feeds[ $feed_id ] = [
			'feed_url'    => $feed_url,
			'interval'    => $interval,
			'author_id'   => get_current_user_id(),
			'status'      => 'active',
			'last_run'    => null,
			'last_result' => null,
		];

		wp_schedule_event( time(), $interval, self::CRON_HOOK, [ $feed_id ] );

		self::save_feeds( $feeds );
		set_transient(
			'nls_rss_importer_notice',
			[
				'type'    => 'success',
				'message' => __( 'Feed added successfully.', 'newspack-lite-site' ),
			],
			60 
		);

		wp_safe_redirect( admin_url( 'admin.php?page=newspack-lite-site-rss-import' ) );
		exit;
	}

	/**
	 * Handle feed table row actions: pause, resume, delete, update_interval.
	 */
	public static function handle_feed_action() {
		check_admin_referer( 'nls_rss_feed_action' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'newspack-lite-site' ) );
		}

		$feed_id     = isset( $_POST['feed_id'] ) ? sanitize_key( wp_unslash( $_POST['feed_id'] ) ) : '';
		$feed_action = isset( $_POST['feed_action'] ) ? sanitize_key( wp_unslash( $_POST['feed_action'] ) ) : '';
		$feeds       = self::get_feeds();

		if ( empty( $feed_id ) || ! isset( $feeds[ $feed_id ] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=newspack-lite-site-rss-import' ) );
			exit;
		}

		switch ( $feed_action ) {
			case 'pause':
				$feeds[ $feed_id ]['status'] = 'paused';
				wp_clear_scheduled_hook( self::CRON_HOOK, [ $feed_id ] );
				break;

			case 'resume':
				$feeds[ $feed_id ]['status'] = 'active';
				wp_clear_scheduled_hook( self::CRON_HOOK, [ $feed_id ] );
				wp_schedule_event( time(), $feeds[ $feed_id ]['interval'], self::CRON_HOOK, [ $feed_id ] );
				break;

			case 'delete':
				wp_clear_scheduled_hook( self::CRON_HOOK, [ $feed_id ] );
				delete_transient( self::LOCK_PREFIX . $feed_id );
				unset( $feeds[ $feed_id ] );
				break;

		}

		self::save_feeds( $feeds );
		set_transient(
			'nls_rss_importer_notice',
			[
				'type'    => 'success',
				'message' => __( 'Feed updated.', 'newspack-lite-site' ),
			],
			60 
		);

		wp_safe_redirect( admin_url( 'admin.php?page=newspack-lite-site-rss-import' ) );
		exit;
	}

	/**
	 * Run the scheduled import for a specific feed.
	 *
	 * @param string $feed_id The feed ID to import.
	 */
	public static function run_scheduled_import( $feed_id = '' ) {
		if ( empty( $feed_id ) ) {
			return;
		}

		$lock_key = self::LOCK_PREFIX . $feed_id;

		// Prevent concurrent runs for this feed.
		if ( get_transient( $lock_key ) ) {
			return;
		}

		set_transient( $lock_key, time(), self::LOCK_TTL );

		$feeds = self::get_feeds();

		if ( ! isset( $feeds[ $feed_id ] ) || 'active' !== $feeds[ $feed_id ]['status'] ) {
			delete_transient( $lock_key );
			return;
		}

		$feed   = $feeds[ $feed_id ];
		$result = self::run_import( $feed['feed_url'], (int) $feed['author_id'] );

		$feeds[ $feed_id ]['last_run']    = time();
		$feeds[ $feed_id ]['last_result'] = is_wp_error( $result )
			? [ 'error' => $result->get_error_message() ]
			: $result;

		self::save_feeds( $feeds );

		delete_transient( $lock_key );
	}

	/**
	 * Run the RSS import for a given feed URL.
	 *
	 * Iterates feed items newest-first, importing each as a WordPress post.
	 * Stops on first duplicate. Also stops if approaching the PHP 
	 * max_execution_time to avoid a fatal timeout.
	 *
	 * @param string $feed_url  The RSS feed URL to import from.
	 * @param int    $author_id WordPress user ID to assign as post author.
	 * @return array|\WP_Error Array with 'imported' and 'skipped' counts, or WP_Error on failure.
	 */
	public static function run_import( $feed_url, $author_id = 0 ) {
		include_once ABSPATH . WPINC . '/feed.php';

		$feed = fetch_feed( $feed_url );

		if ( is_wp_error( $feed ) ) {
			return new \WP_Error(
				'feed_error',
				sprintf(
					/* translators: %s: error message from the feed parser */
					__( 'Could not retrieve feed: %s', 'newspack-lite-site' ),
					$feed->get_error_message()
				)
			);
		}

		$items = $feed->get_items();

		if ( empty( $items ) ) {
			return new \WP_Error( 'feed_empty', __( 'The feed contains no items.', 'newspack-lite-site' ) );
		}

		$imported   = 0;
		$skipped    = 0;
		$start_time = time();
		$time_limit = (int) ini_get( 'max_execution_time' );

		foreach ( $items as $item ) {
			// Timeout protection: stop if within 15 seconds of the PHP time limit.
			if ( $time_limit > 0 && ( time() - $start_time ) > ( $time_limit - 15 ) ) {
				break;
			}

			$result = self::import_item( $item, $feed_url, $author_id );

			if ( 'imported' === $result ) {
				$imported++;
			} elseif ( 'exists' === $result ) {
				// Break on first duplicate — everything below has been imported.
				$skipped++;
				break;
			} else {
				$skipped++;
			}
		}

		return [
			'imported' => $imported,
			'skipped'  => $skipped,
		];
	}

	/**
	 * Import a single feed item as a WordPress post.
	 *
	 * @param \SimplePie_Item $item      The feed item to import.
	 * @param string          $feed_url  The feed URL this item came from.
	 * @param int             $author_id WordPress user ID to assign as post author.
	 * @return string 'imported', 'exists', or 'skipped'.
	 */
	private static function import_item( $item, $feed_url, $author_id = 0 ) {
		$guid = $item->get_id();

		// Duplicate detection via GUID post meta.
		if ( ! empty( $guid ) ) {
			$existing = get_posts(
				[
					'post_type'      => 'any',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						[
							'key'   => '_rss_import_guid',
							'value' => $guid,
						],
					],
				]
			);

			if ( ! empty( $existing ) ) {
				return 'exists';
			}
		}

		$title   = wp_strip_all_tags( $item->get_title() ?? '' );
		$content = $item->get_content() ?? $item->get_description() ?? '';
		$date    = $item->get_date( 'Y-m-d H:i:s' );

		// Fall back to current time if the feed item has no date.
		if ( empty( $date ) ) {
			$date = current_time( 'mysql' );
		}

		$post_id = wp_insert_post(
			[
				'post_title'   => $title,
				'post_content' => wp_kses_post( $content ),
				'post_date'    => $date,
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_author'  => $author_id,
			],
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 'skipped';
		}

		// Store import metadata for duplicate detection and traceability.
		if ( ! empty( $guid ) ) {
			update_post_meta( $post_id, '_rss_import_guid', $guid );
		}

		$source_url = $item->get_permalink();
		if ( ! empty( $source_url ) ) {
			update_post_meta( $post_id, '_rss_import_source_url', esc_url_raw( $source_url ) );
		}

		update_post_meta( $post_id, '_rss_import_feed_url', esc_url_raw( $feed_url ) );

		// Detect and sideload the featured image.
		$image_url = self::get_featured_image_url( $item );
		if ( ! empty( $image_url ) && wp_http_validate_url( $image_url ) ) {
			self::import_featured_image( $image_url, $post_id, $title );
		}

		return 'imported';
	}

	/**
	 * Detect the best featured image URL from a feed item.
	 *
	 * Priority order:
	 * 1. <media:thumbnail> — most explicit declaration from publisher.
	 * 2. <enclosure> or <media:content> with an image MIME type.
	 * 3. First <img> tag found in the item body content.
	 *
	 * @param \SimplePie_Item $item The feed item.
	 * @return string Image URL, or empty string if none found.
	 */
	private static function get_featured_image_url( $item ) {
		$enclosure = $item->get_enclosure();

		if ( $enclosure ) {
			// Priority 1: explicit media:thumbnail.
			$thumbnail = $enclosure->get_thumbnail();
			if ( ! empty( $thumbnail ) && wp_http_validate_url( $thumbnail ) ) {
				return $thumbnail;
			}

			// Priority 2: enclosure or media:content with an image MIME type.
			$link      = $enclosure->get_link();
			$mime_type = $enclosure->get_type();
			if ( ! empty( $link ) && ! empty( $mime_type ) && str_starts_with( $mime_type, 'image/' ) ) {
				return $link;
			}
		}

		// Also check all enclosures — the first may not have an image but others might.
		$enclosures = $item->get_enclosures();
		if ( ! empty( $enclosures ) ) {
			foreach ( $enclosures as $enc ) {
				$thumbnail = $enc->get_thumbnail();
				if ( ! empty( $thumbnail ) && wp_http_validate_url( $thumbnail ) ) {
					return $thumbnail;
				}

				$link      = $enc->get_link();
				$mime_type = $enc->get_type();
				if ( ! empty( $link ) && ! empty( $mime_type ) && str_starts_with( $mime_type, 'image/' ) ) {
					return $link;
				}
			}
		}

		// Priority 3: first <img> src found in the post body.
		$content = $item->get_content() ?? $item->get_description() ?? '';
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
}
