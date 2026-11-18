<?php
/**
 * Tests for the lite site's not-found and empty pages.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers the page a reader gets when a lite address has nothing behind it:
 * a missing article, a page number past the end of the list, and a filter
 * that matches no published post.
 */
class Test_Lite_Site_Not_Found extends Lite_Site_TestCase {

	/**
	 * The request globals as they were before this file changed them.
	 *
	 * @var array
	 */
	private $globals_backup = [];

	/**
	 * Turn Lite Site on for every test in this file.
	 */
	public function set_up() {
		parent::set_up();
		self::set_settings( [ 'enabled' => true ] );

		// test_missing_article_is_not_cached asserts a transient is absent, which
		// is only meaningful while this request could have filled the cache.
		// $_SERVER and $_COOKIE survive between tests, so they are snapshotted
		// here and put back in tear_down().
		$this->globals_backup      = [ $_SERVER, $_COOKIE ]; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- Test setup: the cache check this file relies on reads cookie keys.
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/';
		$_SERVER['HTTP_HOST']      = wp_parse_url( home_url(), PHP_URL_HOST );
		$_COOKIE                   = []; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- Test setup: the cache check this file relies on reads cookie keys.
	}

	/**
	 * Put the request globals back.
	 */
	public function tear_down() {
		if ( [] !== $this->globals_backup ) {
			list( $_SERVER, $_COOKIE ) = $this->globals_backup; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- Test teardown: restores what set_up() snapshotted.
		}
		parent::tear_down();
	}

	/**
	 * Render a lite template and return its markup.
	 *
	 * @param string $template Template file name, such as 'single.php'.
	 * @return string The rendered page.
	 */
	private function render( $template ) {
		ob_start();
		require dirname( __DIR__ ) . '/templates/' . $template;
		return ob_get_clean();
	}

	/**
	 * Render a lite template and return its markup and its HTTP status.
	 *
	 * Both symptoms this file covers were status bugs — a bare body on a 404,
	 * and an empty page served as 200 — so the status is asserted, not assumed.
	 *
	 * @param string $template Template file name.
	 * @return array{page:string,status:int} The rendered page and the status sent, 0 when none was.
	 */
	private function render_with_status( $template ) {
		$status  = 0;
		$capture = function ( $header, $code ) use ( &$status ) {
			$status = $code;
			return $header;
		};

		add_filter( 'status_header', $capture, 10, 2 );
		try {
			$page = $this->render( $template );
		} finally {
			remove_filter( 'status_header', $capture, 10 );
		}

		return [
			'page'   => $page,
			'status' => $status,
		];
	}

	/**
	 * An address with no article behind it renders a full lite page rather
	 * than a bare string, and offers a way onward.
	 */
	public function test_missing_article_renders_a_lite_page() {
		set_query_var( 'lite_path', '2026/01/01/no-such-article' );

		$rendered = $this->render_with_status( 'single.php' );
		$page     = $rendered['page'];

		$this->assertSame( 404, $rendered['status'], 'A missing article answers 404.' );
		$this->assertStringContainsString( '<!DOCTYPE html>', $page, 'The not-found page is a complete document.' );
		$this->assertStringContainsString( 'Page not found', $page, 'The page says what happened.' );
		$this->assertStringContainsString( 'noindex', $page, 'The page is not offered to search engines.' );
		$this->assertStringContainsString( 'View full site', $page, 'The reader can reach the full site.' );
		$this->assertStringContainsString(
			esc_url( home_url( Lite_Site::get_url_base() . '/' ) ),
			$page,
			'The reader can reach the lite site homepage.'
		);
	}

	/**
	 * A not-found page is never stored in the page cache, so a post published
	 * a moment later is not shadowed by it.
	 */
	public function test_missing_article_is_not_cached() {
		set_query_var( 'lite_path', '2026/01/01/no-such-article' );
		$cache_key = 'nls_page_test_not_found';

		$page = Lite_Site::cache_single_page( $cache_key );

		$this->assertStringContainsString( 'Page not found', $page, 'The render is the not-found page.' );
		$this->assertFalse( get_transient( $cache_key ), 'A not-found page is never stored for later readers.' );
	}

