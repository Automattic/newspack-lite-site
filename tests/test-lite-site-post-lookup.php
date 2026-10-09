<?php
/**
 * Tests for the post lookup that resolve_post() caches.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers the cached lookup behind resolve_post(), which must not keep a post
 * off its lite URL once the post is published there.
 */
class Test_Lite_Site_Post_Lookup extends Lite_Site_TestCase {

	/**
	 * Pretty permalinks so url_to_postid() can resolve slugs, and a signed-out
	 * reader, for whom an unpublished post finds nothing.
	 */
	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		wp_set_current_user( 0 );
	}

	/**
	 * A scheduled post resolves as soon as it is published, though its lite
	 * URL was looked up while it was still scheduled.
	 *
	 * @dataProvider data_reader_requests
	 *
	 * @param string $slug       Post slug.
	 * @param bool   $uppercase  Whether the reader's request sends the slug's escapes in uppercase.
	 * @param bool   $over_https Whether the reader's request arrives over HTTPS.
	 */
	public function test_resolves_scheduled_post_once_published_after_an_early_lookup( $slug, $uppercase, $over_https ) {
		$post = self::factory()->post->create_and_get(
			[
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'post_name'   => $slug,
			]
		);
		$path = $uppercase ? strtoupper( $post->post_name ) : $post->post_name;

		$this->assertNull( $this->resolve_post_as_reader( $path, $over_https ), 'A scheduled post does not resolve.' );

		wp_publish_post( $post );

		$resolved = $this->resolve_post_as_reader( $path, $over_https );
		$this->assertInstanceOf( WP_Post::class, $resolved, 'The post resolves once published.' );
		$this->assertSame( $post->ID, $resolved->ID, 'The resolved post is the published one.' );
	}

	/**
	 * Slugs, and how a reader's request for them differs from the request
	 * that publishes the post.
	 *
	 * WordPress stores and links a non-ASCII slug with lowercase escapes,
	 * while a browser uses uppercase ones for a URL typed or pasted in Unicode.
	 * The test site's home URL is http, and a cron run can publish without the
	 * TLS a reader arrives over.
	 *
	 * @return array[]
	 */
	public function data_reader_requests() {
		return [
			'ASCII slug'                           => [ 'scheduled-story', false, false ],
			'non-ASCII slug, as a browser sends'   => [ 'привет', true, false ],
			'reader on HTTPS, publish without TLS' => [ 'scheduled-story', false, true ],
		];
	}

	/**
	 * A post resolves at a new slug as soon as it takes it, though that lite
	 * URL was looked up before then.
	 */
	public function test_resolves_post_at_new_slug_after_an_early_lookup() {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_name'   => 'first-slug',
			]
		);

		$this->assertNull( Lite_Site::resolve_post( 'second-slug' ), 'Nothing resolves at a slug no post has.' );

		wp_update_post(
			[
				'ID'        => $post_id,
				'post_name' => 'second-slug',
			]
		);

		$resolved = Lite_Site::resolve_post( 'second-slug' );
		$this->assertInstanceOf( WP_Post::class, $resolved, 'The post resolves at its new slug.' );
		$this->assertSame( $post_id, $resolved->ID, 'The resolved post is the renamed one.' );
	}

	/**
	 * A draft published from the block editor resolves at the lite URL the
	 * category set in that same save gives it, though that URL was looked up
	 * before the save.
	 */
	public function test_resolves_post_published_from_the_editor_at_its_category_path() {
		$this->set_category_permalinks();
		$category_id = self::factory()->category->create( [ 'slug' => 'news' ] );
		$post_id     = self::factory()->post->create(
			[
				'post_status' => 'draft',
				'post_name'   => 'draft-story',
			]
		);

		$this->assertNull( Lite_Site::resolve_post( 'news/draft-story' ), 'A draft does not resolve.' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(
			[
				'status'     => 'publish',
				'categories' => [ $category_id ],
			]
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$response = rest_do_request( $request );
		wp_set_current_user( 0 );
		$this->assertSame( 200, $response->get_status(), 'The editor publishes the post.' );

		$resolved = Lite_Site::resolve_post( 'news/draft-story' );
		$this->assertInstanceOf( WP_Post::class, $resolved, 'The post resolves at its category path once published.' );
		$this->assertSame( $post_id, $resolved->ID, 'The resolved post is the published one.' );
	}

	/**
	 * A missed lookup for an escaped variant of a slug, which finds nothing,
	 * doesn't keep the post off the slug's own lite URL.
	 */
	public function test_missed_lookup_for_an_escaped_variant_does_not_hide_the_post() {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_name'   => 'my-post',
			]
		);

		$this->assertNull( Lite_Site::resolve_post( 'my%2Dpost' ), 'The escaped variant finds nothing.' );

		$resolved = Lite_Site::resolve_post( 'my-post' );
		$this->assertInstanceOf( WP_Post::class, $resolved, 'The slug still resolves after the variant missed.' );
		$this->assertSame( $post_id, $resolved->ID, 'The resolved post is the one at the slug.' );
	}

	/**
	 * Resolve a lite path as a reader's request would, over HTTPS when asked.
	 *
	 * @param string $path       Lite path.
	 * @param bool   $over_https Whether the request arrives over HTTPS.
	 * @return WP_Post|null
	 */
	private function resolve_post_as_reader( $path, $over_https ) {
		if ( ! $over_https ) {
			return Lite_Site::resolve_post( $path );
		}

		$_SERVER['HTTPS'] = 'on';
		try {
			return Lite_Site::resolve_post( $path );
		} finally {
			unset( $_SERVER['HTTPS'] );
		}
	}
}
