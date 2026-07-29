<?php
/**
 * Tests for lite site URL handling and content cleaning.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers external link detection, the link attribute rewriter, the content
 * cleaner, and the settings getters that back them.
 */
class Test_Lite_Site_Content extends Lite_Site_TestCase {

	/**
	 * The current site's host, without a `www.` prefix.
	 *
	 * @return string
	 */
	private function site_host() {
		return preg_replace( '/^www\./i', '', wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/**
	 * URLs on another domain are external.
	 */
	public function test_detects_external_urls() {
		$this->assertTrue( Lite_Site::is_external_url( 'https://some-other-domain.test/article' ), 'A different domain is external.' );
		$this->assertTrue( Lite_Site::is_external_url( 'http://sub.' . $this->site_host() . '/article' ), 'A subdomain is external.' );
	}

	/**
	 * URLs on this site are internal.
	 */
	public function test_detects_internal_urls() {
		$this->assertFalse( Lite_Site::is_external_url( home_url( '/article' ) ), 'The site itself is internal.' );
		$this->assertFalse( Lite_Site::is_external_url( 'https://www.' . $this->site_host() . '/article' ), 'A www prefix should be normalized away.' );
	}

	/**
	 * Anything without a host is treated as internal.
	 */
	public function test_treats_hostless_urls_as_internal() {
		$this->assertFalse( Lite_Site::is_external_url( '/relative/path' ), 'A relative path is internal.' );
		$this->assertFalse( Lite_Site::is_external_url( '#anchor' ), 'An anchor is internal.' );
		$this->assertFalse( Lite_Site::is_external_url( '' ), 'An empty string is internal.' );
		$this->assertFalse( Lite_Site::is_external_url( 'mailto:someone@example.com' ), 'A mailto link is not external.' );
	}

	/**
	 * External links are marked and opened in a new tab by default.
	 */
	public function test_marks_external_links_for_new_tab() {
		$html = Lite_Site::add_external_link_attrs( '<a href="https://some-other-domain.test/a">Link</a>' );

		$this->assertStringContainsString( 'class="lite-site-external"', $html, 'External links should be classed.' );
		$this->assertStringContainsString( 'target="_blank"', $html, 'External links should open in a new tab.' );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $html, 'External links need a safe rel attribute.' );
	}

	/**
	 * With the new tab setting off, only the class is added.
	 */
	public function test_omits_target_when_new_tab_disabled() {
		self::set_settings( [ 'external_links_new_tab' => false ] );

		$html = Lite_Site::add_external_link_attrs( '<a href="https://some-other-domain.test/a">Link</a>' );

		$this->assertStringContainsString( 'class="lite-site-external"', $html, 'External links should still be classed.' );
		$this->assertStringNotContainsString( 'target=', $html, 'The target attribute should be omitted.' );
	}

	/**
	 * Internal links are left alone.
	 */
	public function test_leaves_internal_links_untouched() {
		$link = '<a href="' . home_url( '/article' ) . '">Link</a>';

		$this->assertSame( $link, Lite_Site::add_external_link_attrs( $link ), 'Internal links should not be rewritten.' );
	}

	/**
	 * Script tags and HTML comments are removed.
	 */
	public function test_clean_content_strips_scripts_and_comments() {
		$content = Lite_Site::clean_content( '<p>Keep this</p><script>alert(1)</script><!-- a comment -->' );

		$this->assertStringContainsString( 'Keep this', $content, 'Body copy should survive.' );
		$this->assertStringNotContainsString( '<script', $content, 'Script tags should be stripped.' );
		$this->assertStringNotContainsString( 'alert(1)', $content, 'Script contents should be stripped.' );
		$this->assertStringNotContainsString( 'a comment', $content, 'HTML comments should be stripped.' );
	}

	/**
	 * Text-level markup on the allowlist survives cleaning.
	 */
	public function test_clean_content_keeps_allowed_markup() {
		$content = Lite_Site::clean_content( '<h2>Heading</h2><p><strong>Bold</strong> and <em>italic</em></p><ul><li>Item</li></ul>' );

		$this->assertStringContainsString( '<h2>Heading</h2>', $content, 'Headings should survive.' );
		$this->assertStringContainsString( '<strong>Bold</strong>', $content, 'Bold text should survive.' );
		$this->assertStringContainsString( '<em>italic</em>', $content, 'Italic text should survive.' );
		$this->assertStringContainsString( '<li>Item</li>', $content, 'List items should survive.' );
	}

	/**
	 * Markup outside the allowlist is removed.
	 */
	public function test_clean_content_strips_disallowed_markup() {
		$content = Lite_Site::clean_content( '<p>Before</p><iframe src="https://some-other-domain.test/embed"></iframe><table><tr><td>Cell</td></tr></table>' );

		$this->assertStringNotContainsString( '<iframe', $content, 'Iframes should be stripped.' );
		$this->assertStringNotContainsString( '<table', $content, 'Tables should be stripped.' );
		$this->assertStringContainsString( 'Before', $content, 'Surrounding copy should survive.' );
	}

	/**
	 * Figures become lazy-load placeholders carrying the image metadata.
	 */
	public function test_clean_content_converts_figures_to_placeholders() {
		$content = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/photo.jpg" alt="A photo"><figcaption>The caption</figcaption></figure>'
		);

		$this->assertStringNotContainsString( '<figure', $content, 'The figure element should be replaced.' );
		$this->assertStringNotContainsString( '<img', $content, 'The image should not be loaded eagerly.' );
		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'A placeholder should be emitted.' );
		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/photo.jpg"', $content, 'The source should be preserved for lazy loading.' );
		$this->assertStringContainsString( 'data-alt="A photo"', $content, 'The alt text should be preserved.' );
		$this->assertStringContainsString( 'data-caption="The caption"', $content, 'The caption should be preserved.' );
		$this->assertStringContainsString( 'lite-image-load-btn', $content, 'A load button should be offered.' );
	}

	/**
	 * A figure with no image is left as-is for kses to deal with.
	 */
	public function test_clean_content_ignores_figure_without_image() {
		$content = Lite_Site::clean_content( '<figure><figcaption>Just a caption</figcaption></figure>' );

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'No placeholder without an image.' );
		$this->assertStringContainsString( 'Just a caption', $content, 'The caption text should survive.' );
	}

	/**
	 * External links inside cleaned content still get their attributes, even
	 * though the kses allowlist for `a` does not permit them. The rewriter
	 * runs after kses for exactly this reason.
	 */
	public function test_clean_content_marks_external_links_after_kses() {
		$content = Lite_Site::clean_content( '<p><a href="https://some-other-domain.test/a">Link</a></p>' );

		$this->assertStringContainsString( 'class="lite-site-external"', $content, 'External links should be marked in cleaned content.' );
	}

	/**
	 * The URL base falls back to `lite` when unset.
	 */
	public function test_url_base_defaults_to_lite() {
		$this->assertSame( 'lite', Lite_Site::get_url_base(), 'The default URL base should be lite.' );

		self::set_settings( [ 'url_base' => 'basic' ] );
		$this->assertSame( 'basic', Lite_Site::get_url_base(), 'A configured URL base should be used.' );
	}

	/**
	 * The lite site is disabled until explicitly enabled.
	 */
	public function test_is_disabled_by_default() {
		$this->assertFalse( Lite_Site::is_enabled(), 'The lite site should default to off.' );

		self::set_settings( [ 'enabled' => true ] );
		$this->assertTrue( Lite_Site::is_enabled(), 'The lite site should switch on when enabled.' );
	}

	/**
	 * Posts per page falls back to the WordPress Reading setting.
	 */
	public function test_posts_per_page_falls_back_to_reading_setting() {
		update_option( 'posts_per_page', 13 );

		$this->assertSame( 13, Lite_Site::get_posts_per_page(), 'The Reading setting should be the fallback.' );

		self::set_settings( [ 'posts_per_page' => 5 ] );
		$this->assertSame( 5, Lite_Site::get_posts_per_page(), 'A configured value should win.' );
	}

	/**
	 * Selected categories are expanded to include their descendants by default.
	 */
	public function test_categories_include_descendants_by_default() {
		$parent = self::factory()->category->create( [ 'name' => 'Parent' ] );
		$child  = self::factory()->category->create(
			[
				'name'   => 'Child',
				'parent' => $parent,
			]
		);

		self::set_settings( [ 'categories' => [ $parent ] ] );
		$this->assertContains( $child, Lite_Site::get_categories(), 'Child categories should be included.' );

		self::set_settings(
			[
				'categories'            => [ $parent ],
				'include_subcategories' => false,
			]
		);
		$this->assertSame( [ $parent ], Lite_Site::get_categories(), 'Child categories should be excluded when opted out.' );
	}

	/**
	 * No category selection means every category.
	 */
	public function test_no_category_selection_means_all() {
		$this->assertSame( [], Lite_Site::get_categories(), 'An empty selection should mean all categories.' );
	}

	/**
	 * Custom query vars are registered without dropping existing ones.
	 */
	public function test_registers_query_vars() {
		$vars = Lite_Site::register_query_vars( [ 'existing_var' ] );

		$this->assertContains( 'existing_var', $vars, 'Existing query vars should be preserved.' );
		$this->assertContains( 'is_lite', $vars, 'The lite site flag should be registered.' );
		$this->assertContains( 'lite_path', $vars, 'The lite path should be registered.' );
		$this->assertContains( 'lite_page', $vars, 'The lite page number should be registered.' );
	}
}
