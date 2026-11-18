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
		$this->assertFalse( Lite_Site::is_external_url( 'mailto:someone@yourgroovydomain.com' ), 'A mailto link is not external.' );
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
	 * Style tags are removed along with their CSS.
	 */
	public function test_clean_content_strips_style_tags_and_their_css() {
		$content = Lite_Site::clean_content( '<p>Before</p><style>.my-class { color: red; }</style><p>After</p>' );

		$this->assertStringContainsString( '<p>Before</p>', $content, 'Body copy should survive.' );
		$this->assertStringContainsString( '<p>After</p>', $content, 'Body copy should survive.' );
		$this->assertStringNotContainsString( '.my-class', $content, 'CSS should not appear as text.' );
		$this->assertStringNotContainsString( 'color: red', $content, 'CSS should not appear as text.' );
	}

	/**
	 * An unclosed HTML comment does not blank the whole article.
	 */
	public function test_clean_content_survives_unclosed_comment() {
		$content = Lite_Site::clean_content(
			'<p>First paragraph.</p><!-- unclosed' . str_repeat( '<p>Filler to force backtracking.</p>', 50 )
		);

		$this->assertStringContainsString( 'First paragraph.', $content, 'Content before an unclosed comment should survive.' );
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
		$content = Lite_Site::clean_content( '<p>Before</p><iframe src="https://some-other-domain.test/embed"></iframe><video src="movie.mp4"></video>' );

		$this->assertStringNotContainsString( '<iframe', $content, 'Iframes should be stripped.' );
		$this->assertStringNotContainsString( '<video', $content, 'Video elements should be stripped.' );
		$this->assertStringContainsString( 'Before', $content, 'Surrounding copy should survive.' );
	}

	/**
	 * Tables keep their structure. A results table, a list of closures or a
	 * set of evacuation zones is the reason a reader is on the lite site at
	 * all, and without the table elements every cell runs into the next.
	 */
	public function test_clean_content_keeps_tables() {
		$content = Lite_Site::clean_content( $this->table_block() );

		$this->assertStringContainsString( '<table>', $content, 'The table survives.' );
		$this->assertStringContainsString( '<th scope="col">Zone</th>', $content, 'Header cells keep their scope.' );
		$this->assertStringContainsString( '<td>North</td>', $content, 'Body cells survive.' );
		$this->assertStringContainsString( '<td>North</td><td>Evacuate</td>', preg_replace( '/\s+/', '', $content ), 'Each cell stays in its own element.' );
	}

	/**
	 * A table gets its own horizontal scroller, so a wide table can overflow
	 * without the whole article column becoming a scroll container, and stays
	 * a table so assistive technology still reads it as one.
	 */
	public function test_clean_content_wraps_a_table_in_a_scroller() {
		$content = Lite_Site::clean_content( $this->table_block() );

		$this->assertStringContainsString( '<div class="lite-table-scroll" tabindex="0" role="group"><table>', $content, 'The table is wrapped in its own scroller.' );
		$this->assertStringContainsString( '</table></div>', $content, 'The wrapper closes around the table.' );
	}

	/**
	 * A table's caption survives. The block editor saves it as a figcaption
	 * beside the table, not as a caption inside it.
	 */
	public function test_clean_content_keeps_a_block_table_caption() {
		$content = Lite_Site::clean_content( $this->table_block() );

		$this->assertStringContainsString( '<figcaption', $content, 'The block editor caption survives as an element.' );
		$this->assertStringContainsString( 'Evacuation zones', $content, 'The caption text survives.' );
		$this->assertStringNotContainsString( '</table>Evacuation', preg_replace( '/\s+/', '', $content ), 'The caption is not left as bare text after the table.' );
	}

	/**
	 * A caption written inside the table, as the classic editor and imported
	 * markup do, survives too.
	 */
	public function test_clean_content_keeps_an_inline_table_caption() {
		$content = Lite_Site::clean_content( '<table><caption>Zones</caption><tr><td>North</td></tr></table>' );

		$this->assertStringContainsString( '<caption>Zones</caption>', $content, 'An inline caption survives.' );
	}

	/**
	 * An image inside a table must not take the table with it. The figure pass
	 * runs before the allowlist, and the block editor wraps a table in a figure,
	 * so an unguarded match would replace the whole table with one placeholder.
	 */
	public function test_clean_content_keeps_a_table_that_contains_an_image() {
		$content = Lite_Site::clean_content(
			'<!-- wp:table --><figure class="wp-block-table"><table><tr><td><img src="https://some-other-domain.test/map.jpg" alt="Zone map"></td><td>Evacuate</td></tr></table></figure><!-- /wp:table -->'
		);

		$this->assertStringContainsString( '<table>', $content, 'The table survives an image in a cell.' );
		$this->assertStringContainsString( 'Evacuate', $content, 'Cell text beside the image survives.' );
		$this->assertStringContainsString( '<td><div class="lite-image-placeholder"', $content, 'The image becomes a placeholder inside its own cell, not in place of the table.' );
	}

	/**
	 * A core table block, as the editor saves it.
	 *
	 * Block delimiters matter: do_blocks() skips wpautop for block content, and
	 * without them the cells are separated by newlines the real bug never had.
	 *
	 * @return string
	 */
	private function table_block() {
		return '<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th scope="col">Zone</th><th scope="col">Status</th></tr></thead><tbody><tr><td>North</td><td>Evacuate</td></tr></tbody></table><figcaption class="wp-element-caption">Evacuation zones</figcaption></figure><!-- /wp:table -->';
	}

	/**
	 * A table cell may span rows and columns.
	 */
	public function test_clean_content_keeps_table_cell_spans() {
		$content = Lite_Site::clean_content( '<table><tr><td colspan="2" rowspan="3">Cell</td></tr></table>' );

		$this->assertStringContainsString( 'colspan="2"', $content, 'Column spans survive.' );
		$this->assertStringContainsString( 'rowspan="3"', $content, 'Row spans survive.' );
	}

	/**
	 * An image with no figure around it still becomes a placeholder, so the
	 * reader is told an image is there and can load it.
	 */
	public function test_clean_content_converts_bare_image_to_placeholder() {
		$content = Lite_Site::clean_content(
			'<p>Before</p><p><img src="https://some-other-domain.test/map.jpg" alt="A map of road closures"></p><p>After</p>'
		);

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'A placeholder should be emitted.' );
		$this->assertStringContainsString( 'A map of road closures', $content, 'The alt text should survive.' );
		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/map.jpg"', $content, 'The source should be kept for loading on demand.' );
		$this->assertStringNotContainsString( '<img', $content, 'No image should be downloaded on first load.' );
		$this->assertStringContainsString( 'Before', $content, 'Surrounding copy should survive.' );
		$this->assertStringContainsString( 'After', $content, 'Surrounding copy should survive.' );
	}

	/**
	 * An image marked decorative with an empty alt stays out of the way: a
	 * box with no label would tell the reader nothing.
	 */
	public function test_clean_content_drops_decorative_images() {
		$content = Lite_Site::clean_content(
			'<p>Before</p><img class="wp-block-cover__image-background" src="https://some-other-domain.test/cover.jpg" alt="">'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'A decorative image needs no placeholder.' );
		$this->assertStringContainsString( 'Before', $content, 'Surrounding copy should survive.' );
	}

	/**
	 * An image tag with nothing to load leaves nothing behind.
	 */
	public function test_clean_content_drops_image_without_a_source() {
		$content = Lite_Site::clean_content( '<p>Before</p><img alt="No source">' );

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'No placeholder without a source.' );
		$this->assertStringNotContainsString( '<img', $content, 'The empty image tag is removed.' );
		$this->assertStringContainsString( 'Before', $content, 'Surrounding copy should survive.' );
	}

	/**
	 * A plugin can keep more markup through the allowlist filter, and without
	 * the filter that markup is stripped.
	 */
	public function test_clean_content_allowlist_is_filterable() {
		$content = '<div class="feed" data-cursor="42"><time datetime="2026-01-01T12:00:00+00:00">Noon</time></div>';

		$default = Lite_Site::clean_content( $content );

		$this->assertStringNotContainsString( 'data-cursor', $default, 'Data attributes are stripped by default.' );
		$this->assertStringNotContainsString( '<time', $default, 'Time elements are stripped by default.' );

		$allow = function ( $allowed_html ) {
			$allowed_html['div']['data-cursor'] = true;
			$allowed_html['time']               = [ 'datetime' => true ];
			return $allowed_html;
		};

		add_filter( 'newspack_lite_site_allowed_html', $allow );
		$filtered = Lite_Site::clean_content( $content );
		remove_filter( 'newspack_lite_site_allowed_html', $allow );

		$this->assertStringContainsString( 'data-cursor="42"', $filtered, 'The filter keeps an added attribute.' );
		$this->assertStringContainsString( '<time datetime="2026-01-01T12:00:00+00:00">Noon</time>', $filtered, 'The filter keeps an added element.' );
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
	 * Custom query vars are registered without dropping existing ones once
	 * Lite Site has been turned on.
	 */
	public function test_registers_query_vars() {
		self::set_settings( [ 'enabled' => true ] );
		$vars = Lite_Site::register_query_vars( [ 'existing_var' ] );

		$this->assertContains( 'existing_var', $vars, 'Existing query vars should be preserved.' );
		$this->assertContains( 'is_lite', $vars, 'The lite site flag should be registered.' );
		$this->assertContains( 'lite_path', $vars, 'The lite path should be registered.' );
		$this->assertContains( 'lite_page', $vars, 'The lite page number should be registered.' );
	}
}
