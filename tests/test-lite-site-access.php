<?php
/**
 * Tests for how the lite site honors content restrictions.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers the `newspack_is_post_restricted` and `newspack_post_has_restrictions`
 * contracts as the lite site consumes them: a post the reader cannot access on
 * the full site is left out of lite pages, and a post that any reader could be
 * restricted from is never written to the page cache.
 */
class Test_Lite_Site_Access extends Lite_Site_TestCase {

	/**
	 * IDs the test restricts through the filter.
	 *
	 * @var int[]
	 */
	private static $restricted_ids = [];

	/**
	 * IDs the test marks as carrying restrictions.
	 *
	 * @var int[]
	 */
	private static $gated_ids = [];

	/**
	 * Hook the two contracts so each test can pick which posts they answer for.
	 */
	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		self::$restricted_ids = [];
		self::$gated_ids      = [];
		add_filter( 'newspack_is_post_restricted', [ __CLASS__, 'restrict_listed_posts' ], 10, 2 );
		add_filter( 'newspack_post_has_restrictions', [ __CLASS__, 'gate_listed_posts' ], 10, 2 );
	}

	/**
	 * Unhook the contracts.
	 */
	public function tear_down() {
		remove_filter( 'newspack_is_post_restricted', [ __CLASS__, 'restrict_listed_posts' ], 10 );
		remove_filter( 'newspack_post_has_restrictions', [ __CLASS__, 'gate_listed_posts' ], 10 );
		parent::tear_down();
	}

	/**
	 * Answer `newspack_is_post_restricted` for the IDs a test listed.
	 *
	 * @param bool $restricted Incoming value.
	 * @param int  $post_id    Post ID.
	 * @return bool
	 */
	public static function restrict_listed_posts( $restricted, $post_id ) {
		return $restricted || in_array( (int) $post_id, self::$restricted_ids, true );
	}

	/**
	 * Answer `newspack_post_has_restrictions` for the IDs a test listed.
	 *
	 * @param bool $has_restrictions Incoming value.
	 * @param int  $post_id          Post ID.
	 * @return bool
	 */
	public static function gate_listed_posts( $has_restrictions, $post_id ) {
		return $has_restrictions || in_array( (int) $post_id, self::$gated_ids, true );
	}

	/**
	 * The path `resolve_post()` expects for a post: its permalink relative to home.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function lite_path( $post_id ) {
		return ltrim( str_replace( home_url(), '', get_permalink( $post_id ) ), '/' );
	}

	/**
	 * With nothing answering the filter, no post is restricted.
	 */
	public function test_post_is_not_restricted_by_default() {
		$post_id = self::factory()->post->create();

		$this->assertFalse( Lite_Site::is_post_restricted( $post_id ), 'An unanswered filter means no restriction.' );
	}

	/**
	 * The filter's verdict is what the lite site reports.
	 */
	public function test_post_is_restricted_when_the_filter_says_so() {
		$post_id              = self::factory()->post->create();
		self::$restricted_ids = [ $post_id ];

		$this->assertTrue( Lite_Site::is_post_restricted( $post_id ), 'A restricted post should be reported as such.' );
	}

	/**
	 * A restricted post's lite URL resolves to nothing, so the single view 404s.
	 */
	public function test_resolve_post_returns_nothing_for_a_restricted_post() {
		$post_id = self::factory()->post->create();
		$path    = $this->lite_path( $post_id );

		$this->assertInstanceOf( WP_Post::class, Lite_Site::resolve_post( $path ), 'An unrestricted post should resolve.' );

		self::$restricted_ids = [ $post_id ];
		$this->assertNull( Lite_Site::resolve_post( $path ), 'A restricted post should not resolve.' );
	}

	/**
	 * Restricted posts are dropped from an archive listing, keeping the rest in order.
	 */
	public function test_exclude_restricted_posts_keeps_only_accessible_posts() {
		$open       = self::factory()->post->create();
		$restricted = self::factory()->post->create();
		$also_open  = self::factory()->post->create();
		$posts      = array_map( 'get_post', [ $open, $restricted, $also_open ] );

		self::$restricted_ids = [ $restricted ];
		$remaining            = Lite_Site::exclude_restricted_posts( $posts );

		$this->assertSame( [ $open, $also_open ], wp_list_pluck( $remaining, 'ID' ), 'Only accessible posts should remain, in their original order.' );
		$this->assertSame( [ 0, 1 ], array_keys( $remaining ), 'The list should be re-indexed.' );
	}

	/**
	 * A post nobody can be restricted from is safe to cache for every reader.
	 */
	public function test_ungated_post_page_is_cacheable() {
		$post_id = self::factory()->post->create();

		$this->assertTrue( Lite_Site::is_post_page_cacheable( $post_id ), 'An ungated post can be served from the page cache.' );
	}

	/**
	 * A post that carries restrictions is never cached, even for a reader who
	 * can see it: the cache is keyed by URL alone, so one reader's view would
	 * become every reader's view.
	 */
	public function test_gated_post_page_is_not_cacheable() {
		$post_id         = self::factory()->post->create();
		self::$gated_ids = [ $post_id ];

		$this->assertFalse( Lite_Site::is_post_restricted( $post_id ), 'This reader can see the post.' );
		$this->assertFalse( Lite_Site::is_post_page_cacheable( $post_id ), 'A gated post must not be written to the page cache.' );
	}
}
