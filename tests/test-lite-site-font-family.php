<?php
/**
 * Tests for the body font-family value printed in lite page styles.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers Lite_Site::get_font_family() and the styles template.
 */
class Test_Lite_Site_Font_Family extends Lite_Site_TestCase {

	/**
	 * Resolve the font family for a body font setting.
	 *
	 * @param string $font_body Setting value.
	 * @return string
	 */
	private function family_for( string $font_body ): string {
		self::set_settings( [ 'font_body' => $font_body ] );
		return Lite_Site::get_font_family();
	}

	/**
	 * Falls back to the system stack when unset.
	 */
	public function test_default_when_unset() {
		$this->assertSame( 'system-ui, -apple-system, sans-serif', Lite_Site::get_font_family() );
	}

	/**
	 * A single bare name is quoted, including one with a digit-leading token.
	 */
	public function test_single_name_is_quoted() {
		$this->assertSame( '"Source Serif 4"', $this->family_for( 'Source Serif 4' ) );
		$this->assertSame( '"Georgia"', $this->family_for( 'Georgia' ) );
	}

	/**
	 * Already quoted names are not quoted twice, in either quote style.
	 */
	public function test_quoted_names_stay_valid() {
		$this->assertSame( '"Source Serif 4", serif', $this->family_for( '"Source Serif 4", serif' ) );
		$this->assertSame( '"Source Serif 4", serif', $this->family_for( "'Source Serif 4', serif" ) );
	}

	/**
	 * A stray unmatched quote on either end of a name is dropped.
	 */
	public function test_unbalanced_quote_is_stripped() {
		$this->assertSame( '"Source Serif 4"', $this->family_for( '"Source Serif 4' ) );
		$this->assertSame( '"Source Serif 4"', $this->family_for( "Source Serif 4'" ) );
	}

	/**
	 * Control characters become a space instead of joining the words.
	 */
	public function test_control_characters_become_spaces() {
		$this->assertSame( '"Open Sans"', $this->family_for( "Open\tSans" ) );
		$this->assertSame( '"Open Sans"', $this->family_for( "Open\n \r\nSans" ) );
	}

	/**
	 * Generic families and vendor keywords stay unquoted and nothing is appended.
	 */
	public function test_generics_stay_unquoted() {
		$this->assertSame( '"Inter", sans-serif', $this->family_for( 'Inter, sans-serif' ) );
		$this->assertSame( 'system-ui, -apple-system, monospace', $this->family_for( 'system-ui, -apple-system, monospace' ) );
		$this->assertSame( '-apple-system', $this->family_for( '-apple-system' ) );
		$this->assertSame( 'SERIF', $this->family_for( 'SERIF' ) );
	}

	/**
	 * Comma lists are split, trimmed and quoted per name.
	 */
	public function test_comma_list() {
		$this->assertSame( '"Open Sans", "Noto Sans"', $this->family_for( '  Open Sans ,Noto Sans,  ' ) );
	}

	/**
	 * Embedded quotes and backslashes are escaped.
	 */
	public function test_embedded_quote_and_backslash_are_escaped() {
		$this->assertSame( '"Foo\\"Bar"', $this->family_for( 'Foo"Bar' ) );
		$this->assertSame( '"Foo\\\\Bar"', $this->family_for( 'Foo\\Bar' ) );
	}

	/**
	 * Hostile input cannot end the declaration, the rule or the style element.
	 */
	public function test_hostile_input_cannot_break_out() {
		$value = $this->family_for( "Evil; color: red } </style><script>alert(1)</script> {\n" );

		foreach ( [ ';', '{', '}', '<', '>', "\n" ] as $char ) {
			$this->assertStringNotContainsString( $char, $value );
		}
		$this->assertSame( 1, preg_match( '/^"[^"]*"$/', $value ) );
	}

	/**
	 * Input with nothing usable falls back to the default stack.
	 */
	public function test_unusable_input_uses_default() {
		$this->assertSame( 'system-ui, -apple-system, sans-serif', $this->family_for( ' ; , {} ' ) );
	}

	/**
	 * The template prints the quotes as characters, not entities.
	 */
	public function test_template_prints_unescaped_quotes() {
		self::set_settings( [ 'font_body' => 'Source Serif 4' ] );

		ob_start();
		include dirname( __DIR__ ) . '/templates/lite-site-styles.php';
		$output = ob_get_clean();

		$this->assertStringContainsString( 'font-family: "Source Serif 4";', $output );
		$this->assertStringNotContainsString( '&quot;', $output );
	}