	/**
	 * A not-found render does not stop later pages in the same process from
	 * being cached, which a flag that is never reset would do.
	 */
	public function test_a_later_page_still_caches_after_a_not_found() {
		set_query_var( 'lite_path', '2026/01/01/no-such-article' );
		Lite_Site::cache_single_page( 'nls_page_test_not_found_first' );

		$post = self::factory()->post->create( [ 'post_title' => 'A real article' ] );
		set_query_var( 'lite_path', ltrim( wp_make_link_relative( get_permalink( $post ) ), '/' ) );
		$cache_key = 'nls_page_test_real';

		$page = Lite_Site::cache_single_page( $cache_key );

		$this->assertStringContainsString( 'A real article', $page, 'The real article renders.' );
		$this->assertNotFalse( get_transient( $cache_key ), 'A real page is still stored after a not-found render.' );
	}

	/**
	 * A not-found render nested inside a successful one does not stop the outer
	 * page being cached.
	 */
	public function test_a_nested_not_found_does_not_block_the_outer_page() {
		$post = self::factory()->post->create( [ 'post_title' => 'The outer article' ] );
		$path = ltrim( wp_make_link_relative( get_permalink( $post ) ), '/' );

		$nested = function () {
			$outer = get_query_var( 'lite_path' );
			set_query_var( 'lite_path', '2026/01/01/no-such-article' );
			Lite_Site::cache_single_page( 'nls_page_test_nested_inner' );
			set_query_var( 'lite_path', $outer );
		};

		add_action( 'newspack_lite_site_single_after_footer', $nested );
		set_query_var( 'lite_path', $path );
		$cache_key = 'nls_page_test_nested_outer';

		try {
			$page = Lite_Site::cache_single_page( $cache_key );
		} finally {
			remove_action( 'newspack_lite_site_single_after_footer', $nested );
		}

		$this->assertStringContainsString( 'The outer article', $page, 'The outer page renders.' );
		$this->assertNotFalse( get_transient( $cache_key ), 'The outer page is still cached.' );
		$this->assertFalse( get_transient( 'nls_page_test_nested_inner' ), 'The nested not-found page is not cached.' );
	}

	/**
	 * A page number past the end of the list answers as not found instead of
	 * rendering an empty page.
	 */
	public function test_page_past_the_end_is_not_found() {
		self::factory()->post->create_many( 3 );
		set_query_var( 'lite_page', 99 );

		$rendered = $this->render_with_status( 'archive.php' );

		$this->assertSame( 404, $rendered['status'], 'A page past the end answers 404, not 200.' );
		$this->assertStringContainsString( 'Page not found', $rendered['page'], 'A page past the end is not found.' );
		$this->assertStringNotContainsString( '<ul class="post-list">', $rendered['page'], 'No empty list is rendered.' );
	}

	/**
	 * When filters match no published post, the list says so rather than
	 * rendering a page with nothing on it.
	 */
	public function test_empty_list_explains_itself() {
		self::set_settings(
			[
				'enabled' => true,
				'tags'    => [ 999999 ],
			]
		);
		self::factory()->post->create_many( 2 );

		$rendered = $this->render_with_status( 'archive.php' );

		$this->assertSame( 0, $rendered['status'], 'An empty first page sends no error status.' );
		$this->assertStringContainsString( 'Nothing to show here yet.', $rendered['page'], 'The empty list explains itself.' );
		$this->assertStringContainsString( 'filtered to', $rendered['page'], 'The message points at the filters.' );
	}

	/**
	 * With no filter configured, the empty list does not blame a filter the
	 * publisher has not set.
	 */
	public function test_empty_list_without_filters_blames_nothing() {
		$rendered = $this->render_with_status( 'archive.php' );

		$this->assertStringContainsString( 'Nothing to show here yet.', $rendered['page'], 'The empty list still explains itself.' );
		$this->assertStringNotContainsString( 'filter', $rendered['page'], 'No filter of any wording is mentioned when none is set.' );
	}

	/**
	 * The positive control: an archive with posts still renders the list.
	 */
	public function test_archive_with_posts_renders_the_list() {
		self::factory()->post->create( [ 'post_title' => 'A listed article' ] );

		$rendered = $this->render_with_status( 'archive.php' );

		$this->assertSame( 0, $rendered['status'], 'A populated archive sends no error status.' );
		$this->assertStringContainsString( '<ul class="post-list">', $rendered['page'], 'The list is rendered.' );
		$this->assertStringContainsString( 'A listed article', $rendered['page'], 'The post appears in the list.' );
		$this->assertStringNotContainsString( 'Nothing to show here yet.', $rendered['page'], 'No empty state when there are posts.' );
	}
}
