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
	 * @dataProvider data_slugs_as_requested
	 *
	 * @param string $slug      Post slug.
	 * @param bool   $uppercase Whether the request sends the slug's escapes in uppercase.
	 */
	public function test_resolves_scheduled_post_once_published_after_an_early_lookup( $slug, $uppercase ) {
		$post = self::factory()->post->create_and_get(
			[
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'post_name'   => $slug,
			]
		);
		$path = $uppercase ? strtoupper( $post->post_name ) : $post->post_name;

		$this->assertNull( Lite_Site::resolve_post( $path ), 'A scheduled post does not resolve.' );

		wp_publish_post( $post );

		$resolved = Lite_Site::resolve_post( $path );
		$this->assertInstanceOf( WP_Post::class, $resolved, 'The post resolves once published.' );
		$this->assertSame( $post->ID, $resolved->ID, 'The resolved post is the published one.' );
	}

	/**
	 * Slugs, and whether a request sends their escapes in uppercase.
	 *
	 * WordPress stores and links a non-ASCII slug with lowercase escapes,
	 * while a browser uses uppercase ones for a URL typed or pasted in Unicode.
	 *
	 * @return array[]
	 */
	public function data_slugs_as_requested() {
		return [
			'ASCII slug'                         => [ 'scheduled-story', false ],
			'non-ASCII slug, as a browser sends' => [ 'привет', true ],
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
}
