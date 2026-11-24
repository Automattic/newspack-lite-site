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
		$this->assertStringContainsString(
			'data-scroll-label="Table, scrolls sideways"',
			$html,
			'The scroller carries the name its tab stop will be given.'
		);
		$this->assertStringNotContainsString(
			'tabindex=',
			$html,
			'But not the tab stop itself: the page script adds that only to a table that overflows.'
		);
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

		$this->assertStringNotContainsString( '<div class="lite-table-scroll" data-scroll-label="Table, scrolls sideways"><table><tr><td>orphan', $html, 'The wrapper does not open at the unclosed table.' );
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
	 * a PCRE limit returns null, and the fallback hands back the original
	 * content, so every figure in the article loses its placeholder.
	 *
	 * This guards against reintroducing a pattern that fails at this size: the
	 * tempered-dot form it replaced exhausted the JIT stack at about 24.6 KB.
	 * It is not a test of the backtrack limit, which a lazy scan reaches three
	 * orders of magnitude higher, at a megabyte of figure content.
	 *
	 * The image figure is what detects the failure. A table figure cannot: the
	 * pass leaves a table alone on purpose, so its output is the same whether
	 * the pass ran or fell back to the original.
	 */
	public function test_clean_content_survives_a_very_large_figure() {
		$rows = '';
		for ( $i = 0; $i < 400; $i++ ) {
			$rows .= '<tr><td>Precinct ' . $i . '</td><td>Reporting</td><td>Turnout steady through the afternoon</td></tr>';
		}
		$content = Lite_Site::clean_content( '<figure class="wp-block-table"><table>' . $rows . '</table></figure>' );

		$this->assertNotSame( '', $content, 'A large figure does not blank the article.' );
		$this->assertStringContainsString(
			'<td>Precinct 399</td>',
			$content,
			'The last row survives as a row. Asserting the text alone passes on trunk too, where the table is stripped and its cells run together.'
		);

		$caption = str_repeat( 'Turnout steady through the afternoon. ', 1000 );
		$image   = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/precincts.jpg" alt="Precinct map">'
			. '<figcaption>' . $caption . '</figcaption></figure>'
		);

		$this->assertStringContainsString( 'lite-image-placeholder', $image, 'A large image figure still becomes a placeholder.' );
		$this->assertStringContainsString( 'precincts.jpg', $image, 'The placeholder keeps the image it was built from.' );
	}

	/**
	 * A table's caption survives, and belongs to the table. The block editor
	 * saves it as a figcaption beside the table; the allowlist drops the
	 * figure that joined them, so it is moved into the table's own caption
	 * rather than left loose underneath it.
	 */
	public function test_clean_content_keeps_a_block_table_caption() {
		$content = Lite_Site::clean_content( $this->table_block() );

		$this->assertStringContainsString( '<caption>Evacuation zones</caption>', $content, 'The caption survives, tied to its table.' );
		$this->assertStringNotContainsString( '<figcaption', $content, 'And is not left as an element belonging to nothing.' );
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
		$this->assertStringContainsString( '<td><span class="lite-image-placeholder"', $content, 'The image becomes a placeholder inside its own cell, not in place of the table.' );
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
	 * Attribute names are not case sensitive in HTML, and syndicated or
	 * migrated markup is full of uppercase ones.
	 */
	public function test_clean_content_reads_uppercase_attributes() {
		$content = Lite_Site::clean_content( '<p>Story.</p><IMG SRC="https://some-other-domain.test/photo.jpg" ALT="Flood damage">' );

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'An uppercase tag still becomes a placeholder.' );
		$this->assertStringContainsString( 'Flood damage', $content, 'Its alt text survives.' );
	}

	/**
	 * Alt text with an apostrophe in it keeps the whole sentence.
	 */
	public function test_clean_content_keeps_alt_text_containing_an_apostrophe() {
		$content = Lite_Site::clean_content( '<img src="https://some-other-domain.test/a.jpg" alt="Mayor\'s address to the council">' );

		$this->assertStringContainsString( 'Mayor&#039;s address to the council', $content, 'The alt text is not truncated at the apostrophe.' );
	}

	/**
	 * A lazy-loaded image carries its real description in alt and an empty
	 * data-alt. Reading the wrong one would drop a described photograph.
	 */
	public function test_clean_content_does_not_confuse_data_attributes_with_their_own() {
		$content = Lite_Site::clean_content(
			'<img data-alt="" data-width="1" data-height="1" src="https://some-other-domain.test/photo.jpg" alt="A map of road closures" width="1600" height="900">'
		);

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'The photograph is still offered.' );
		$this->assertStringContainsString( 'A map of road closures', $content, 'Its own alt text is used.' );
	}

	/**
	 * A malformed tag must not swallow the copy that follows it. An
	 * unterminated quote used to let the match run past the tag and delete the
	 * sentence after it.
	 */
	public function test_clean_content_does_not_swallow_copy_after_a_malformed_image() {
		$content = Lite_Site::clean_content(
			'<p>Lead.</p><img src="a.jpg" alt="Map of closures<p>Crews cleared 6" of snow overnight.</p><p>The road reopened.</p>'
		);

		$this->assertStringContainsString( 'The road reopened.', $content, 'Later copy survives.' );
		$this->assertStringContainsString( 'Lead.', $content, 'Earlier copy survives.' );
		$this->assertStringContainsString( 'of snow overnight.', $content, 'So does the sentence the match could have swallowed.' );
	}

	/**
	 * A very large image tag must not defeat the pipeline. A pattern that hits
	 * a PCRE limit returns null, which would blank the article.
	 */
	public function test_clean_content_survives_a_very_large_image_tag() {
		$blob    = str_repeat( 'A', 40000 );
		$content = Lite_Site::clean_content( '<p>Story.</p><img src="data:image/png;base64,' . $blob . '" alt="Chart">' );

		$this->assertStringContainsString( 'Story.', $content, 'A huge tag does not blank the article.' );
		$this->assertStringNotContainsString( $blob, $content, 'A blob this size is not carried in the page.' );
		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'And it is not offered behind a button either.' );
	}

	/**
	 * A small inline image needs no request at all, so it is worth keeping
	 * where a large one is not.
	 */
	public function test_clean_content_keeps_a_small_inline_data_image() {
		$src     = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==';
		$content = Lite_Site::clean_content( '<p>Story.</p><img src="' . $src . '" alt="Turnout chart" width="600" height="400">' );

		$this->assertStringContainsString( 'data-src="' . $src . '"', $content, 'A small inline image is kept.' );
		$this->assertStringContainsString( 'Turnout chart', $content, 'With its description.' );
	}

	/**
	 * An inline SVG is markup, and an <img> is not where this plugin wants to
	 * find out how a given browser treats it.
	 */
	public function test_clean_content_drops_an_inline_svg_data_image() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><img src="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=" alt="Chart" width="90" height="90">'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'No placeholder for an inline SVG.' );
		$this->assertStringNotContainsString( 'image/svg', $content, 'Its payload does not reach the page.' );
		$this->assertStringContainsString( 'Story.', $content, 'The copy around it survives.' );
	}

	/**
	 * The placeholder replaces an image that can sit inside a paragraph, so it
	 * has to be phrasing content rather than a block element.
	 */
	public function test_placeholder_is_inline_safe() {
		$content = Lite_Site::clean_content( '<p>The chart <img src="https://some-other-domain.test/c.png" alt="Turnout"> shows turnout.</p>' );

		$this->assertStringContainsString( '<span class="lite-image-placeholder"', $content, 'The placeholder is a span.' );
		$this->assertStringNotContainsString( '<div class="lite-image-placeholder"', $content, 'It is not a block element inside a paragraph.' );
		$this->assertStringContainsString( 'shows turnout.', $content, 'Copy after the image keeps its place.' );
	}

	/**
	 * A tracking pixel never becomes a Load image button. Feed items routinely
	 * end with a 1x1 beacon, and the lite page's promise is that nothing third
	 * party is fetched unless a reader asks for it.
	 */
	public function test_clean_content_drops_tracking_pixels() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><img src="https://feeds.some-other-domain.test/~r/site/~4/abc" height="1" width="1" border="0">'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'A 1x1 beacon is not offered to the reader.' );
		$this->assertStringNotContainsString( 'feeds.some-other-domain.test', $content, 'Its URL does not reach the page.' );
		$this->assertStringContainsString( 'Story.', $content, 'The copy around it survives.' );
	}

	/**
	 * A real photograph with no alt attribute still earns a placeholder: the
	 * size rule is for beacons, not for images an author simply did not
	 * describe.
	 */
	public function test_clean_content_keeps_an_image_with_no_alt_attribute() {
		$content = Lite_Site::clean_content( '<p>Story.</p><img src="https://some-other-domain.test/photo.jpg" width="1200" height="800">' );

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'An undescribed photograph is still offered.' );
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
	 * The classic editor writes alt="" into every insert whose attachment has
	 * no alt text, so an empty alt cannot be read as "decorative": doing so
	 * deletes undescribed news photographs, which is the bug this pass exists
	 * to fix.
	 */
	public function test_clean_content_keeps_a_photograph_with_an_empty_alt() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><img class="alignnone size-full wp-image-2" src="https://some-other-domain.test/photo.jpg" alt="" width="800" height="600" />'
		);

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'An undescribed photograph is still offered.' );
		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/photo.jpg"', $content, 'With its source.' );
	}

	/**
	 * A beacon gives itself away with one edge. Requiring both let a 1x100
	 * spacer through with a Load image button on it.
	 */
	public function test_clean_content_drops_a_beacon_with_one_tiny_edge() {
		$content = Lite_Site::clean_content( '<p>Story.</p><img src="https://pixel.some-other-domain.test/b.gif" width="1" height="100">' );

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'A 1x100 spacer is not offered to the reader.' );
		$this->assertStringContainsString( 'Story.', $content, 'The copy around it survives.' );
	}

	/**
	 * The common noscript beacon declares its size in a style attribute and
	 * carries no width or height at all.
	 */
	public function test_clean_content_drops_a_beacon_sized_in_a_style_attribute() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><img src="https://pixel.some-other-domain.test/b.gif?v=noscript" style="height:0;width:0;">'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'A zero-sized beacon is not offered.' );
		$this->assertStringNotContainsString( 'pixel.some-other-domain.test', $content, 'Its URL does not reach the page.' );
	}

	/**
	 * A min-width:0 reset is not a tracking pixel. Matching the property
	 * loosely would delete the photograph it sits on.
	 */
	public function test_clean_content_keeps_an_image_with_a_min_width_reset() {
		$content = Lite_Site::clean_content(
			'<img src="https://some-other-domain.test/e.jpg" alt="Map" style="min-width:0;max-width:100%" width="800" height="600">'
		);

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'The photograph survives a CSS reset.' );
		$this->assertStringContainsString( 'Map', $content, 'With its description.' );
	}

	/**
	 * A lazy loader parks a one-pixel stub on src and puts the real URL on
	 * data-src. Taking src would hand the reader a transparent pixel.
	 */
	public function test_clean_content_prefers_a_lazy_loaded_source_over_a_stub() {
		$stub    = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
		$content = Lite_Site::clean_content(
			'<img src="' . $stub . '" data-src="https://some-other-domain.test/real.jpg" alt="Flood" width="1200" height="800">'
		);

		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/real.jpg"', $content, 'The real URL is what the button loads.' );
		$this->assertStringNotContainsString( 'R0lGOD', $content, 'The stub is not what the reader gets.' );
	}

	/**
	 * An image can carry its only usable URL in a srcset, which imports and
	 * save-time rewriters leave behind.
	 */
	public function test_clean_content_falls_back_to_a_srcset_url() {
		$content = Lite_Site::clean_content(
			'<img srcset="https://some-other-domain.test/p-800.jpg 1x, https://some-other-domain.test/p-1600.jpg 2x" alt="Evacuation route">'
		);

		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/p-800.jpg"', $content, 'The first candidate is used as the source.' );
		$this->assertStringContainsString( 'Evacuation route', $content, 'The alt text is not lost with it.' );
	}

	/**
	 * The canonical lazysizes shape: no src at all, everything on data-*.
	 */
	public function test_clean_content_falls_back_to_a_data_srcset_url() {
		$content = Lite_Site::clean_content(
			'<img class="lazyload" data-srcset="https://some-other-domain.test/q-800.jpg 800w" alt="Route">'
		);

		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/q-800.jpg"', $content, 'A data-srcset candidate is used.' );
	}

	/**
	 * A document-relative path resolves against the lite URL, not the
	 * article's, so images/flood.jpg on /lite/2026/03/story asks for
	 * /lite/2026/03/images/flood.jpg. A feed item's relative paths belong to
	 * the partner's site and do not resolve here at all. Either way the
	 * reader would tap Load image and get a 404, and safe_image_srcset()
	 * already turns these away, so src agrees with it.
	 */
	public function test_clean_content_drops_a_document_relative_source() {
		$content = Lite_Site::clean_content( '<img src="images/flood.jpg" alt="Flood" width="800" height="600">' );

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'No button is offered for a path that cannot resolve.' );
		$this->assertStringNotContainsString( 'images/flood.jpg', $content, 'And the path does not reach the page.' );
		$this->assertStringNotContainsString( 'http://images/', $content, 'Least of all rewritten to a host named after the folder.' );
	}

	/**
	 * A root-relative path does resolve, and is kept.
	 */
	public function test_clean_content_keeps_a_root_relative_source() {
		$content = Lite_Site::clean_content( '<img src="/wp-content/uploads/flood.jpg" alt="Flood" width="800" height="600">' );

		$this->assertStringContainsString( 'data-src="/wp-content/uploads/flood.jpg"', $content, 'A path from the root is offered as it is.' );
	}

	/**
	 * A source with a scheme the site does not allow is not offered behind a
	 * button that would do nothing, or worse.
	 */
	public function test_clean_content_drops_a_disallowed_scheme() {
		$content = Lite_Site::clean_content( '<p>Before</p><img src="javascript:alert(1)" alt="X" width="90" height="90">' );

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'No placeholder for a scheme the site rejects.' );
		$this->assertStringNotContainsString( 'javascript:', $content, 'And the scheme does not reach the page.' );
		$this->assertStringContainsString( 'Before', $content, 'Surrounding copy should survive.' );
	}

	/**
	 * IMG_TAG_PATTERN stops at the first ">", so a ">" inside a quoted
	 * attribute truncates the match.
	 *
	 * The outcome is the same whether the callback deletes the match or hands
	 * it back, since wp_kses() removes it either way; this pins the outcome,
	 * not the branch that produces it.
	 */
	public function test_clean_content_hands_back_a_tag_it_cannot_parse() {
		$content = Lite_Site::clean_content(
			'<p>Lead.</p><img src="https://some-other-domain.test/a.jpg" alt="Snowfall > 12 inches"><p>The road reopened.</p>'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'Half a tag is not turned into a placeholder.' );
		$this->assertStringContainsString( 'Lead.', $content, 'Earlier copy survives.' );
		$this->assertStringContainsString( 'The road reopened.', $content, 'Later copy survives.' );
	}

	/**
	 * The classic editor links an image to the full-size original by default,
	 * so the placeholder lands inside an <a>, and the anchor has to survive
	 * for the image to be a link again once it loads.
	 *
	 * Whether tapping the button navigates away is a question about the page's
	 * script, which this cannot reach.
	 */
	public function test_clean_content_keeps_a_placeholder_inside_a_link() {
		$content = Lite_Site::clean_content(
			'<p>Lead.</p><a href="https://some-other-domain.test/flood-scaled.jpg"><img src="https://some-other-domain.test/flood-300x200.jpg" alt="Flood damage" width="300" height="200" /></a>'
		);

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'The image still becomes a placeholder.' );
		$this->assertStringContainsString( 'flood-scaled.jpg', $content, 'And the link around it survives.' );
		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/flood-300x200.jpg"', $content, 'The button loads the smaller file, not the original.' );
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
	 * Shapes a lazy loader leaves an <img> in, and the source a reader should get.
	 *
	 * @return array[]
	 */
	public function lazy_image_source_provider(): array {
		return [
			'data-src beside a stub src'  => [
				'<img src="https://some-other-domain.test/1x1.gif" data-src="https://some-other-domain.test/real.jpg">',
				'https://some-other-domain.test/real.jpg',
			],
			'a stub src written after it' => [
				'<img data-src="https://some-other-domain.test/real.jpg" src="https://some-other-domain.test/1x1.gif">',
				'https://some-other-domain.test/real.jpg',
			],
			'data-src with no src at all' => [
				'<img data-src="https://some-other-domain.test/real.jpg">',
				'https://some-other-domain.test/real.jpg',
			],
			'data-lazy-src'               => [
				'<img src="https://some-other-domain.test/1x1.gif" data-lazy-src="https://some-other-domain.test/real.jpg">',
				'https://some-other-domain.test/real.jpg',
			],
			'data-original'               => [
				'<img data-original="https://some-other-domain.test/real.jpg">',
				'https://some-other-domain.test/real.jpg',
			],
			'an ordinary src on its own'  => [
				'<img src="https://some-other-domain.test/real.jpg">',
				'https://some-other-domain.test/real.jpg',
			],
		];
	}

	/**
	 * A lazy loader puts a stub in src and the real image in a data attribute, so
	 * reading src alone offers the reader a transparent pixel, and reading it
	 * alone when there is no src at all offers them nothing.
	 *
	 * @dataProvider lazy_image_source_provider
	 *
	 * @param string $img      The image tag to wrap in a figure.
	 * @param string $expected The source the placeholder should carry.
	 */
	public function test_clean_content_reads_the_source_a_lazy_loader_left( string $img, string $expected ) {
		$content = Lite_Site::clean_content( '<figure>' . $img . '</figure>' );

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'A placeholder should be emitted.' );
		$this->assertStringContainsString(
			sprintf( 'data-src="%s"', $expected ),
			$content,
			'The placeholder should carry the image the browser would have loaded.'
		);
	}

	/**
	 * Places a source can be forged, and where the real one sits in the tag.
	 *
	 * @return array[]
	 */
	public function forged_source_provider(): array {
		$real   = 'https://some-other-domain.test/real.jpg';
		$forged = 'data-src="https://forger.test/p.jpg"';

		return [
			'forged inside title, real src first' => [
				'<img src="' . $real . '" alt="Flood" title=\'x ' . $forged . ' y\'>',
			],
			'forged inside title, real src last'  => [
				'<img title=\'x ' . $forged . ' y\' src="' . $real . '" alt="Flood">',
			],
			'forged inside data-caption'          => [
				'<img src="' . $real . '" data-caption=\'' . $forged . '\' alt="Flood">',
			],
			'forged inside alt'                   => [
				'<img alt=\'' . $forged . '\' src="' . $real . '">',
			],
		];
	}

	/**
	 * An attribute value is text, not markup. A source written inside one is not
	 * an attribute of the tag, so it cannot be the source the reader is offered:
	 * the browser would load the real one, and the placeholder has to agree with
	 * the browser or it sends the reader somewhere the page never pointed.
	 *
	 * @dataProvider forged_source_provider
	 *
	 * @param string $img The image tag to wrap in a figure.
	 */
	public function test_clean_content_ignores_a_source_forged_in_an_attribute_value( string $img ) {
		$content = Lite_Site::clean_content( '<figure>' . $img . '</figure>' );

		$this->assertStringContainsString(
			'data-src="https://some-other-domain.test/real.jpg"',
			$content,
			'The real source should be the one offered.'
		);
		$this->assertStringNotContainsString(
			'data-src="https://forger.test/p.jpg"',
			$content,
			'A source written inside another attribute value should never be read as an attribute.'
		);
	}

	/**
	 * Attribute values need no quotes to be valid HTML, and a hand-written post
	 * or an imported feed item may carry none. Reading them with a pattern that
	 * required quotes dropped the whole figure.
	 */
	public function test_clean_content_reads_unquoted_image_attributes() {
		$content = Lite_Site::clean_content(
			'<figure><img src=https://some-other-domain.test/real.jpg alt=Flood></figure>'
		);

		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/real.jpg"', $content, 'An unquoted source should still be read.' );
		$this->assertStringContainsString( 'data-alt="Flood"', $content, 'An unquoted alt should still be read.' );
	}

	/**
	 * A tag name and an attribute name are case-insensitive in HTML.
	 */
	public function test_clean_content_reads_an_uppercase_image_tag() {
		$content = Lite_Site::clean_content(
			'<figure><IMG SRC="https://some-other-domain.test/real.jpg" ALT="Flood"></figure>'
		);

		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/real.jpg"', $content, 'An uppercase source should still be read.' );
		$this->assertStringContainsString( 'data-alt="Flood"', $content, 'An uppercase alt should still be read.' );
	}

	/**
	 * The alt text is the whole of what a placeholder can tell a reader about an
	 * image they have not loaded, so a data-alt written first must not take its
	 * place.
	 */
	public function test_clean_content_keeps_the_real_alt_beside_a_data_alt() {
		$content = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/real.jpg" data-alt="" alt="Flood damage on Mill Road"></figure>'
		);

		$this->assertStringContainsString( 'data-alt="Flood damage on Mill Road"', $content, 'The alt text should survive a data-alt.' );
	}

	/**
	 * A srcset candidate replaces src rather than supplementing it, so a srcset
	 * belonging to the stub must not travel with a source read from elsewhere.
	 */
	public function test_clean_content_pairs_a_srcset_with_the_source_it_belongs_to() {
		$content = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/1x1.gif" srcset="https://some-other-domain.test/1x1.gif 2x" data-src="https://some-other-domain.test/real.jpg"></figure>'
		);

		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/real.jpg"', $content, 'The real image should be the source.' );
		$this->assertStringNotContainsString( 'data-srcset', $content, 'The stub\'s srcset should not travel with it.' );
	}

	/**
	 * The srcset that belongs to the chosen source does travel with it.
	 */
	public function test_clean_content_keeps_the_srcset_belonging_to_the_chosen_source() {
		$content = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/1x1.gif" data-src="https://some-other-domain.test/real.jpg" data-srcset="https://some-other-domain.test/real-2x.jpg 2x"></figure>'
		);

		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/real.jpg"', $content, 'The real image should be the source.' );
		$this->assertStringContainsString( 'data-srcset="https://some-other-domain.test/real-2x.jpg 2x"', $content, 'Its own srcset should be kept.' );
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
		// Just over SRCSET_MAX_LENGTH once escaped, so the test pins the
		// constant rather than "some budget".
		$candidates = [];
		for ( $i = 0; $i < 31; $i++ ) {
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
			$this->assertStringContainsString( 'Story.', $content, 'And the reader still gets the story, rather than a blank page.' );
		}
	}

	/**
	 * A zero dimension is what a broken importer writes, not what a beacon
	 * writes. Treating it as tiny deleted real photographs.
	 */
	public function test_clean_content_keeps_a_photograph_with_a_zero_dimension() {
		$content = Lite_Site::clean_content( '<img src="https://some-other-domain.test/photo.jpg" alt="Levee breach" width="0" height="0">' );

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'The photograph is still offered.' );
		$this->assertStringContainsString( 'Levee breach', $content, 'With its description.' );
		$this->assertStringNotContainsString( 'data-width', $content, 'A zero dimension is not passed on to reserve space with.' );
	}

	/**
	 * The common inline-styled beacon carries no width or height attributes
	 * and a one-pixel style, so matching only zero missed it.
	 */
	public function test_clean_content_drops_a_one_pixel_styled_beacon() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><img src="https://pixel.some-other-domain.test/b.gif?u=9" style="position:absolute;width:1px;height:1px">'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'A one-pixel beacon is not offered.' );
		$this->assertStringNotContainsString( 'pixel.some-other-domain.test', $content, 'Its URL does not reach the page.' );
	}

	/**
	 * Only px and unitless lengths are read as pixels, so a 1em icon is left
	 * alone rather than read as a one-pixel beacon.
	 */
	public function test_clean_content_keeps_an_image_sized_in_em() {
		$content = Lite_Site::clean_content( '<img src="https://some-other-domain.test/icon.png" alt="Rain" style="width:1em;height:1em">' );

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'An em-sized image is not a beacon.' );
	}

	/**
	 * The beacon rule applies inside a figure too. Before, a feed's wrapped
	 * beacon kept its URL on the page with a button inviting the tap.
	 */
	public function test_clean_content_drops_a_beacon_wrapped_in_a_figure() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><figure><img src="https://pixel.some-other-domain.test/t.gif" width="1" height="1" alt=""></figure>'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'A wrapped beacon is not offered either.' );
		$this->assertStringContainsString( 'Story.', $content, 'The copy around it survives.' );
	}

	/**
	 * The HTML parser swallows a <textarea> whole, so the class walk never
	 * sees inside one, and kses then drops the wrapper and keeps the
	 * children. That let a forged placeholder through complete, from content
	 * any contributor can write.
	 *
	 * @dataProvider atomic_wrapper_provider
	 * @param string $open  Opening tag.
	 * @param string $close Closing tag, or an empty string to leave it open.
	 */
	public function test_clean_content_clears_a_forgery_hidden_in_an_atomic_element( $open, $close ) {
		$content = Lite_Site::clean_content(
			'<p>Story.</p>' . $open
			. '<div class="lite-image-placeholder" data-src="https://tracker.some-other-domain.test/p.gif?uid=1">'
			. '<button class="lite-image-load-btn" type="button">Load image</button></div>' . $close
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'The hook class does not survive.' );
		$this->assertStringNotContainsString( 'lite-image-load-btn', $content, 'Nor does the button class.' );
		$this->assertStringContainsString( 'Story.', $content, 'The copy around it survives.' );
	}

	/**
	 * The Cover rule matches a class token, not a substring of the attribute.
	 */
	public function test_clean_content_keeps_an_image_whose_class_merely_contains_the_cover_name() {
		$content = Lite_Site::clean_content(
			'<img class="my-wp-block-cover__image-background-thumb" src="https://some-other-domain.test/photo.jpg" alt="Flood" width="800" height="600">'
		);

		$this->assertStringContainsString( 'lite-image-placeholder', $content, 'A lookalike class does not delete the image.' );
	}

	/**
	 * Some lazy loaders park a real placeholder FILE on src rather than a
	 * data: URI, so the reader got a blank graphic instead of the photograph.
	 */
	public function test_clean_content_prefers_a_lazy_source_over_a_file_stub() {
		$content = Lite_Site::clean_content(
			'<img class="lazy lazy-hidden" src="https://some-other-domain.test/plugins/a3-lazy-load/assets/images/lazy_placeholder.gif"'
			. ' data-src="https://some-other-domain.test/photo-1200x800.jpg" alt="Flood" width="1200" height="800">'
		);

		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/photo-1200x800.jpg"', $content, 'The real photograph is what loads.' );
		$this->assertStringNotContainsString( 'lazy_placeholder.gif', $content, 'The stub graphic is not.' );
	}

	/**
	 * Vendor-prefixed lazy attributes carry the real image too.
	 *
	 * @dataProvider lazy_attribute_provider
	 * @param string $attribute The attribute holding the real URL.
	 */
	public function test_clean_content_reads_vendor_lazy_attributes( $attribute ) {
		$content = Lite_Site::clean_content(
			'<img src="https://some-other-domain.test/stub.gif" ' . $attribute . '="https://some-other-domain.test/real.jpg" alt="Flood" width="900" height="600">'
		);

		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/real.jpg"', $content, 'The real URL is what the button loads.' );
	}

	/**
	 * The lazy attribute names in circulation.
	 *
	 * @return array<int,array<int,string>>
	 */
	public function lazy_attribute_provider() {
		return [
			[ 'data-src' ],
			[ 'data-lazy-src' ],
			[ 'data-original' ],
		];
	}

	/**
	 * The elements WP_HTML_Tag_Processor consumes together with their
	 * contents, closed and unclosed.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function atomic_wrapper_provider() {
		return [
			'textarea'          => [ '<textarea>', '</textarea>' ],
			'title'             => [ '<title>', '</title>' ],
			'iframe'            => [ '<iframe>', '</iframe>' ],
			'xmp'               => [ '<xmp>', '</xmp>' ],
			'noembed'           => [ '<noembed>', '</noembed>' ],
			'noframes'          => [ '<noframes>', '</noframes>' ],
			'unclosed textarea' => [ '<textarea>', '' ],
			'unclosed title'    => [ '<title>', '' ],
		];
	}

	/**
	 * A lazy loader emits the image twice, once lazily and once in a
	 * <noscript>. kses keeps the contents, so the reader used to get two
	 * placeholders and two buttons for one photograph.
	 */
	public function test_clean_content_does_not_duplicate_a_noscript_fallback() {
		$content = Lite_Site::clean_content(
			'<p><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="'
			. ' data-src="https://some-other-domain.test/real.jpg" alt="Flood">'
			. '<noscript><img src="https://some-other-domain.test/real.jpg" alt="Flood"></noscript></p>'
		);

		$this->assertSame( 1, substr_count( $content, 'lite-image-placeholder' ), 'One photograph gets one placeholder.' );
		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/real.jpg"', $content, 'And it loads the real image.' );
	}

	/**
	 * If the <noscript> holds the only image, it is still offered: the wrapper
	 * alone is removed, which is what kses did before.
	 */
	public function test_clean_content_keeps_a_noscript_only_image() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><noscript><img src="https://some-other-domain.test/only.jpg" alt="Flood"></noscript>'
		);

		$this->assertSame( 1, substr_count( $content, 'lite-image-placeholder' ), 'The only image is still offered.' );
		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/only.jpg"', $content, 'With its source.' );
	}

	/**
	 * An <img> written inside a script string was never on the page, so it
	 * must not become a button a reader can fire.
	 */
	public function test_clean_content_ignores_an_image_inside_a_script() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><script>var a = \'<img src="https://tracker.some-other-domain.test/p.gif">\';</script>'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'Markup inside a script is not content.' );
		$this->assertStringNotContainsString( 'tracker.some-other-domain.test', $content, 'Its URL does not reach the page.' );
	}

	/**
	 * A relative path can hold a colon in its filename, which is not a scheme.
	 * It is dropped for being document-relative, not mistaken for a scheme and
	 * handed to esc_url(), which would read 2026 as one and empty the value.
	 */
	public function test_clean_content_drops_a_relative_path_containing_a_colon() {
		$content = Lite_Site::clean_content( '<img src="2026:03:photo.jpg" alt="Flood" width="800" height="600">' );

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'It is turned away like any relative path.' );
		$this->assertStringNotContainsString( '2026:03:photo.jpg', $content, 'And does not reach the page.' );
	}

	/**
	 * An alt of "0" is a description, however terse.
	 */
	public function test_clean_content_treats_a_zero_alt_as_a_description() {
		$content = Lite_Site::clean_content( '<img src="https://some-other-domain.test/chart.png" alt="0" width="400" height="300">' );

		$this->assertStringContainsString( 'data-alt="0"', $content, 'The description is carried.' );
		$this->assertStringContainsString( 'Image: 0', $content, 'And used in the label.' );
	}

	/**
	 * Dimensions are carried so the figure can reserve space, which keeps the
	 * article from reflowing under the reader when the image arrives.
	 */
	public function test_clean_content_carries_dimensions_for_the_loaded_image() {
		$content = Lite_Site::clean_content( '<img src="https://some-other-domain.test/photo.jpg" alt="Flood" width="1200" height="800">' );

		$this->assertStringContainsString( 'data-width="1200"', $content, 'The width is carried.' );
		$this->assertStringContainsString( 'data-height="800"', $content, 'So is the height.' );
	}

	/**
	 * A class value can spell a reserved name with character references, which
	 * kses decodes back into the real name. A guard that looks for the literal
	 * name is therefore evadable; one that looks for the attribute name is not.
	 *
	 * @dataProvider encoded_class_provider
	 * @param string $class The class attribute value.
	 */
	public function test_clean_content_clears_an_entity_encoded_forgery( $class ) {
		$content = Lite_Site::clean_content(
			'<div class="' . $class . '" data-src="https://tracker.some-other-domain.test/p.gif">'
			. '<button class="lite&#45;image-load-btn" type="button">Load image</button></div>'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'The decoded class does not reach the page.' );
		$this->assertStringNotContainsString( 'lite-image-load-btn', $content, 'Nor does the button class.' );
	}

	/**
	 * Ways of spelling the same reserved name.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function encoded_class_provider() {
		return [
			'decimal hyphen' => [ 'lite&#45;image-placeholder' ],
			'hex hyphen'     => [ 'lite&#x2D;image-placeholder' ],
			'encoded letter' => [ '&#108;ite&#45;image-placeholder' ],
		];
	}

	/**
	 * The comment pass can join a class value back into a reserved name, so it
	 * has to run before the walk rather than after it.
	 *
	 * The class value is unquoted on purpose. Quoted, the embedded `<` stops
	 * wptexturize recognizing the opening tag, so it curls the quote, the
	 * allowlist discards the whole mangled attribute, and the walk never sees
	 * a usable class. The test would then pass whatever the pass order was.
	 */
	public function test_clean_content_clears_a_forgery_split_by_a_comment() {
		$content = Lite_Site::clean_content(
			'<div class=lite-image-place<!---->holder data-src=https://tracker.some-other-domain.test/p.gif>'
			. '<button class="lite-image-load-btn" type="button">Load image</button></div>'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'The rejoined class does not reach the page.' );
	}

	/**
	 * A document-relative candidate is not rejected by esc_url(), it is
	 * rewritten to a host named after the first path segment.
	 */
	public function test_clean_content_drops_a_relative_srcset_candidate() {
		$content = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/photo.jpg" srcset="photos/flood-800.jpg 800w" alt="Flood"/></figure>'
		);

		$this->assertStringNotContainsString( 'http://photos/', $content, 'A relative candidate is not relocated to a bogus host.' );
		$this->assertStringNotContainsString( 'data-srcset', $content, 'The list is dropped rather than half filtered.' );
		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/photo.jpg"', $content, 'The vetted source still loads.' );
	}

	/**
	 * The srcset grammar tracks parentheses in a descriptor and this parser
	 * does not, so a descriptor like 1x(,) used to split off a junk candidate
	 * that esc_url() turned into http://) and passed.
	 */
	public function test_clean_content_drops_a_parenthesized_srcset_descriptor() {
		$content = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/photo.jpg" srcset="https://some-other-domain.test/a.jpg 1x(,)" alt="Flood"/></figure>'
		);

		$this->assertStringNotContainsString( 'data-srcset', $content, 'A descriptor that is not a width or a density drops the list.' );
		$this->assertStringNotContainsString( 'http://)', $content, 'And no junk candidate is emitted.' );
	}

	/**
	 * A pathological srcset is rejected on its length before every candidate
	 * is parsed and escaped.
	 */
	public function test_clean_content_rejects_a_pathological_srcset_cheaply() {
		// Comfortably past the early bound without building a string large
		// enough to upset WordPress's own block parser.
		$srcset = implode( ', ', array_fill( 0, 1000, 'https://some-other-domain.test/a.jpg 1x' ) );

		// Elapsed time does not tell these apart: a thousand candidates parse
		// in a few milliseconds, so the clock passes whether the bound fired
		// or every candidate was escaped one by one. esc_url() runs the
		// clean_url filter once per call, so counting it says which happened.
		$escaped = 0;
		$counter = static function ( $url ) use ( &$escaped ) {
			++$escaped;
			return $url;
		};

		add_filter( 'clean_url', $counter );
		$content = Lite_Site::clean_content(
			'<figure><img src="https://some-other-domain.test/photo.jpg" srcset="' . $srcset . '" alt="Flood"/></figure>'
		);
		remove_filter( 'clean_url', $counter );

		$this->assertStringNotContainsString( 'data-srcset', $content, 'The oversized list is dropped.' );
		$this->assertLessThan(
			10,
			$escaped,
			'And dropped on its length, before the thousand candidates are escaped one by one.'
		);
	}
	/**
	 * Content with two figures, the first of them never closed.
	 *
	 * @return string
	 */
	private function unclosed_figure_before_another() {
		return '<p>Intro</p>'
			. '<figure><img src="https://some-other-domain.test/one.jpg" alt="First photo">'
			. '<p>A paragraph of reporting between the two figures.</p>'
			. '<figure><img src="https://some-other-domain.test/two.jpg" alt="Second photo"><figcaption>The second caption</figcaption></figure>'
			. '<p>Closing paragraph.</p>';
	}

	/**
	 * A figure left unclosed is ordinary in a hand-written post and in an
	 * imported feed item. Where it ends is a guess, and a guess that runs to the
	 * next figure's closing tag takes the reporting between them with it.
	 */
	public function test_clean_content_keeps_the_prose_after_an_unclosed_figure() {
		$content = Lite_Site::clean_content( $this->unclosed_figure_before_another() );

		$this->assertStringContainsString(
			'A paragraph of reporting between the two figures.',
			$content,
			'The reporting between the two figures survives.'
		);
		$this->assertStringContainsString(
			'Closing paragraph.',
			$content,
			'And so does the paragraph after them.'
		);
	}

	/**
	 * The second figure is a figure in its own right, so it still becomes a
	 * placeholder when the one before it was never closed.
	 */
	public function test_clean_content_keeps_a_figure_that_follows_an_unclosed_one() {
		$content = Lite_Site::clean_content( $this->unclosed_figure_before_another() );

		$this->assertStringContainsString(
			'data-src="https://some-other-domain.test/two.jpg"',
			$content,
			'The second photograph is still offered.'
		);
	}

	/**
	 * A caption describes the image in its own figure. Carrying it to an earlier
	 * one prints a description of one photograph underneath another.
	 *
	 * The figure left unclosed is handed back whole by the figure pass, since
	 * where it ends is a guess. Its image is then a bare one, so the pass after
	 * it offers the photograph on its own, with its alt text and no caption.
	 * Both photographs reach the reader and neither is described by the other's
	 * words.
	 */
	public function test_clean_content_keeps_a_caption_with_its_own_image() {
		$content = Lite_Site::clean_content( $this->unclosed_figure_before_another() );

		$this->assertSame(
			2,
			substr_count( $content, 'lite-image-placeholder' ),
			'Both photographs are offered.'
		);
		$this->assertMatchesRegularExpression(
			'/data-src="https:\/\/some-other-domain\.test\/one\.jpg"[^>]*data-alt="First photo"[^>]*>/',
			$content,
			'The image from the unclosed figure keeps its description.'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/data-src="https:\/\/some-other-domain\.test\/one\.jpg"[^>]*data-caption=/',
			$content,
			'And it is not given the other figure\'s caption.'
		);
		$this->assertMatchesRegularExpression(
			'/data-src="https:\/\/some-other-domain\.test\/two\.jpg"[^>]*data-caption="The second caption"/',
			$content,
			'The caption stays with the image from its own figure.'
		);
	}

	/**
	 * A gallery nests one figure per image. Each one already becomes its own
	 * placeholder with its own caption, and this is the contract for that:
	 * reading a figure's extent as ending at the next figure must not collapse
	 * a gallery into a single image.
	 */
	public function test_clean_content_gives_each_gallery_image_its_own_caption() {
		$content = Lite_Site::clean_content(
			'<figure class="wp-block-gallery">'
			. '<figure><img src="https://some-other-domain.test/g1.jpg" alt="Gallery one"><figcaption>First caption</figcaption></figure>'
			. '<figure><img src="https://some-other-domain.test/g2.jpg" alt="Gallery two"><figcaption>Second caption</figcaption></figure>'
			. '</figure>'
		);

		$this->assertSame(
			2,
			substr_count( $content, 'lite-image-placeholder' ),
			'Each image in the gallery gets its own placeholder.'
		);
		$this->assertMatchesRegularExpression(
			'/g1\.jpg"[^>]*data-caption="First caption"/',
			$content,
			'The first image keeps the first caption.'
		);
		$this->assertMatchesRegularExpression(
			'/g2\.jpg"[^>]*data-caption="Second caption"/',
			$content,
			'And the second keeps the second.'
		);
	}

	/**
	 * `wpautop` writes a closing paragraph tag after a figure that was never
	 * closed, and the allowlist keeps a closing tag whose opening tag it has
	 * just removed. Neither belongs on the page.
	 */
	public function test_clean_content_balances_a_stray_closing_tag() {
		$content = Lite_Site::clean_content(
			wpautop(
				'<p>Intro</p>'
				. '<figure><img src="https://some-other-domain.test/one.jpg" alt="A photo">'
				. '<p>After the figure.</p>'
			)
		);

		$this->assertSame(
			preg_match_all( '/<p[\s>]/', $content ),
			substr_count( $content, '</p>' ),
			'Every paragraph that closes was opened.'
		);
	}

	/**
	 * A fallback is judged against the image it holds, not against the page.
	 * An article with a photograph at the top and an evacuation map that only
	 * a <noscript> carries used to lose the map, because the photograph above
	 * it counted as "an image elsewhere" and every fallback was dropped.
	 */
	public function test_clean_content_keeps_a_noscript_only_image_beside_another_image() {
		$content = Lite_Site::clean_content(
			'<p><img src="https://some-other-domain.test/portrait.jpg" alt="The mayor"></p>'
			. '<p>Story.</p>'
			. '<noscript><img src="https://some-other-domain.test/evacuation-map.jpg" alt="Evacuation map"></noscript>'
		);

		$this->assertSame( 2, substr_count( $content, 'lite-image-placeholder' ), 'Both images are offered.' );
		$this->assertStringContainsString(
			'data-src="https://some-other-domain.test/evacuation-map.jpg"',
			$content,
			'The map the fallback carries is the only copy of it, so it survives.'
		);
	}

	/**
	 * The duplicate rule still has to fire when the page holds other images:
	 * a lazy-loaded photograph beside an unrelated one is still one
	 * photograph, not two.
	 */
	public function test_clean_content_drops_a_duplicate_fallback_beside_another_image() {
		$content = Lite_Site::clean_content(
			'<p><img src="https://some-other-domain.test/portrait.jpg" alt="The mayor"></p>'
			. '<p><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="'
			. ' data-src="https://some-other-domain.test/real.jpg" alt="Flood">'
			. '<noscript><img src="https://some-other-domain.test/real.jpg" alt="Flood"></noscript></p>'
		);

		$this->assertSame( 2, substr_count( $content, 'lite-image-placeholder' ), 'Two photographs, two placeholders.' );
		$this->assertSame(
			1,
			substr_count( $content, 'data-src="https://some-other-domain.test/real.jpg"' ),
			'The lazy-loaded one is offered once, not twice.'
		);
	}

	/**
	 * A browser drops tab, carriage return and line feed from a URL before
	 * requesting it. A scheme split across that whitespace therefore reads as
	 * a relative path here and as `data:` to the browser, which would put an
	 * inline SVG of any size on a page whose promise is to stay small.
	 */
	public function test_clean_content_rejects_a_scheme_split_by_whitespace() {
		$svg     = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg"></svg>' );
		$content = Lite_Site::clean_content(
			'<p>Story.</p><img src="' . substr( $svg, 0, 2 ) . '&#10;' . substr( $svg, 2 ) . '" alt="A map">'
		);

		$this->assertStringNotContainsString( 'svg', $content, 'The SVG never reaches the page.' );
		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'And it is not offered as an image.' );
	}

	/**
	 * The same whitespace inside `data:image/svg` would otherwise slip past
	 * the SVG test and be treated as an ordinary inline image.
	 */
	public function test_clean_content_rejects_an_svg_type_split_by_whitespace() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><img src="data:image/sv&#9;g+xml;base64,'
			. base64_encode( '<svg xmlns="http://www.w3.org/2000/svg"></svg>' ) . '" alt="A map">'
		);

		$this->assertStringNotContainsString(
			'lite-image-placeholder',
			$content,
			'Still recognized as an SVG, and still refused.'
		);
	}

	/**
	 * The allowlist keeps figcaption but drops figure, so a Table block's
	 * caption used to reach the reader as loose text after the table with
	 * nothing tying the two together.
	 */
	public function test_clean_content_gives_a_table_figures_caption_to_the_table() {
		$content = Lite_Site::clean_content(
			'<figure class="wp-block-table"><table><tr><td>North</td><td>Evacuate</td></tr></table>'
			. '<figcaption>Zones as of this morning.</figcaption></figure>'
		);

		$this->assertStringContainsString(
			'<caption>Zones as of this morning.</caption>',
			$content,
			'The caption belongs to the table now.'
		);
		$this->assertStringNotContainsString(
			'<figcaption>',
			$content,
			'And is not also left loose after it.'
		);
		$this->assertLessThan(
			strpos( $content, '<td>North</td>' ),
			strpos( $content, '<caption>' ),
			'A caption is the table\'s first child, where a screen reader reads it as the table\'s name.'
		);
	}

	/**
	 * A table that captions itself keeps its own. Only a figure's caption is
	 * moved, and only into a table that has none.
	 */
	public function test_clean_content_leaves_a_table_that_captions_itself() {
		$content = Lite_Site::clean_content(
			'<figure class="wp-block-table"><table><caption>Its own caption.</caption>'
			. '<tr><td>North</td></tr></table><figcaption>The figure\'s caption.</figcaption></figure>'
		);

		$this->assertStringContainsString( '<caption>Its own caption.</caption>', $content, 'The table keeps its own.' );
		$this->assertSame( 1, substr_count( $content, '<caption>' ), 'And is not given a second one.' );
	}

	/**
	 * An unterminated <script> pauses the tag processor at an incomplete
	 * token, so everything after it goes unwalked, while kses keeps that
	 * markup. A placeholder forged there would reach the reader with a button
	 * the page's own script acts on.
	 */
	public function test_clean_content_clears_a_forged_class_after_an_unterminated_script() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><script>var a = 1;'
			. '<div class="lite-image-placeholder" data-src="https://some-other-domain.test/p.gif">'
			. '<button class="lite-image-load-btn" type="button">Load</button></div>'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'No forged container survives.' );
		$this->assertStringNotContainsString( 'lite-image-load-btn', $content, 'And no forged button.' );
	}

	/**
	 * A `>` inside a quoted attribute hides markup from the tag processor,
	 * which reads it as part of the attribute value, while kses reads it as
	 * real markup and keeps it.
	 */
	public function test_clean_content_clears_a_forged_class_hidden_by_a_quoted_bracket() {
		$content = Lite_Site::clean_content(
			'<p title="x><div class=lite-image-placeholder data-src=https://some-other-domain.test/p.gif>'
			. '<button class=lite-image-load-btn type=button>Load</button></div>">'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'No forged container survives.' );
		$this->assertStringNotContainsString( 'lite-image-load-btn', $content, 'And no forged button.' );
	}

	/**
	 * A lazy loader that keeps the real file on an attribute this plugin does
	 * not read leaves only a stub behind: a transparent pixel in src. The
	 * fallback holds the only real copy, so offering both gave the reader two
	 * buttons, one of which fetched the pixel.
	 */
	public function test_clean_content_prefers_a_fallback_over_a_stub_only_image() {
		$content = Lite_Site::clean_content(
			'<p><img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="'
			. ' data-opt-src="https://some-other-domain.test/real.jpg" alt="Flooded road">'
			. '<noscript><img src="https://some-other-domain.test/real.jpg"></noscript></p>'
		);

		$this->assertSame( 1, substr_count( $content, 'lite-image-placeholder' ), 'One photograph gets one button.' );
		$this->assertStringContainsString(
			'data-src="https://some-other-domain.test/real.jpg"',
			$content,
			'And it loads the real file.'
		);
		$this->assertStringNotContainsString( 'R0lGOD', $content, 'The stub is not offered at all.' );
		$this->assertStringContainsString(
			'data-alt="Flooded road"',
			$content,
			'The description was written on the stub, so it comes across with it.'
		);
	}

	/**
	 * An image with a real source beside its stub is not this case: the
	 * fallback there is a duplicate and still goes.
	 */
	public function test_clean_content_still_drops_a_duplicate_beside_a_readable_source() {
		$content = Lite_Site::clean_content(
			'<p><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="'
			. ' data-src="https://some-other-domain.test/real.jpg" alt="Flood">'
			. '<noscript><img src="https://some-other-domain.test/real.jpg" alt="Flood"></noscript></p>'
		);

		$this->assertSame( 1, substr_count( $content, 'lite-image-placeholder' ), 'Still one placeholder.' );
		$this->assertStringContainsString( 'data-src="https://some-other-domain.test/real.jpg"', $content, 'Loading the real file.' );
	}

	/**
	 * The URL parser trims every C0 control from both ends, where trim()'s own
	 * default list stops at \x0B. A form feed before the scheme would
	 * otherwise break the scheme test here and be put back by the browser.
	 */
	public function test_clean_content_rejects_a_scheme_behind_a_form_feed() {
		$svg     = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg"></svg>' );
		$content = Lite_Site::clean_content( '<img data-src="&#12;' . $svg . '" alt="A map">' );

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'The SVG is refused like any other.' );
		$this->assertStringNotContainsString( 'svg', $content, 'And does not reach the page.' );
	}

	/**
	 * A beacon that declares its size with !important, or without the leading
	 * zero, is still a beacon.
	 */
	public function test_clean_content_reads_a_beacon_size_written_with_important() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><img src="https://pixel.some-other-domain.test/b.gif" style="width:1px !important;height:1px !important;">'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'No button for a beacon.' );
		$this->assertStringNotContainsString( 'pixel.some-other-domain.test', $content, 'And its URL never reaches the page.' );
	}

	/**
	 * The same, written as .5px rather than 0.5px.
	 */
	public function test_clean_content_reads_a_beacon_size_without_a_leading_zero() {
		$content = Lite_Site::clean_content(
			'<p>Story.</p><img src="https://pixel.some-other-domain.test/c.gif" style="width:.5px;height:.5px;">'
		);

		$this->assertStringNotContainsString( 'lite-image-placeholder', $content, 'No button for a beacon.' );
		$this->assertStringNotContainsString( 'pixel.some-other-domain.test', $content, 'And its URL never reaches the page.' );
	}
}
