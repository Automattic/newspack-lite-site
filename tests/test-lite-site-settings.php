<?php
/**
 * Tests for settings sanitization and rewrite-flush scheduling.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site_Settings;

/**
 * Covers `sanitize_settings()`, the private font URL helper it delegates to,
 * the category tree builder, and the option hooks that schedule a rewrite flush.
 */
class Test_Lite_Site_Settings extends Lite_Site_TestCase {

	/**
	 * The transient used to defer a rewrite rule flush.
	 */
	const FLUSH_TRANSIENT = 'nls_flush_rewrite_rules';

	/**
	 * Clear the deferred flush flag between tests.
	 */
	public function set_up() {
		parent::set_up();
		delete_transient( self::FLUSH_TRANSIENT );
	}

	/**
	 * Sanitize a partial settings payload.
	 *
	 * @param array $settings Raw settings.
	 * @return array Sanitized settings.
	 */
	private function sanitize( array $settings ) {
		return Lite_Site_Settings::sanitize_settings( $settings );
	}

	/**
	 * The enabled flag is coerced to a boolean.
	 */
	public function test_sanitizes_enabled_flag() {
		$this->assertTrue( $this->sanitize( [ 'enabled' => true ] )['enabled'], 'A truthy value should enable the lite site.' );
		$this->assertTrue( $this->sanitize( [ 'enabled' => '1' ] )['enabled'], 'A truthy string should enable the lite site.' );
		$this->assertFalse( $this->sanitize( [ 'enabled' => '0' ] )['enabled'], 'A falsy string should disable the lite site.' );
		$this->assertFalse( $this->sanitize( [] )['enabled'], 'The lite site should default to disabled.' );
	}

	/**
	 * The URL base is slugified.
	 */
	public function test_sanitizes_url_base() {
		$this->assertSame( 'my-lite-site', $this->sanitize( [ 'url_base' => 'My Lite Site' ] )['url_base'], 'The URL base should be slugified.' );
		$this->assertSame( '', $this->sanitize( [] )['url_base'], 'A missing URL base should be empty.' );
	}

	/**
	 * Posts per page is clamped to a sane range.
	 */
	public function test_clamps_posts_per_page() {
		$this->assertSame( 1, $this->sanitize( [ 'posts_per_page' => 0 ] )['posts_per_page'], 'Zero should clamp up to one.' );
		$this->assertSame( 1, $this->sanitize( [ 'posts_per_page' => -20 ] )['posts_per_page'], 'A negative value should clamp up to one.' );
		$this->assertSame( 100, $this->sanitize( [ 'posts_per_page' => 5000 ] )['posts_per_page'], 'Large values should clamp to 100.' ); // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Asserting the oversized value is clamped; no query is run.
		$this->assertSame( 25, $this->sanitize( [ 'posts_per_page' => '25' ] )['posts_per_page'], 'A numeric string should be cast to int.' );
	}

	/**
	 * Posts per page falls back to the WordPress Reading setting.
	 */
	public function test_posts_per_page_falls_back_to_reading_setting() {
		update_option( 'posts_per_page', 7 );

		$this->assertSame( 7, $this->sanitize( [] )['posts_per_page'], 'The Reading setting should be the fallback.' );
	}

	/**
	 * Selecting "All categories" clears the category filter.
	 */
	public function test_all_categories_selection_clears_filter() {
		$this->assertSame( [], $this->sanitize( [ 'categories' => [ '' ] ] )['categories'], 'The empty option means all categories.' );
		$this->assertSame( [], $this->sanitize( [ 'categories' => [ '', '3' ] ] )['categories'], 'All categories should win over specific ones.' );
	}

	/**
	 * Term selections are cast to integers.
	 */
	public function test_casts_term_ids_to_integers() {
		$sanitized = $this->sanitize(
			[
				'categories'          => [ '3', '4' ],
				'tags'                => [ '7' ],
				'excluded_categories' => [ '8' ],
				'excluded_tags'       => [ '9' ],
			]
		);

		$this->assertSame( [ 3, 4 ], $sanitized['categories'], 'Category IDs should be integers.' );
		$this->assertSame( [ 7 ], $sanitized['tags'], 'Tag IDs should be integers.' );
		$this->assertSame( [ 8 ], $sanitized['excluded_categories'], 'Excluded category IDs should be integers.' );
		$this->assertSame( [ 9 ], $sanitized['excluded_tags'], 'Excluded tag IDs should be integers.' );
	}