	/**
	 * Theme font stacks and the system stack each maps to.
	 *
	 * @return array
	 */
	public function data_theme_font_stacks() {
		$sans  = 'system-ui, -apple-system, sans-serif';
		$serif = 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif';
		$mono  = 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace';

		return [
			'sans-serif web font' => [ '"Inter", system-ui, sans-serif', $sans ],
			'serif web font'      => [ '"EB Garamond", ui-serif, serif', $serif ],
			'monospace web font'  => [ '"Fira Code", monospace', $mono ],
			'unquoted sans-serif' => [ 'Manrope, sans-serif', $sans ],
			'no generic family'   => [ 'Cardo', $sans ],
			'empty'               => [ '', $sans ],
		];
	}

	/**
	 * A theme font becomes the system font of the same kind, so nothing is downloaded.
	 *
	 * @dataProvider data_theme_font_stacks
	 *
	 * @param string $theme_stack Theme font-family value.
	 * @param string $expected    System font stack.
	 */
	public function test_theme_font_maps_to_system_font_of_same_kind( $theme_stack, $expected ) {
		$this->assertSame( $expected, Lite_Site::get_system_font_stack( $theme_stack ) );
	}

	/**
	 * The body font follows the kind of the block theme's body font.
	 */
	public function test_body_font_follows_theme() {
		$this->use_block_theme();

		$this->assertSame( 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif', Lite_Site::get_font_family() );
	}

	/**
	 * Headings follow the theme's heading font when it differs in kind from the body.
	 */
	public function test_heading_font_follows_theme() {
		$this->use_block_theme();

		$this->assertSame( 'system-ui, -apple-system, sans-serif', Lite_Site::get_heading_font_family() );

		ob_start();
		include dirname( __DIR__ ) . '/templates/lite-site-styles.php';
		$output = ob_get_clean();
		$this->assertMatchesRegularExpression( '/h1, h2, h3, h4, h5, h6 \{\s*font-family: system-ui, -apple-system, sans-serif;/', $output );
	}

	/**
	 * A heading font that does not say whether it is serif or sans-serif follows the body.
	 */
	public function test_heading_font_without_kind_follows_body() {
		$this->filter_theme_json( 'wp_theme_json_data_theme', [ 'styles' => [ 'elements' => [ 'heading' => [ 'typography' => [ 'fontFamily' => 'Cardo' ] ] ] ] ] );
		$this->use_block_theme();

		$this->assertSame( '', Lite_Site::get_heading_font_family() );
	}

	/**
	 * A Body Font setting changes body text only; headings keep the theme's heading font.
	 */
	public function test_body_font_setting_leaves_theme_heading_font() {
		$this->use_block_theme();

		$this->assertSame( '"Cardo"', $this->family_for( 'Cardo' ) );
		$this->assertSame( 'system-ui, -apple-system, sans-serif', Lite_Site::get_heading_font_family() );
	}

	/**
	 * Headings follow the body when the chosen body font is the theme's heading font.
	 */
	public function test_heading_follows_body_when_body_font_is_the_heading_font() {
		$this->use_block_theme();

		$this->assertSame( '"Inter", system-ui, sans-serif', $this->family_for( 'Inter' ) );
		$this->assertSame( '', Lite_Site::get_heading_font_family() );
	}

	/**
	 * A classic theme keeps the default font and prints no heading font.
	 */
	public function test_classic_theme_keeps_default_fonts() {
		$this->assertSame( '', Lite_Site::get_heading_font_family() );
	}

	/**
	 * A block theme offers each font family it registers, once, by family name.
	 */
	public function test_theme_fonts_are_the_registered_families() {
		$this->use_block_theme();

		$this->assertSame( [ 'Newsreader', 'Cardo', 'Inter' ], wp_list_pluck( Lite_Site::get_theme_fonts(), 'family' ) );
	}

	/**
	 * A classic theme offers no theme fonts.
	 */
	public function test_classic_theme_has_no_theme_fonts() {
		$this->assertSame( [], Lite_Site::get_theme_fonts() );
	}

	/**
	 * A Body Font that is one of the theme's fonts uses the theme's own stack.
	 */
	public function test_registered_body_font_uses_theme_stack() {
		$this->use_block_theme();

		$this->assertSame( '"Inter", system-ui, sans-serif', $this->family_for( 'Inter' ) );
	}

	/**
	 * On a block theme, a Body Font the theme does not register counts as the system font,
	 * so the page matches what the settings screen shows.
	 */
	public function test_unregistered_body_font_falls_back_on_block_theme() {
		$this->use_block_theme();

		$this->assertSame( 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif', $this->family_for( 'Open Sans' ) );
	}

	/**
	 * Choosing a theme font prints that family's font faces, and only that family's.
	 */
	public function test_chosen_theme_font_prints_its_font_faces() {
		$this->use_block_theme();
		self::set_settings(
			[
				'font_body'       => 'Inter',
				'font_import_url' => 'https://fonts.googleapis.com/css2?family=Inter',
			]
		);

		$output = get_echo( [ Lite_Site::class, 'print_font_stylesheet' ] );

		$this->assertStringContainsString( '@font-face', $output );
		$this->assertStringContainsString( 'nls-block-theme/assets/fonts/inter.woff2', $output );
		$this->assertStringNotContainsString( 'fonts.googleapis.com', $output, 'The embed field is not used on a block theme.' );
	}

	/**
	 * The system font downloads nothing on a block theme.
	 */
	public function test_system_font_prints_no_font_faces() {
		$this->use_block_theme();

		$this->assertSame( '', get_echo( [ Lite_Site::class, 'print_font_stylesheet' ] ) );
	}

	/**
	 * A Heading Font that is one of the theme's fonts sets the headings.
	 */
	public function test_heading_font_setting_uses_theme_font() {
		$this->use_block_theme();
		self::set_settings( [ 'font_heading' => 'Cardo' ] );

		$this->assertSame( '"Cardo"', Lite_Site::get_heading_font_family() );
	}

	/**
	 * A Heading Font the theme does not register counts as the system font.
	 */
	public function test_unregistered_heading_font_falls_back() {
		$this->use_block_theme();
		self::set_settings( [ 'font_heading' => 'Georgia' ] );

		$this->assertSame( 'system-ui, -apple-system, sans-serif', Lite_Site::get_heading_font_family() );
	}

	/**
	 * Choosing a theme font for headings prints that family's font faces.
	 */
	public function test_chosen_heading_font_prints_its_font_faces() {
		$this->use_block_theme();
		self::set_settings( [ 'font_heading' => 'Inter' ] );

		$this->assertStringContainsString( 'nls-block-theme/assets/fonts/inter.woff2', get_echo( [ Lite_Site::class, 'print_font_stylesheet' ] ) );
	}

	/**
	 * The same font chosen for body and headings is printed once, and headings follow the body.
	 */
	public function test_same_body_and_heading_font_prints_once() {
		$this->use_block_theme();
		self::set_settings(
			[
				'font_body'    => 'Inter',
				'font_heading' => 'Inter',
			]
		);

		$this->assertSame( '', Lite_Site::get_heading_font_family() );
		$this->assertSame( 1, substr_count( get_echo( [ Lite_Site::class, 'print_font_stylesheet' ] ), 'inter.woff2' ) );
	}

	/**
	 * A classic theme ignores the Heading Font setting.
	 */
	public function test_classic_theme_ignores_heading_font() {
		self::set_settings( [ 'font_heading' => 'Georgia' ] );

		$this->assertSame( '', Lite_Site::get_heading_font_family() );
	}

	/**
	 * Only the chosen family's font faces print, not every font the theme registers.
	 */
	public function test_only_the_chosen_family_prints() {
		$this->use_block_theme();
		self::set_settings( [ 'font_body' => 'Inter' ] );

		$this->assertStringNotContainsString( 'newsreader.woff2', get_echo( [ Lite_Site::class, 'print_font_stylesheet' ] ) );
	}

	/**
	 * A font installed through the Site Editor's font library can be chosen and loads from its own address.
	 */
	public function test_font_library_font_can_be_chosen() {
		$this->filter_theme_json(
			'wp_theme_json_data_user',
			[
				'settings' => [
					'typography' => [
						'fontFamilies' => [
							[
								'slug'       => 'library-font',
								'name'       => 'Library Font',
								'fontFamily' => '"Library Font", serif',
								'fontFace'   => [
									[
										'fontFamily' => 'Library Font',
										'fontStyle'  => 'normal',
										'fontWeight' => '400',
										'src'        => [ 'https://example.test/wp-content/uploads/fonts/library.woff2' ],
									],
								],
							],
						],
					],
				],
			]
		);
		$this->use_block_theme();
		self::set_settings( [ 'font_body' => 'Library Font' ] );

		$this->assertContains( 'Library Font', wp_list_pluck( Lite_Site::get_theme_fonts(), 'family' ) );
		$this->assertStringContainsString( 'https://example.test/wp-content/uploads/fonts/library.woff2', get_echo( [ Lite_Site::class, 'print_font_stylesheet' ] ) );
	}

	/**
	 * With no theme heading font, System font for headings follows the style of the theme's body font,
	 * not a web font chosen for the body.
	 */
	public function test_system_heading_font_without_theme_heading_font_matches_theme_body() {
		$this->filter_theme_json( 'wp_theme_json_data_theme', [ 'styles' => [ 'elements' => [ 'heading' => [ 'typography' => [ 'fontFamily' => '' ] ] ] ] ] );
		$this->use_block_theme();
		self::set_settings( [ 'font_body' => 'Inter' ] );

		$this->assertSame( 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif', Lite_Site::get_heading_font_family() );
	}
}
