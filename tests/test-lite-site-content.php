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
	public function test_wraps_a_table_in_a_scroller_the_reader_can_use() {
		// Through the same sanitizer the template applies, so the test cannot
		// pass on markup the page never serves.
		$html = Lite_Site::wrap_tables_for_scrolling( wp_kses_post( Lite_Site::clean_content( $this->table_block() ) ) );

		$this->assertStringContainsString( 'class="lite-table-scroll"', $html, 'The table is wrapped in its own scroller.' );
		$this->assertStringContainsString( 'tabindex="0"', $html, 'The scroller carries the attribute the keyboard needs.' );
		$this->assertStringContainsString( 'aria-label=', $html, 'The scroller is named, so a keyboard stop is explained.' );
		$this->assertStringContainsString( '</table></div>', $html, 'The wrapper closes around the table.' );
	}

	/**
	 * Only the outermost table is wrapped. Wrapping a nested one would close
	 * the div inside the outer table, where the parser drops it, leaving the
	 * wrapper open over the rest of the article.
	 */
	public function test_wraps_only_the_outermost_of_nested_tables() {
		$html = Lite_Site::wrap_tables_for_scrolling(
			'<table><tr><td>outer<table><tr><td>inner</td></tr></table></td></tr></table><p>After.</p>'
		);

		$this->assertSame( 1, substr_count( $html, 'lite-table-scroll' ), 'Only one scroller is emitted.' );
		$this->assertStringStartsWith( '<div class="lite-table-scroll"', $html, 'The scroller opens before the outer table.' );
		$this->assertStringContainsString( '</table></div><p>After.</p>', $html, 'The scroller closes after the outer table.' );
	}

	/**
	 * A table whose closing tag never arrives is left alone rather than being
	 * wrapped by an unbalanced div.
	 */
	public function test_an_unclosed_table_does_not_swallow_what_follows() {
		$html = Lite_Site::wrap_tables_for_scrolling(
			'<table><tr><td>orphan</td></tr><p>Between.</p><table><tr><td>good</td></tr></table>'
		);

		$this->assertStringNotContainsString( '<div class="lite-table-scroll" tabindex="0" role="group" aria-label="Table, scrolls sideways"><table><tr><td>orphan', $html, 'The wrapper does not open at the unclosed table.' );
		$this->assertStringContainsString( '<p>Between.</p>', $html, 'The paragraph between the tables is left where it was.' );
	}

	/**
	 * Only one table is involved, and the wrapper closes around it.
	 */
	public function test_wraps_a_single_closed_table() {
		$html = Lite_Site::wrap_tables_for_scrolling( '<p>Before.</p><table><tr><td>North</td></tr></table><p>After.</p>' );

		$this->assertStringContainsString( '<p>Before.</p><div class="lite-table-scroll"', $html, 'The wrapper opens at the table.' );
		$this->assertStringContainsString( '</table></div><p>After.</p>', $html, 'The wrapper closes at the table.' );
	}

	/**
	 * A self-closing table tag closes nothing, so it must not strand the walk
	 * and leave every later table unwrapped.
	 */
	public function test_a_self_closing_table_tag_does_not_strand_the_walk() {
		$html = Lite_Site::wrap_tables_for_scrolling( '<table /><p>Text.</p><table><tr><td>North</td></tr></table>' );

		$this->assertSame( 1, substr_count( $html, 'lite-table-scroll' ), 'The real table is still wrapped.' );
	}

	/**
	 * A failed preg_* call falls back to its input rather than passing null on,
	 * which would blank the article.
	 */
	public function test_a_failed_pattern_falls_back_to_the_original() {
		$this->assertSame( 'original', self::invoke_private( Lite_Site::class, 'or_original', [ null, 'original' ] ), 'A null result falls back.' );
		$this->assertSame( 'replaced', self::invoke_private( Lite_Site::class, 'or_original', [ 'replaced', 'original' ] ), 'A string result is kept.' );
	}

	/**
	 * A large figure must not defeat the content pipeline. A pattern that hits
	 * a PCRE limit returns null, which would blank the article and cache the
	 * blank — on a results table during an election, say.
	 */
	public function test_clean_content_survives_a_very_large_figure() {
		$rows = '';
		for ( $i = 0; $i < 400; $i++ ) {
			$rows .= '<tr><td>Precinct ' . $i . '</td><td>Reporting</td><td>Turnout steady through the afternoon</td></tr>';
		}
		$content = Lite_Site::clean_content( '<figure class="wp-block-table"><table>' . $rows . '</table></figure>' );

		$this->assertNotSame( '', $content, 'A large figure does not blank the article.' );
		$this->assertStringContainsString( 'Precinct 399', $content, 'The last row survives.' );
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
		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'A table figure is not treated as an image figure.' );
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

	/**
	 * A placeholder written in post content is not one this plugin built.
	 *
	 * Every piece of markup a placeholder is made of passes wp_kses(), so a
	 * custom HTML block or an imported feed item could write one and have a
	 * reader's tap fetch an arbitrary host.
	 */
	public function test_clean_content_clears_a_forged_placeholder() {
		$content = Lite_Site::clean_content(
			'<div class="lite-image-placeholder" data-src="https://tracker.some-other-domain.test/p.gif?uid=1">'
			. '<button class="lite-image-load-btn" type="button">Load image</button></div>'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'The hook class does not survive from content.' );
		$this->assertStringNotContainsString( 'lite-image-load-btn', $content, 'Nor does the button class.' );
	}

	/**
	 * The same holds for the table wrapper's class.
	 */
	public function test_clean_content_clears_a_forged_table_wrapper() {
		$content = Lite_Site::clean_content( '<div class="lite-table-scroll"><p>Not a table.</p></div>' );

		$this->assertStringNotContainsString( 'lite-table-scroll', $content, 'The wrapper class does not survive from content.' );
		$this->assertStringContainsString( 'Not a table.', $content, 'The copy inside it does.' );
	}

	/**
	 * A placeholder this plugin builds still gets its classes.
	 */
	public function test_clean_content_still_builds_its_own_placeholder() {
		$content = Lite_Site::clean_content(
			'<figure class="wp-block-image"><img src="https://some-other-domain.test/photo.jpg" alt="Flood damage"/></figure>'
		);

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'The plugin still emits a placeholder.' );
		$this->assertStringContainsString( 'lite-image-load-btn', $content, 'With its button.' );
	}

	/**
	 * A srcset candidate replaces src rather than supplementing it, so an
	 * unvetted candidate is the image the browser would actually load.
	 */
	public function test_clean_content_drops_a_srcset_carrying_an_inline_payload() {
		$blob    = str_repeat( 'A', 40000 );
		$content = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/photo.jpg" srcset="data:image/png;base64,' . $blob . ' 1x" alt="Chart"/></figure>'
		);

		$this->assertStringNotContainsString( $blob, $content, 'The payload is not carried in the page.' );
		$this->assertStringNotContainsString( 'data-srcset', $content, 'The srcset is dropped rather than half filtered.' );
		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/photo.jpg"', $content, 'The vetted source still loads.' );
	}

	/**
	 * The same for a candidate pointing somewhere the source rules reject.
	 */
	public function test_clean_content_drops_a_srcset_with_a_rejected_candidate() {
		$content = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/photo.jpg" srcset="https://some-other-domain.test/ok.jpg 1x, javascript:alert(1) 2x" alt="Flood"/></figure>'
		);

		$this->assertStringNotContainsString( 'data-srcset', $content, 'One bad candidate drops the whole list.' );
		$this->assertStringNotContainsString( 'javascript:', $content, 'And it does not reach the page.' );
	}

	/**
	 * A CDN path can hold a transform with a comma in it. Splitting the list
	 * on commas before whitespace truncated the URL.
	 */
	public function test_clean_content_keeps_a_srcset_url_containing_a_comma() {
		$content = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/flood.jpg" srcset="https://cdn.some-other-domain.test/upload/w_800,h_600/flood.jpg 800w" alt="Flood"/></figure>'
		);

		$this->assertStringContainsString( 'w_800,h_600/flood.jpg 800w', $content, 'The whole URL survives with its descriptor.' );
	}

	/**
	 * A srcset longer than the budget is left off rather than shipped.
	 */
	public function test_clean_content_drops_an_oversized_srcset() {
		$candidates = [];
		for ( $i = 0; $i < 80; $i++ ) {
			$candidates[] = 'https://some-other-domain.test/a-very-long-image-file-name-' . $i . '.jpg ' . ( $i + 1 ) . 'w';
		}

		$content = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/photo.jpg" srcset="' . implode( ', ', $candidates ) . '" alt="Flood"/></figure>'
		);

		$this->assertStringNotContainsString( 'data-srcset', $content, 'An oversized list is left off.' );
		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/photo.jpg"', $content, 'The source still loads.' );
	}

	/**
	 * The post content filter is public, so a third-party callback can hand
	 * back something that is not a string. The page a reader reaches when
	 * everything else is down must not be the one that fatals.
	 */
	public function test_clean_content_survives_a_filter_returning_a_non_string() {
		foreach ( [ null, [ 'not', 'a', 'string' ] ] as $return_value ) {
			$callback = function () use ( $return_value ) {
				return $return_value;
			};

			add_filter( 'newspack_lite_site_post_content', $callback, 99 );
			$content = Lite_Site::clean_content( '<p>Story.</p>' );
			remove_filter( 'newspack_lite_site_post_content', $callback, 99 );

			$this->assertIsString( $content, 'clean_content() always returns a string.' );
		}
	}
}