	/**
	 * Booleans that default to on stay on unless explicitly turned off.
	 */
	public function test_opt_out_booleans_default_to_true() {
		$defaults = $this->sanitize( [] );
		$this->assertTrue( $defaults['include_subcategories'], 'Subcategories should be included by default.' );
		$this->assertTrue( $defaults['external_links_new_tab'], 'External links should open in a new tab by default.' );

		$explicit = $this->sanitize(
			[
				'include_subcategories'  => false,
				'external_links_new_tab' => false,
			]
		);
		$this->assertFalse( $explicit['include_subcategories'], 'Subcategories can be excluded.' );
		$this->assertFalse( $explicit['external_links_new_tab'], 'External links can open in the same tab.' );
	}

	/**
	 * Footer HTML is run through the post kses allowlist.
	 */
	public function test_strips_unsafe_footer_html() {
		$footer = $this->sanitize( [ 'footer_html' => '  <p>Contact us</p><script>alert(1)</script>  ' ] )['footer_html'];

		$this->assertStringContainsString( '<p>Contact us</p>', $footer, 'Safe markup should survive.' );
		$this->assertStringNotContainsString( '<script>', $footer, 'Script tags should be stripped.' );
		$this->assertStringStartsWith( '<p>', $footer, 'Surrounding whitespace should be trimmed.' );
	}

	/**
	 * Only valid hex colours are stored.
	 */
	public function test_sanitizes_primary_color() {
		$this->assertSame( '#ff0000', $this->sanitize( [ 'primary_color' => '#ff0000' ] )['primary_color'], 'A valid hex colour should be kept.' );
		$this->assertSame( '', $this->sanitize( [ 'primary_color' => 'rgb(255,0,0)' ] )['primary_color'], 'A non-hex colour should be discarded.' );
		$this->assertSame( '', $this->sanitize( [] )['primary_color'], 'A missing colour should be empty.' );
	}

	/**
	 * A font import URL is stored as a bare URL.
	 */
	public function test_sanitizes_font_import_url() {
		$url = 'https://fonts.googleapis.com/css2?family=Inter';

		$this->assertSame( $url, $this->sanitize( [ 'font_import_url' => $url ] )['font_import_url'], 'A bare URL should be kept.' );
		$this->assertSame( '', $this->sanitize( [ 'font_import_url' => '   ' ] )['font_import_url'], 'Whitespace should yield an empty value.' );
		$this->assertSame( '', $this->sanitize( [] )['font_import_url'], 'A missing URL should be empty.' );
	}

	/**
	 * Pasting a whole `<link>` tag stores just its href.
	 */
	public function test_extracts_font_url_from_link_tag() {
		$url = 'https://fonts.googleapis.com/css2?family=Inter';
		$tag = '<link rel="stylesheet" href="' . $url . '">';

		$this->assertSame( $url, $this->sanitize( [ 'font_import_url' => $tag ] )['font_import_url'], 'The href should be extracted from the tag.' );
	}

	/**
	 * Every key the front end reads is always present in the stored settings.
	 */
	public function test_returns_a_complete_settings_shape() {
		$sanitized = $this->sanitize( [] );

		foreach (
			[
				'enabled',
				'url_base',
				'posts_per_page',
				'categories',
				'include_subcategories',
				'tags',
				'excluded_categories',
				'excluded_tags',
				'footer_html',
				'custom_css',
				'external_links_new_tab',
				'ga4_measurement_id',
				'primary_color',
				'font_import_url',
				'font_body',
			] as $key
		) {
			$this->assertArrayHasKey( $key, $sanitized, sprintf( 'The %s key should always be present.', $key ) );
		}
	}

