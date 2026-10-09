<?php
/**
 * Tests for what a post's old lite URL serves once the post leaves it.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers the caches kept for the lite path a post had before a save moved it
 * or unpublished it, or a delete removed it, and for the paths of the pages
 * below a page that moves, which would otherwise go on serving there.
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
	 * The pages below a page stop resolving, and stop serving their cached
	 * pages, at the lite URLs they had once a save moves that page.
	 *
	 * @dataProvider data_ancestor_moves
	 *
	 * @param array $changes Fields the save changes, with a new parent given by its slug.
	 */
	public function test_old_descendant_paths_stop_serving_after_ancestor_moves( $changes ) {
		$pages      = $this->create_page_tree();
		$cache_keys = [
			'about/team'      => $this->visit_lite_path( 'about/team' ),
			'about/team/lead' => $this->visit_lite_path( 'about/team/lead' ),
		];

		if ( isset( $changes['post_parent'] ) ) {
			$changes['post_parent'] = $pages[ $changes['post_parent'] ];
		}
		wp_update_post( array_merge( [ 'ID' => $pages['about'] ], $changes ) );

		foreach ( $cache_keys as $path => $cache_key ) {
			$this->assertFalse( get_transient( $cache_key ), "The page cached at $path is cleared." );
			$this->assertNull( Lite_Site::resolve_post( $path ), "$path no longer resolves." );
		}
	}

	/**
	 * Saves that move a page, and every page below it.
	 *
	 * @return array[]
	 */
	public function data_ancestor_moves() {
		return [
			'renamed'                  => [ [ 'post_name' => 'who-we-are' ] ],
			'moved under another page' => [ [ 'post_parent' => 'company' ] ],
			'trashed'                  => [ [ 'post_status' => 'trash' ] ],
		];
	}

	/**
	 * A published page below an unpublished one stops serving its cached
	 * page at the lite URL it had once a save moves a page above both.
	 */
	public function test_old_path_below_unpublished_page_stops_serving_after_ancestor_moves() {
		$pages = $this->create_page_tree();
		wp_update_post(
			[
				'ID'          => $pages['team'],
				'post_status' => 'draft',
			]
		);
		$cache_key = $this->visit_lite_path( 'about/team/lead' );

		wp_update_post(
			[
				'ID'        => $pages['about'],
				'post_name' => 'who-we-are',
			]
		);

		$this->assertFalse( get_transient( $cache_key ) );
	}

	/**
	 * The pages below a renamed page resolve at their new lite URLs right
	 * away, even where a visit before the rename found nothing.
	 */
	public function test_descendants_resolve_at_new_paths_after_ancestor_rename() {
		$pages = $this->create_page_tree();
		$this->assertNull( Lite_Site::resolve_post( 'who-we-are/team' ), 'Nothing resolves at the new path before the rename.' );

		wp_update_post(
			[
				'ID'        => $pages['about'],
				'post_name' => 'who-we-are',
			]
		);

		$this->assertSame( $pages['team'], Lite_Site::resolve_post( 'who-we-are/team' )->ID ?? null );
	}

	/**
	 * Saving a page without moving it leaves the pages below it cached, since
	 * their lite URLs and content are unchanged.
	 */
	public function test_saving_page_in_place_keeps_descendant_pages_cached() {
		$pages     = $this->create_page_tree();
		$cache_key = $this->visit_lite_path( 'about/team' );

		wp_update_post(
			[
				'ID'           => $pages['about'],
				'post_content' => 'New content',
			]
		);

		$this->assertSame( 'Cached lite page', get_transient( $cache_key ) );
	}

	/**
	 * Moving a page with more pages below it than the clearing limit clears
	 * the nearest ones and leaves the rest to expire, so one save can't take
	 * on an unbounded amount of work.
	 */
	public function test_ancestor_move_clears_a_bounded_number_of_descendants() {
		$limit    = Lite_Site::MAX_DESCENDANTS_CLEARED;
		$about_id = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'about',
			]
		);
		$children = self::factory()->post->create_many(
			$limit + 1,
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_parent' => $about_id,
			]
		);
		$nearest  = $this->visit_lite_path( 'about/' . get_post( $children[0] )->post_name );
		$past     = $this->visit_lite_path( 'about/' . get_post( $children[ $limit ] )->post_name );

		wp_update_post(
			[
				'ID'        => $about_id,
				'post_name' => 'who-we-are',
			]
		);

		$this->assertFalse( get_transient( $nearest ), 'A page within the limit is cleared.' );
		$this->assertSame( 'Cached lite page', get_transient( $past ), 'A page past the limit is left to expire.' );
	}

	/**
	 * A post moved to another category stops serving its cached page at the
	 * lite URL of its old category.
	 *
	 * Only the page is checked: WordPress still finds the post at a path
	 * naming another category, so the lookup there stays correct. A save
	 * changes the category before wp_after_insert_post runs, so the permalink
	 * of the post as it was before the save already names the new category.
	 */
	public function test_category_change_clears_page_cached_at_old_category_path() {
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

		wp_update_post(
			[
				'ID'            => $post_id,
				'post_category' => [ $new_category_id ],
			]
		);

		$this->assertFalse( get_transient( $cache_key ), 'The page cached at the old category path is cleared.' );
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
	 * A permanently deleted post stops serving its cached page at the lite
	 * URL it was published at, which the cache would otherwise serve without
	 * looking the post up.
	 *
	 * @dataProvider data_deleted_post_paths
	 *
	 * @param bool   $category_permalinks Whether permalinks include the category.
	 * @param string $path                The post's lite path.
	 */
	public function test_deleting_post_clears_page_cached_at_its_path( $category_permalinks, $path ) {
		if ( $category_permalinks ) {
			$this->set_category_permalinks();
		}
		$post_id   = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_name'     => 'a-story',
				'post_category' => [ self::factory()->category->create( [ 'slug' => 'news' ] ) ],
			]
		);
		$cache_key = $this->visit_lite_path( $path );

		wp_delete_post( $post_id, true );

		$this->assertFalse( get_transient( $cache_key ), 'The page cached at the published path is cleared.' );
	}

	/**
	 * Lite paths of a deleted post, including one built from the categories
	 * that deleting removes from the post.
	 *
	 * @return array[]
	 */
	public function data_deleted_post_paths() {
		return [
			'post'                     => [ false, 'a-story' ],
			'post filed in a category' => [ true, 'news/a-story' ],
		];
	}

	/**
	 * The pages below a permanently deleted page, which move up a level,
	 * stop resolving and serving their cached pages at the lite URLs they
	 * had under it, and resolve right away at the ones they move to.
	 */
	public function test_deleting_page_moves_descendants_to_new_paths() {
		$pages      = $this->create_page_tree();
		$cache_keys = [
			'about/team'      => $this->visit_lite_path( 'about/team' ),
			'about/team/lead' => $this->visit_lite_path( 'about/team/lead' ),
		];
		$this->assertNull( Lite_Site::resolve_post( 'team' ), 'Nothing resolves at the new path before the delete.' );

		wp_delete_post( $pages['about'], true );

		foreach ( $cache_keys as $path => $cache_key ) {
			$this->assertFalse( get_transient( $cache_key ), "The page cached at $path is cleared." );
			$this->assertNull( Lite_Site::resolve_post( $path ), "$path no longer resolves." );
		}
		$this->assertSame( $pages['team'], Lite_Site::resolve_post( 'team' )->ID ?? null, 'The page resolves at the path it moved up to.' );
	}

	/**
	 * Create published pages `about/team/lead`, and `company` beside them.
	 *
	 * @return int[] Page IDs, keyed by slug.
	 */
	private function create_page_tree() {
		$page_ids = [];
		foreach ( [
			'company' => '',
			'about'   => '',
			'team'    => 'about',
			'lead'    => 'team',
		] as $slug => $parent ) {
			$page_ids[ $slug ] = self::factory()->post->create(
				[
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_name'   => $slug,
					'post_parent' => $parent ? $page_ids[ $parent ] : 0,
				]
			);
		}
		return $page_ids;
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
