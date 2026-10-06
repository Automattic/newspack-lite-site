<?php
/**
 * Tests for the lite single-page cache: the key a request gets and the key
 * invalidation clears.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers the page cache keys for requests and for invalidation.
 */
class Test_Lite_Site_Page_Cache extends Lite_Site_TestCase {

	/**
	 * Pretty permalinks: WordPress routes a request, and sets the path the
	 * request key is built from, only when rewrite rules are in use.
	 */
	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * Lite URLs whose slugs are written entirely in non-Latin scripts get
	 * different page cache keys, so one post's cached page is never served
	 * for another.
	 */
	public function test_request_page_cache_key_keeps_non_ascii_slugs_apart() {
		$cyrillic = self::factory()->post->create_and_get(
			[
				'post_status' => 'publish',
				'post_name'   => 'привет',
			]
		);
		$arabic   = self::factory()->post->create_and_get(
			[
				'post_status' => 'publish',
				'post_name'   => 'مرحبا',
			]
		);

		$this->assertNotSame(
			$this->get_request_page_cache_key( '/lite/' . $cyrillic->post_name . '/' ),
			$this->get_request_page_cache_key( '/lite/' . $arabic->post_name . '/' ),
			'Posts with non-ASCII slugs get different keys.'
		);
	}

	/**
	 * A request path keeps its own key when it differs from another only by
	 * a character that sanitizing, URL parsing or decoding would lose, since
	 * WordPress can resolve such paths to different pages: rewrite matches
	 * keep their escapes, `sanitize_title()` cuts a slug at a stray `<`, and
	 * `url_to_postid()` reads query arguments before it drops a fragment.
	 *
	 * @dataProvider data_paths_differing_by_a_lossy_character
	 *
	 * @param string $path       A lite request path.
	 * @param string $other_path The same path, differing by one such character.
	 */
	public function test_request_page_cache_key_keeps_every_character_of_the_path( $path, $other_path ) {
		$this->assertNotSame(
			$this->get_request_page_cache_key( $path ),
			$this->get_request_page_cache_key( $other_path ),
			'The two paths get different keys.'
		);
	}

	/**
	 * Pairs of paths that differ only by a character a normalization would lose.
	 *
	 * @return array[]
	 */
	public function data_paths_differing_by_a_lossy_character() {
		return [
			'a character esc_url_raw() drops'          => [ '/lite/a-story-update/', '/lite/a-story<-update/' ],
			'a fragment marker wp_parse_url() cuts at' => [ '/lite/a-story/', '/lite/a-story/#update' ],
			'an escape that decoding would merge'      => [ '/lite/a-story/', '/lite/%61-story/' ],
		];
	}

	/**
	 * Invalidating a post with a non-ASCII slug clears the page its lite
	 * request cached, whichever case the request's percent-encoding used.
	 *
	 * Calls the save_post handler directly rather than saving the post: the
	 * revision a save creates clears the key for the bare `/lite` path, the
	 * key these requests collapse to when their octets are stripped, so the
	 * test would pass against that bug.
	 *
	 * @dataProvider data_percent_encoding_cases
	 *
	 * @param bool $uppercase Whether the request sends uppercase escapes.
	 */
	public function test_invalidation_clears_cached_page_for_non_ascii_slug( $uppercase ) {
		$post = self::factory()->post->create_and_get(
			[
				'post_status' => 'publish',
				'post_name'   => 'привет',
			]
		);

		// WordPress stores and links the slug with lowercase escapes, while a
		// browser uses uppercase ones for a URL typed or pasted in Unicode.
		$slug      = $uppercase ? strtoupper( $post->post_name ) : $post->post_name;
		$cache_key = $this->get_request_page_cache_key( '/lite/' . $slug . '/' );
		set_transient( $cache_key, 'Cached lite page', MINUTE_IN_SECONDS );

		Lite_Site::invalidate_page_cache( $post->ID );

		$this->assertFalse( get_transient( $cache_key ), 'Invalidating the post clears its cached lite page.' );
	}

	/**
	 * Letter cases a request's percent-encoding can arrive in.
	 *
	 * @return array[]
	 */
	public function data_percent_encoding_cases() {
		return [
			'lowercase escapes' => [ false ],
			'uppercase escapes' => [ true ],
		];
	}

	/**
	 * Invalidating a post clears the page its lite request cached when the
	 * site lives in a subdirectory, whether the request URI carries that
	 * directory or a proxy in front of WordPress has stripped it.
	 *
	 * @dataProvider data_subdirectory_request_uris
	 *
	 * @param string $request_uri The lite request URI, as the server reports it.
	 */
	public function test_invalidation_clears_cached_page_on_subdirectory_install( $request_uri ) {
		update_option( 'home', home_url( '/news' ) );
		$post = self::factory()->post->create_and_get(
			[
				'post_status' => 'publish',
				'post_name'   => 'a-story',
			]
		);

		$cache_key = $this->get_request_page_cache_key( $request_uri );
		set_transient( $cache_key, 'Cached lite page', MINUTE_IN_SECONDS );

		Lite_Site::invalidate_page_cache( $post->ID );

		$this->assertFalse( get_transient( $cache_key ), 'Invalidating the post clears its cached lite page.' );
	}

	/**
	 * Request URIs a subdirectory install's lite page can arrive with.
	 *
	 * @return array[]
	 */
	public function data_subdirectory_request_uris() {
		return [
			'request carries the directory' => [ '/news/lite/a-story/' ],
			'proxy strips the directory'    => [ '/lite/a-story/' ],
		];
	}

	/**
	 * The page cache key the lite single handler builds for a request, once
	 * WordPress has routed it.
	 *
	 * @param string $request_uri Request URI, as the server reports it.
	 * @return string The transient key.
	 */
	private function get_request_page_cache_key( $request_uri ) {
		$_SERVER['REQUEST_URI'] = $request_uri;
		$GLOBALS['wp']->parse_request();
		$cache_key = Lite_Site::get_request_page_cache_key();
		tests_reset__SERVER();
		return $cache_key;
	}
}