	/**
	 * Changing the URL base schedules a rewrite rule flush.
	 */
	public function test_url_base_change_schedules_flush() {
		Lite_Site_Settings::handle_updated_option(
			Lite_Site_Settings::OPTION_NAME,
			[ 'url_base' => 'lite' ],
			[ 'url_base' => 'basic' ]
		);

		$this->assertNotFalse( get_transient( self::FLUSH_TRANSIENT ), 'A URL base change should schedule a flush.' );
	}

	/**
	 * Toggling the lite site schedules a rewrite rule flush.
	 */
	public function test_enabled_change_schedules_flush() {
		Lite_Site_Settings::handle_updated_option(
			Lite_Site_Settings::OPTION_NAME,
			[ 'enabled' => false ],
			[ 'enabled' => true ]
		);

		$this->assertNotFalse( get_transient( self::FLUSH_TRANSIENT ), 'Enabling the lite site should schedule a flush.' );
	}

	/**
	 * Unrelated settings changes do not schedule a flush.
	 */
	public function test_unrelated_change_does_not_schedule_flush() {
		Lite_Site_Settings::handle_updated_option(
			Lite_Site_Settings::OPTION_NAME,
			[
				'url_base' => 'lite',
				'enabled'  => true,
			],
			[
				'url_base'      => 'lite',
				'enabled'       => true,
				'primary_color' => '#ff0000',
			]
		);

		$this->assertFalse( get_transient( self::FLUSH_TRANSIENT ), 'A cosmetic change should not schedule a flush.' );
	}

	/**
	 * Other plugins' options are ignored.
	 */
	public function test_ignores_other_options() {
		Lite_Site_Settings::handle_updated_option( 'some_other_option', [ 'url_base' => 'a' ], [ 'url_base' => 'b' ] );
		$this->assertFalse( get_transient( self::FLUSH_TRANSIENT ), 'Another option should not schedule a flush.' );

		Lite_Site_Settings::handle_added_option( 'some_other_option', [ 'url_base' => 'a' ] );
		$this->assertFalse( get_transient( self::FLUSH_TRANSIENT ), 'Another option being added should not schedule a flush.' );
	}

	/**
	 * The first save schedules a flush, since `updated_option` does not fire.
	 */
	public function test_first_save_schedules_flush() {
		Lite_Site_Settings::handle_added_option( Lite_Site_Settings::OPTION_NAME, [ 'url_base' => 'lite' ] );

		$this->assertNotFalse( get_transient( self::FLUSH_TRANSIENT ), 'Creating the option should schedule a flush.' );
	}

	/**
	 * Categories come back in tree order with a depth on each term.
	 */
	public function test_builds_ordered_category_tree() {
		$parent     = self::factory()->category->create( [ 'name' => 'AAA Parent' ] );
		$child_a    = self::factory()->category->create(
			[
				'name'   => 'BBB Child A',
				'parent' => $parent,
			]
		);
		$grandchild = self::factory()->category->create(
			[
				'name'   => 'CCC Grandchild',
				'parent' => $child_a,
			]
		);
		$child_b    = self::factory()->category->create(
			[
				'name'   => 'DDD Child B',
				'parent' => $parent,
			]
		);

		$all = get_terms(
			[
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'include'    => [ $parent, $child_a, $grandchild, $child_b ],
			]
		);

		$ordered = self::invoke_private( Lite_Site_Settings::class, 'build_ordered_categories', [ $all, 0, 0 ] );

		$this->assertSame(
			[ $parent, $child_a, $grandchild, $child_b ],
			array_map( fn( $term ) => $term->term_id, $ordered ),
			'Each parent should be immediately followed by its descendants.'
		);
		$this->assertSame(
			[ 0, 1, 2, 1 ],
			array_map( fn( $term ) => $term->depth, $ordered ),
			'Depth should reflect nesting level.'
		);
	}

	/**
	 * A branch with no matching parent yields nothing.
	 */
	public function test_ordered_category_tree_is_empty_without_matches() {
		$ordered = self::invoke_private( Lite_Site_Settings::class, 'build_ordered_categories', [ [], 0, 0 ] );

		$this->assertSame( [], $ordered, 'An empty term list should produce an empty tree.' );
	}
}
