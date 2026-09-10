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
}
