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
	 * Turn Lite Site on for every test in this file.
	 */
	public function set_up() {
		parent::set_up();
		self::set_settings( [ 'enabled' => true ] );
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
	 * An address with no article behind it renders a full lite page rather
	 * than a bare string, and offers a way onward.
	 */
	public function test_missing_article_renders_a_lite_page() {
		set_query_var( 'lite_path', '2026/01/01/no-such-article' );

		$page = $this->render( 'single.php' );

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

		$this->render( 'single.php' );

		$property = new ReflectionProperty( Lite_Site::class, 'is_not_found' );
		$property->setAccessible( true );

		$this->assertTrue(
			$property->getValue(),
			'Rendering a not-found page marks the request so the cache skips it.'
		);
	}

	/**
	 * A page number past the end of the list answers as not found instead of
	 * rendering an empty page.
	 */
	public function test_page_past_the_end_is_not_found() {
		self::factory()->post->create_many( 3 );
		set_query_var( 'lite_page', 99 );

		$page = $this->render( 'archive.php' );

		$this->assertStringContainsString( 'Page not found', $page, 'A page past the end is not found.' );
		$this->assertStringNotContainsString( '<ul class="post-list">', $page, 'No empty list is rendered.' );
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

		$page = $this->render( 'archive.php' );

		$this->assertStringContainsString( 'Nothing to show here yet.', $page, 'The empty list explains itself.' );
		$this->assertStringContainsString( 'category or tag', $page, 'The message points at the filters.' );
		$this->assertStringNotContainsString( 'Page not found', $page, 'An empty first page is not a 404.' );
	}
}
