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
 * Imports posts from an external RSS feed as standard WordPress posts,
 * including featured image detection and sideloading.
 */
class RSS_Importer {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_newspack_lite_site_rss_import', [ __CLASS__, 'handle_ajax_import' ] );
	}

	/**
	 * Handle the AJAX import request.
	 * Validates the request, runs the import, and returns a JSON response.
	 */
	public static function handle_ajax_import() {
		check_ajax_referer( 'newspack_lite_site_rss_import' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'newspack-lite-site' ) );
		}

		$feed_url = isset( $_POST['rss_feed_url'] ) ? esc_url_raw( wp_unslash( $_POST['rss_feed_url'] ) ) : '';

		if ( empty( $feed_url ) || ! wp_http_validate_url( $feed_url ) ) {
			wp_send_json_error( __( 'Please enter a valid feed URL.', 'newspack-lite-site' ) );
		}

		$result = self::run_import( $feed_url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: 1: number of posts imported, 2: number of posts skipped */
					__( 'Import complete: %1$d post(s) imported, %2$d skipped (already existed).', 'newspack-lite-site' ),
					$result['imported'],
					$result['skipped']
				),
			]
		);
	}

	/**
	 * Run the RSS import for a given feed URL.
	 *
	 * @param string $feed_url The RSS feed URL to import from.
	 * @return array|WP_Error Array with 'imported' and 'skipped' counts, or WP_Error on failure.
	 */
	public static function run_import( $feed_url ) {
		include_once ABSPATH . WPINC . '/feed.php';

		$feed = fetch_feed( $feed_url );

		if ( is_wp_error( $feed ) ) {
			return new WP_Error(
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
			return new WP_Error( 'feed_empty', __( 'The feed contains no items.', 'newspack-lite-site' ) );
		}

		$imported = 0;
		$skipped  = 0;

		foreach ( $items as $item ) {
			$result = self::import_item( $item, $feed_url );
			if ( 'imported' === $result ) {
				$imported++;
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
	 * @param SimplePie_Item $item     The feed item to import.
	 * @param string         $feed_url The feed URL this item came from.
	 * @return string 'imported' or 'skipped'.
	 */
	private static function import_item( $item, $feed_url ) {
		$guid = $item->get_id();

		// Skip if already imported (duplicate detection via GUID post meta).
		if ( ! empty( $guid ) ) {
			$cache_key = 'rss_import_guid_' . md5( $guid );
			$existing  = wp_cache_get( $cache_key, 'newspack_lite_site' );

			if ( false === $existing ) {
				$posts    = get_posts(
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
				$existing = ! empty( $posts ) ? $posts[0] : 0;
				wp_cache_set( $cache_key, $existing, 'newspack_lite_site' );
			}

			if ( $existing ) {
				return 'skipped';
			}
		}

		$title   = wp_strip_all_tags( $item->get_title() ?? '' );
		$content = $item->get_content() ?? $item->get_description() ?? '';
		$date    = $item->get_date( 'Y-m-d H:i:s' );

		// Fall back to current time if no date in feed.
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
				'post_author'  => get_current_user_id(),
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

		// Detect and import featured image.
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
	 * 1. <media:thumbnail> — most explicit declaration from publisher
	 * 2. <enclosure> or <media:content> with an image MIME type
	 * 3. First <img> tag found in the post body content
	 *
	 * @param SimplePie_Item $item The feed item.
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

			// Priority 2: enclosure or media:content that is an image.
			$link      = $enclosure->get_link();
			$mime_type = $enclosure->get_type();
			if ( ! empty( $link ) && ! empty( $mime_type ) && str_starts_with( $mime_type, 'image/' ) ) {
				return $link;
			}
		}

		// Also check all enclosures in case the first one had no image but others do.
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
	 * @param string $title     The post title, used as the image alt/title.
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
