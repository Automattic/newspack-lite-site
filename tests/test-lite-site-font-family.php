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
	 * A name starting a token with a digit is quoted.
	 */
	public function test_name_with_digit_is_quoted() {
		$this->assertSame( '"Source Serif 4", sans-serif', $this->family_for( 'Source Serif 4' ) );
	}

	/**
	 * A single bare name is quoted and gets a generic fallback.
	 */
	public function test_single_name_gets_fallback() {
		$this->assertSame( '"Georgia", sans-serif', $this->family_for( 'Georgia' ) );
	}

	/**
	 * Already quoted names are not quoted twice, in either quote style.
	 */
	public function test_quoted_names_stay_valid() {
		$this->assertSame( '"Source Serif 4", serif', $this->family_for( '"Source Serif 4", serif' ) );
		$this->assertSame( '"Source Serif 4", serif', $this->family_for( "'Source Serif 4', serif" ) );
	}

	/**
	 * Generic families stay unquoted and suppress the fallback.
	 */
	public function test_generics_stay_unquoted() {
		$this->assertSame( '"Inter", sans-serif', $this->family_for( 'Inter, sans-serif' ) );
		$this->assertSame( 'system-ui, -apple-system, monospace', $this->family_for( 'system-ui, -apple-system, monospace' ) );
		$this->assertSame( 'SERIF', $this->family_for( 'SERIF' ) );
	}

	/**
	 * Comma lists are split, trimmed and quoted per name.
	 */
	public function test_comma_list() {
		$this->assertSame( '"Open Sans", "Noto Sans", sans-serif', $this->family_for( '  Open Sans ,Noto Sans,  ' ) );
	}

	/**
	 * Embedded quotes and backslashes are escaped.
	 */
	public function test_embedded_quote_and_backslash_are_escaped() {
		$this->assertSame( '"Foo\\"Bar", sans-serif', $this->family_for( 'Foo"Bar' ) );
		$this->assertSame( '"Foo\\\\Bar", sans-serif', $this->family_for( 'Foo\\Bar' ) );
	}

	/**
	 * Hostile input cannot end the declaration, the rule or the style element.
	 */
	public function test_hostile_input_cannot_break_out() {
		$value = $this->family_for( "Evil; color: red } </style><script>alert(1)</script> {\n" );

		foreach ( [ ';', '{', '}', '<', '>', "\n" ] as $char ) {
			$this->assertStringNotContainsString( $char, $value );
		}
		$this->assertSame( 1, preg_match( '/^"[^"]*", sans-serif$/', $value ) );
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

		$this->assertStringContainsString( 'font-family: "Source Serif 4", sans-serif;', $output );
		$this->assertStringNotContainsString( '&quot;', $output );
	}
}
