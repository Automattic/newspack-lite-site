<?php
/**
 * Tests for the colors Lite Site takes from the theme.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers colors read from a block theme's global styles, and the settings that override them.
 */
class Test_Lite_Site_Primary_Color extends Lite_Site_TestCase {

	/**
	 * Links follow the theme's link style, not a palette color picked by position or name.
	 */
	public function test_link_color_comes_from_theme_link_style() {
		$this->use_block_theme();

		$this->assertSame( '#2255aa', Lite_Site::get_primary_color() );
	}

	/**
	 * The page background and text follow the theme, in both preset reference forms.
	 */
	public function test_page_colors_come_from_theme() {
		$this->use_block_theme();

		$this->assertSame( '#111111', Lite_Site::get_background_color() );
		$this->assertSame( '#eeeeee', Lite_Site::get_text_color() );

		$output = $this->render_template( 'lite-site-styles.php' );
		$this->assertMatchesRegularExpression( '/body\s*\{[^}]*background: #111111;[^}]*color: #eeeeee;/', $output );
	}

	/**
	 * The Live badge pairs the theme's link color with its page background,
	 * so a light link color on a dark theme stays readable.
	 */
	public function test_live_badge_text_uses_theme_background() {
		$this->use_block_theme();

		$this->assertMatchesRegularExpression( '/\.liveblog-status\.enable\s*\{\s*background: #2255aa;\s*color: #111111;/', $this->render_template( 'liveblog-styles.php' ) );
	}

	/**
	 * On a block theme, colors come from the theme's styles, so a saved Primary Color is ignored.
	 */
	public function test_primary_color_setting_ignored_on_block_theme() {
		$this->use_block_theme();
		self::set_settings( [ 'primary_color' => '#aa0000' ] );

		$this->assertSame( '#2255aa', Lite_Site::get_primary_color() );
	}

	/**
	 * A theme whose links use the text color leaves the Live badge gray.
	 */
	public function test_current_color_link_keeps_badge_gray() {
		$this->filter_theme_json( 'wp_theme_json_data_theme', [ 'styles' => [ 'elements' => [ 'link' => [ 'color' => [ 'text' => 'currentColor' ] ] ] ] ] );
		$this->use_block_theme();

		$this->assertSame( 'currentcolor', Lite_Site::get_primary_color() );
		$this->assertStringNotContainsString( '.liveblog-status.enable', $this->render_template( 'liveblog-styles.php' ) );
	}

	/**
	 * A reference Lite Site cannot resolve counts as unset, because lite pages
	 * load no theme CSS to define it.
	 */
	public function test_unresolved_reference_falls_back() {
		$this->filter_theme_json( 'wp_theme_json_data_theme', [ 'styles' => [ 'elements' => [ 'link' => [ 'color' => [ 'text' => 'var(--wp--custom--link)' ] ] ] ] ] );
		$this->use_block_theme();

		$this->assertSame( 'currentcolor', Lite_Site::get_primary_color() );
	}

	/**
	 * A palette color changed in the Site Editor wins over the theme's own value.
	 */
	public function test_site_editor_palette_change_wins() {
		$this->filter_theme_json(
			'wp_theme_json_data_user',
			[
				'settings' => [
					'color' => [
						'palette' => [
							[
								'slug'  => 'link',
								'color' => '#00aa00',
								'name'  => 'Link',
							],
						],
					],
				],
			]
		);
		$this->use_block_theme();

		$this->assertSame( '#00aa00', Lite_Site::get_primary_color() );
	}

	/**
	 * The image load button follows the page text color; buttons do not inherit it.
	 */
	public function test_load_image_button_follows_text_color() {
		$this->assertMatchesRegularExpression( '/\.lite-image-load-btn\s*\{[^}]*color: inherit;/', $this->render_template( 'lite-site-styles.php' ) );
	}

	/**
	 * Values that could end the declaration or the style element are refused.
	 *
	 * @return array
	 */
	public function data_css_values() {
		return [
			'hex kept'                  => [ '#ffc857', '#ffc857' ],
			'color-mix kept'            => [ 'color-mix(in oklab, red 50%, blue)', 'color-mix(in oklab, red 50%, blue)' ],
			'currentColor normalized'   => [ 'currentColor', 'currentcolor' ],
			'declaration end refused'   => [ 'red; background: url(x)', '' ],
			'rule end refused'          => [ 'red } body {', '' ],
			'style element end refused' => [ 'red</style>', '' ],
			'non-string refused'        => [ [ 'red' ], '' ],
		];
	}

	/**
	 * Theme values are only printed when they are safe inside a style element.
	 *
	 * @dataProvider data_css_values
	 *
	 * @param mixed  $value    Raw theme value.
	 * @param string $expected Printable value.
	 */
	public function test_css_value_refuses_unsafe_values( $value, $expected ) {
		$this->assertSame( $expected, Lite_Site::get_css_value( $value ) );
	}

	/**
	 * A classic theme sets no page colors, so the page looks as it always has.
	 */
	public function test_classic_theme_prints_no_page_colors() {
		$this->assertSame( '', Lite_Site::get_background_color() );
		$this->assertSame( '', Lite_Site::get_text_color() );
		$this->assertDoesNotMatchRegularExpression( '/body\s*\{[^}]*background:/', $this->render_template( 'lite-site-styles.php' ) );
	}

	/**
	 * Without a usable color, the Live badge keeps its gray background
	 * instead of turning white behind its white text.
	 */
	public function test_live_badge_keeps_gray_without_color() {
		$this->assertSame( 'currentcolor', Lite_Site::get_primary_color(), 'The test theme should have no primary color.' );

		$this->assertStringNotContainsString( '.liveblog-status.enable', $this->render_template( 'liveblog-styles.php' ) );
	}

	/**
	 * A set primary color fills the Live badge.
	 */
	public function test_live_badge_uses_primary_color() {
		self::set_settings( [ 'primary_color' => '#aa0000' ] );

		$this->assertMatchesRegularExpression( '/\.liveblog-status\.enable\s*\{\s*background: #aa0000;/', $this->render_template( 'liveblog-styles.php' ) );
	}

	/**
	 * Render a style template.
	 *
	 * @param string $template Template file name.
	 * @return string
	 */
	private function render_template( $template ) {
		ob_start();
		include dirname( __DIR__ ) . '/templates/' . $template;
		return ob_get_clean();
	}
}
