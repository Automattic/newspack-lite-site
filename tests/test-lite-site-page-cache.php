<?php
/**
 * Tests for the lite single page cache.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers what cache_single_page() stores in the page cache.
 */
class Test_Lite_Site_Page_Cache extends Lite_Site_TestCase {

	/**
	 * Page cache key for the story's lite URL.
	 *
	 * @var string
	 */
	private $cache_key;

	/**
	 * A published story with blocks that render differently for signed-in
	 * readers, requested at its lite URL.
	 */
	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );

		self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_name'    => 'a-story',
				'post_content' => '<!-- wp:paragraph --><p>Story copy.</p><!-- /wp:paragraph -->'
					. '<!-- wp:group {"className":"members-only"} --><div class="wp-block-group members-only"><!-- wp:paragraph --><p>Members-only copy.</p><!-- /wp:paragraph --></div><!-- /wp:group -->'
					. '<!-- wp:group {"className":"signed-out-only"} --><div class="wp-block-group signed-out-only"><!-- wp:paragraph --><p>Subscribe prompt.</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
			]
		);

		set_query_var( 'lite_path', 'a-story' );
		$this->cache_key = Lite_Site::get_page_cache_key( '/lite/a-story' );

		// The single template exits when its post doesn't resolve, which would
		// end the whole run with a passing status, so fail here instead.
		$this->assertNotNull( Lite_Site::resolve_post( 'a-story' ), 'The story resolves at its lite path.' );

		add_filter( 'render_block', [ __CLASS__, 'render_block_per_reader' ], 10, 2 );
	}

	/**
	 * Stands in for Newspack's block visibility rules, which decide each
	 * block for the current user: one group shows only to signed-in readers,
	 * the other only to signed-out ones.
	 *
	 * @param string $block_content Rendered block.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public static function render_block_per_reader( $block_content, $block ) {
		switch ( $block['attrs']['className'] ?? '' ) {
			case 'members-only':
				return is_user_logged_in() ? $block_content : '';
			case 'signed-out-only':
				return is_user_logged_in() ? '' : $block_content;
			default:
				return $block_content;
		}
	}

	/**
	 * A signed-in reader fills the cache with the page a signed-out visitor
	 * gets, so every later reader is served the same copy whoever came first.
	 */
	public function test_cached_page_is_the_same_whoever_fills_it() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		Lite_Site::cache_single_page( $this->cache_key );
		$filled_by_member = get_transient( $this->cache_key );

		wp_set_current_user( 0 );
		Lite_Site::cache_single_page( $this->cache_key );
		$filled_by_visitor = get_transient( $this->cache_key );

		$this->assertStringContainsString( 'Subscribe prompt.', $filled_by_visitor, 'A signed-out visitor gets the blocks shown to signed-out readers.' );
		$this->assertStringNotContainsString( 'Members-only copy.', $filled_by_visitor, 'A signed-out visitor does not get the blocks shown only to members.' );
		$this->assertSame( $filled_by_visitor, $filled_by_member, 'A member fills the cache with the page a signed-out visitor gets.' );
	}

	/**
	 * The rest of the request runs for the reader who made it, not as the
	 * signed-out reader the page was rendered for.
	 */
	public function test_restores_the_signed_in_reader_after_rendering() {
		$member_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $member_id );

		Lite_Site::cache_single_page( $this->cache_key );

		$this->assertSame( $member_id, get_current_user_id(), 'The member is the current user again.' );
	}

	/**
	 * A caller that recovers from a failed render carries on as the reader
	 * who made the request.
	 */
	public function test_restores_the_signed_in_reader_when_rendering_fails() {
		$member_id    = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$render_error = new RuntimeException( 'The render failed.' );
		add_action(
			'newspack_lite_site_single_after_footer',
			function () use ( $render_error ) {
				throw $render_error;
			}
		);
		wp_set_current_user( $member_id );

		$caught_error = null;
		try {
			Lite_Site::cache_single_page( $this->cache_key );
		} catch ( RuntimeException $error ) {
			$caught_error = $error;
		}

		$this->assertSame( $render_error, $caught_error, 'The render error reaches the caller.' );
		$this->assertSame( $member_id, get_current_user_id(), 'The member is the current user again.' );
	}
}
