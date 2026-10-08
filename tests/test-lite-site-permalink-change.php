<?php
/**
 * Tests for what a post's old lite URL serves once the post leaves it.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers the caches kept for the lite path a post had before a save moved it
 * or unpublished it, which would otherwise go on serving the post there.
 */
class Test_Lite_Site_Permalink_Change extends Lite_Site_TestCase {

	/**
	 * Pretty permalinks, so url_to_postid() can resolve paths.
	 */
	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * A post's old lite URL stops resolving to it, and stops serving its
	 * cached page, once a save changes the post's permalink.
	 *
	 * @dataProvider data_permalink_changes
	 *
	 * @param string $structure Permalink structure.
	 * @param array  $post_args Arguments the post is created with.
	 * @param array  $changes   Fields the save changes.
	 * @param string $old_path  The post's lite path before the save.
	 */
	public function test_old_path_stops_serving_post_after_permalink_change( $structure, $post_args, $changes, $old_path ) {
		$this->set_permalink_structure( $structure );
		$post_id   = self::factory()->post->create( array_merge( [ 'post_status' => 'publish' ], $post_args ) );
		$cache_key = $this->visit_lite_path( $old_path );

		wp_update_post( array_merge( [ 'ID' => $post_id ], $changes ) );

		$this->assertFalse( get_transient( $cache_key ), 'The page cached at the old path is cleared.' );
		$this->assertNull( Lite_Site::resolve_post( $old_path ), 'The old path no longer resolves.' );
	}

	/**
	 * Permalink changes a save can make, with the structure that exposes each.
	 *
	 * @return array[]
	 */
	public function data_permalink_changes() {
		return [
			'new slug' => [
				'/%postname%/',
				[ 'post_name' => 'first-slug' ],
				[ 'post_name' => 'second-slug' ],
				'first-slug',
			],
			'new date' => [
				'/%year%/%monthnum%/%postname%/',
				[
					'post_name' => 'a-story',
					'post_date' => '2020-01-15 10:00:00',
				],
				[
					'post_date'     => '2019-12-15 10:00:00',
					'post_date_gmt' => '2019-12-15 10:00:00',
				],
				'2020/01/a-story',
			],
		];
	}

	/**
	 * A page's old lite URL stops resolving to it, and stops serving its
	 * cached page, once it moves under a new parent.
	 */
	public function test_old_path_stops_serving_page_after_parent_change() {
		$page_args = [
			'post_type'   => 'page',
			'post_status' => 'publish',
		];
		$old_parent_id = self::factory()->post->create( array_merge( $page_args, [ 'post_name' => 'about' ] ) );
		$new_parent_id = self::factory()->post->create( array_merge( $page_args, [ 'post_name' => 'company' ] ) );
		$page_id       = self::factory()->post->create(
			array_merge(
				$page_args,
				[
					'post_name'   => 'team',
					'post_parent' => $old_parent_id,
				]
			)
		);
		$cache_key     = $this->visit_lite_path( 'about/team' );

		wp_update_post(
			[
				'ID'          => $page_id,
				'post_parent' => $new_parent_id,
			]
		);

		$this->assertFalse( get_transient( $cache_key ), 'The page cached at the old path is cleared.' );
		$this->assertNull( Lite_Site::resolve_post( 'about/team' ), 'The old path no longer resolves.' );
	}

	/**
	 * A post moved to another category stops serving its cached page at the
	 * lite URL of its old category, whether the block editor or a classic
	 * save changes the category.
	 *
	 * Only the page is checked: WordPress still finds the post at a path
	 * naming another category, so the lookup there stays correct. Both saves
	 * change the category before wp_after_insert_post runs, so the permalink
	 * of the post as it was before the save already names the new category.
	 *
	 * @dataProvider data_category_saves
	 *
	 * @param bool $from_editor Whether the block editor saves the change.
	 */
	public function test_category_change_clears_page_cached_at_old_category_path( $from_editor ) {
		$this->set_category_permalinks();
		$old_category_id = self::factory()->category->create( [ 'slug' => 'news' ] );
		$new_category_id = self::factory()->category->create( [ 'slug' => 'sports' ] );
		$post_id         = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_name'     => 'a-story',
				'post_category' => [ $old_category_id ],
			]
		);
		$cache_key       = $this->visit_lite_path( 'news/a-story' );

		if ( $from_editor ) {
			$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
			$request->set_body_params( [ 'categories' => [ $new_category_id ] ] );
			wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
			$response = rest_do_request( $request );
			wp_set_current_user( 0 );
			$this->assertSame( 200, $response->get_status(), 'The editor saves the post.' );
		} else {
			wp_update_post(
				[
					'ID'            => $post_id,
					'post_category' => [ $new_category_id ],
				]
			);
		}

		$this->assertFalse( get_transient( $cache_key ), 'The page cached at the old category path is cleared.' );
	}

	/**
	 * Ways a category change is saved.
	 *
	 * @return array[]
	 */
	public function data_category_saves() {
		return [
			'block editor' => [ true ],
			'classic save' => [ false ],
		];
	}

	/**
	 * An unpublished post stops serving its cached page at the lite URL it
	 * was published at, which the cache would otherwise serve without
	 * looking the post up.
	 *
	 * @dataProvider data_unpublishing
	 *
	 * @param string $status The status the post moves to.
	 */
	public function test_unpublishing_clears_page_cached_at_published_path( $status ) {
		$post_id   = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_name'   => 'a-story',
			]
		);
		$cache_key = $this->visit_lite_path( 'a-story' );

		if ( 'trash' === $status ) {
			wp_trash_post( $post_id );
		} else {
			wp_update_post(
				[
					'ID'          => $post_id,
					'post_status' => $status,
				]
			);
		}

		$this->assertFalse( get_transient( $cache_key ), 'The page cached at the published path is cleared.' );
	}

	/**
	 * Statuses that take a post off its lite URL and change its permalink.
	 *
	 * @return array[]
	 */
	public function data_unpublishing() {
		return [
			'moved to drafts' => [ 'draft' ],
			'trashed'         => [ 'trash' ],
		];
	}

	/**
	 * Look up a lite path and cache a page for it, as a reader's visit would.
	 *
	 * @param string $path Lite path.
	 * @return string The key of the cached page.
	 */
	private function visit_lite_path( $path ) {
		$this->assertInstanceOf( WP_Post::class, Lite_Site::resolve_post( $path ), 'The post resolves at the path before the save.' );

		$cache_key = $this->get_request_page_cache_key( '/lite/' . $path . '/' );
		set_transient( $cache_key, 'Cached lite page', MINUTE_IN_SECONDS );
		return $cache_key;
	}
}
