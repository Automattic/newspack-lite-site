<?php
/**
 * Plugin Name: Lite Site screenshot fakes
 * Description: Stands in for the services the docs screenshots show, on a site set aside for screenshots. The seed installs and removes this file.
 *
 * Answers the scheduled runs and Retry Now of the demo RSS feeds, which point
 * at made-up hosts, with the result each feed is meant to show. Serves the
 * fonts of Lite Site and the theme from this folder in place of the font
 * provider's stylesheets.
 * Blocks every request WordPress's HTTP API makes to another host, and all
 * email, and gives every account a fixed avatar. Does nothing on a site the
 * seed hasn't seeded.
 *
 * @package newspack-lite-site
 */

defined( 'ABSPATH' ) || exit;

if ( ! get_option( 'newspack_ls_screenshots_state' ) ) {
	return;
}

/**
 * The demo content and settings from content.json, or an empty array.
 *
 * @return array
 */
function newspack_ls_screenshots_fakes_content() {
	static $content = null;
	if ( null === $content ) {
		$file    = defined( 'NEWSPACK_LITE_SITE_PLUGIN_DIR' ) ? NEWSPACK_LITE_SITE_PLUGIN_DIR . 'site/screenshots/content.json' : '';
		$json    = $file && is_readable( $file ) ? file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$content = json_decode( (string) $json, true );
		$content = is_array( $content ) ? $content : [];
	}
	return $content;
}

/**
 * Stands in for an import of a demo RSS feed, with the result the feed is
 * meant to show: the one content.json gives it. The feed that can't be
 * reached keeps failing, unless the `newspack_ls_screenshots_retry_works`
 * option is set, when it comes back up to date. Every run, scheduled or Retry
 * Now, records its time as the feed's last run, as a real import would.
 *
 * @param string $feed_id Feed ID.
 * @return bool Whether the feed is a demo feed that was answered.
 */
function newspack_ls_screenshots_fake_import( $feed_id ) {
	$state = get_option( 'newspack_ls_screenshots_state' );
	$key   = $state['feeds'][ $feed_id ] ?? null;
	if ( null === $key ) {
		return false;
	}
	$feeds = get_option( 'newspack_lite_site_rss_importer', [] );
	$seed  = newspack_ls_screenshots_fakes_content()['feeds'][ $key ]['last_result'] ?? null;
	if ( ! isset( $feeds['feeds'][ $feed_id ] ) || ! is_array( $seed ) ) {
		return false;
	}

	$result = $seed;
	if ( ! empty( $seed['error'] ) && get_option( 'newspack_ls_screenshots_retry_works' ) ) {
		$result = [
			'imported'   => 0,
			'failed'     => 0,
			'up_to_date' => true,
		];
	}

	$feeds['feeds'][ $feed_id ]['last_run']    = time();
	$feeds['feeds'][ $feed_id ]['last_result'] = $result;
	update_option( 'newspack_lite_site_rss_importer', $feeds );
	return true;
}

add_action(
	'init',
	function () {
		if ( ! class_exists( 'Newspack_Lite_Site\\RSS_Importer' ) ) {
			return;
		}
		remove_action( Newspack_Lite_Site\RSS_Importer::CRON_HOOK, [ 'Newspack_Lite_Site\\RSS_Importer', 'run_scheduled_import' ] );
		add_action(
			Newspack_Lite_Site\RSS_Importer::CRON_HOOK,
			function ( $feed_id = '' ) {
				if ( ! newspack_ls_screenshots_fake_import( (string) $feed_id ) ) {
					Newspack_Lite_Site\RSS_Importer::run_scheduled_import( $feed_id );
				}
			}
		);
	}
);

add_filter(
	'rest_dispatch_request',
	function ( $result, $request, $route ) {
		if ( null !== $result || 'POST' !== strtoupper( $request->get_method() ) || 'import' !== $request->get_param( 'action' ) ) {
			return $result;
		}
		if ( 0 !== strcasecmp( $route, '/newspack-lite-site/v1/rss-feeds/(?P<id>[a-f0-9]{32})/action' ) || ! current_user_can( 'manage_options' ) ) {
			return $result;
		}
		$feed_id = (string) $request->get_param( 'id' );
		$feeds   = Newspack_Lite_Site\RSS_Importer::get_feeds();
		if ( 'active' !== ( $feeds[ $feed_id ]['status'] ?? '' ) ) {
			return $result;
		}
		if ( ! newspack_ls_screenshots_fake_import( $feed_id ) ) {
			return $result;
		}
		return Newspack_Lite_Site\RSS_Importer::rest_get_feeds();
	},
	10,
	3
);

add_filter(
	'style_loader_src',
	function ( $src ) {
		$fonts = newspack_ls_screenshots_fakes_content()['fonts'] ?? [];
		if ( isset( $fonts[ $src ] ) ) {
			return wp_make_link_relative( plugins_url( 'site/screenshots/assets/' . $fonts[ $src ], NEWSPACK_LITE_SITE_PLUGIN_FILE ) );
		}
		return $src;
	}
);

add_filter(
	'pre_http_request',
	function ( $response, $args, $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== $host ) {
			return new WP_Error( 'newspack_ls_screenshots_blocked', 'Blocked on the screenshot site.' );
		}
		return $response;
	},
	10,
	3
);

add_filter( 'pre_option_ping_sites', '__return_empty_string' );
add_filter( 'pre_wp_mail', '__return_false' );

add_filter(
	'pre_get_avatar_data',
	function ( $args, $id_or_email ) {
		static $avatars = null;

		if ( ! defined( 'NEWSPACK_LITE_SITE_PLUGIN_FILE' ) ) {
			return $args;
		}

		$user = false;
		if ( is_numeric( $id_or_email ) ) {
			$user = get_user_by( 'id', (int) $id_or_email );
		} elseif ( $id_or_email instanceof WP_User ) {
			$user = $id_or_email;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$user = get_user_by( 'id', (int) $id_or_email->post_author );
		} elseif ( $id_or_email instanceof WP_Comment ) {
			$user = $id_or_email->user_id ? get_user_by( 'id', (int) $id_or_email->user_id ) : false;
		} elseif ( is_string( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
		}

		if ( null === $avatars ) {
			$avatars = array_column( newspack_ls_screenshots_fakes_content()['users'] ?? [], 'avatar', 'login' );
		}

		$avatar               = $user && isset( $avatars[ $user->user_login ] ) ? $avatars[ $user->user_login ] : 'default.svg';
		$args['url']          = plugins_url( 'site/screenshots/assets/avatars/' . $avatar, NEWSPACK_LITE_SITE_PLUGIN_FILE );
		$args['found_avatar'] = true;
		return $args;
	},
	20,
	2
);
