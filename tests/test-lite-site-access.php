<?php
/**
 * Tests for lite site access control: which posts resolve for rendering.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers resolve_post() access decisions.
 */
class Test_Lite_Site_Access extends Lite_Site_TestCase {

	/**
	 * Pretty permalinks so url_to_postid() can resolve slugs.
	 */
	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * A published post resolves.
	 */
	public function test_resolves_published_post() {
		$post = self::factory()->post->create_and_get(
			[
				'post_status' => 'publish',
				'post_name'   => 'public-story',
			]
		);

		$resolved = Lite_Site::resolve_post( 'public-story' );

		$this->assertInstanceOf( WP_Post::class, $resolved, 'A published post resolves.' );
		$this->assertSame( $post->ID, $resolved->ID, 'The resolved post is the requested one.' );
	}

	/**
	 * A password-protected post does not resolve.
	 */
	public function test_does_not_resolve_password_protected_post() {
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_name'     => 'locked-story',
			]
		);

		$this->assertNull( Lite_Site::resolve_post( 'locked-story' ), 'A password-protected post does not resolve.' );
	}

	/**
	 * The page cache key ignores the query string, so cache-busting query
	 * strings cannot mint unbounded transients.
	 */
	public function test_page_cache_key_ignores_query_string() {
		$bare = Lite_Site::get_page_cache_key( '/lite/2024/07/01/a-story' );

		$this->assertSame( $bare, Lite_Site::get_page_cache_key( '/lite/2024/07/01/a-story?utm_source=x' ), 'A query string does not change the key.' );
		$this->assertSame( $bare, Lite_Site::get_page_cache_key( '/lite/2024/07/01/a-story/?utm_source=x' ), 'A trailing slash does not change the key.' );
		$this->assertNotSame( $bare, Lite_Site::get_page_cache_key( '/lite/2024/07/01/another-story' ), 'Different paths get different keys.' );
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
	 * The page cache key the lite single handler builds for a request.
	 *
	 * @param string $request_uri Request URI, as the server reports it.
	 * @return string The transient key.
	 */
	private function get_request_page_cache_key( $request_uri ) {
		$_SERVER['REQUEST_URI'] = $request_uri;
		$cache_key              = Lite_Site::get_request_page_cache_key();
		tests_reset__SERVER();
		return $cache_key;
	}
}
