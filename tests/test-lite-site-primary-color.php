<?php
/**
 * Tests for the theme accent color lookup.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers how a block theme's palette resolves to the Lite Site primary color.
 */
class Test_Lite_Site_Primary_Color extends Lite_Site_TestCase {

	/**
	 * Palettes and the color each should resolve to.
	 *
	 * @return array
	 */
	public function palette_provider() {
		return [
			'accent found by slug, not position' => [
				[
					[
						'slug'  => 'base',
						'color' => '#ffffff',
					],
					[
						'slug'  => 'accent',
						'color' => '#003DA5',
					],
				],
				'#003DA5',
			],
			'no accent falls back to text color' => [
				[
					[
						'slug'  => 'base',
						'color' => '#FFFFFF',
					],
					[
						'slug'  => 'accent-1',
						'color' => '#FFEE58',
					],
				],
				'currentcolor',
			],
			'empty palette falls back'           => [ [], 'currentcolor' ],
		];
	}

	/**
	 * The accent is found by slug, so a palette that opens with the page
	 * background does not paint links white.
	 *
	 * @dataProvider palette_provider
	 *
	 * @param array  $palette  Theme palette.
	 * @param string $expected Resolved color.
	 */
	public function test_palette_accent_color( $palette, $expected ) {
		$this->assertSame( $expected, Lite_Site::get_palette_accent_color( $palette ) );
	}

	/**
	 * Without a usable color, the Live badge keeps its gray background
	 * instead of turning white behind its white text.
	 */
	public function test_live_badge_keeps_gray_without_color() {
		$this->assertSame( 'currentcolor', Lite_Site::get_primary_color(), 'The test theme should have no primary color.' );

		$output = $this->render_liveblog_styles();

		$this->assertStringNotContainsString( '.liveblog-status.enable', $output );
	}

	/**
	 * A set primary color fills the Live badge.
	 */
	public function test_live_badge_uses_primary_color() {
		self::set_settings( [ 'primary_color' => '#aa0000' ] );

		$output = $this->render_liveblog_styles();

		$this->assertMatchesRegularExpression( '/\.liveblog-status\.enable\s*\{\s*background: #aa0000;/', $output );
	}

	/**
	 * Render the liveblog styles template.
	 *
	 * @return string
	 */
	private function render_liveblog_styles() {
		ob_start();
		include dirname( __DIR__ ) . '/templates/liveblog-styles.php';
		return ob_get_clean();
	}
}
