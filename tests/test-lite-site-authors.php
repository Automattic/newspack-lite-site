<?php
/**
 * Tests for lite site byline rendering.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers get_authors(), including the optional Newspack custom-byline
 * integration (exercised through the \Newspack\Bylines test double).
 */
class Test_Lite_Site_Authors extends Lite_Site_TestCase {

	/**
	 * Reset the Bylines stub so a configured byline never leaks between tests.
	 */
	public function tear_down() {
		\Newspack\Bylines::$custom_byline_html = null;
		parent::tear_down();
	}

	/**
	 * Without a custom byline, the post author is credited.
	 */
	public function test_get_authors_defaults_to_post_author() {
		$author_id = self::factory()->user->create( [ 'display_name' => 'Account Author' ] );
		$post      = self::factory()->post->create_and_get( [ 'post_author' => $author_id ] );

		$this->assertStringContainsString( 'Account Author', Lite_Site::get_authors( $post ), 'The post author is the default byline.' );
	}

	/**
	 * An active Newspack custom byline replaces the author-derived byline.
	 */
	public function test_get_authors_honors_newspack_custom_byline() {
		$author_id = self::factory()->user->create( [ 'display_name' => 'Account Author' ] );
		$post      = self::factory()->post->create_and_get( [ 'post_author' => $author_id ] );

		\Newspack\Bylines::$custom_byline_html = 'By Custom Person with reporting from Jane Doe';

		$authors = Lite_Site::get_authors( $post );

		$this->assertStringContainsString( 'Custom Person', $authors, 'An active custom byline is used.' );
		$this->assertStringNotContainsString( 'Account Author', $authors, 'The author-derived byline is replaced.' );
	}
}
