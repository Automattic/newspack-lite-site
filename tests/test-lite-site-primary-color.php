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
	 * The accent is found by slug, not by position, so a palette that opens
	 * with the page background does not paint links white.
	 */
	public function test_picks_accent_slug_not_first_entry() {
		$palettes = [
			'theme' => [
				[
					'slug'  => 'base',
					'color' => '#ffffff',
				],
				[
					'slug'  => 'accent',
					'color' => '#003DA5',
				],
			],
		];

		$this->assertSame( '#003DA5', Lite_Site::get_palette_accent_color( $palettes ) );
	}

	/**
	 * A custom palette's accent wins over the theme's.
	 */
	public function test_custom_accent_wins_over_theme() {
		$palettes = [
			'custom' => [
				[
					'slug'  => 'accent',
					'color' => '#aa0000',
				],
			],
			'theme'  => [
				[
					'slug'  => 'accent',
					'color' => '#003DA5',
				],
			],
		];

		$this->assertSame( '#aa0000', Lite_Site::get_palette_accent_color( $palettes ) );
	}

	/**
	 * A theme accent is still found when the custom palette has no accent.
	 */
	public function test_falls_through_custom_without_accent() {
		$palettes = [
			'custom' => [
				[
					'slug'  => 'custom-1',
					'color' => '#123456',
				],
			],
			'theme'  => [
				[
					'slug'  => 'accent',
					'color' => '#003DA5',
				],
			],
		];

		$this->assertSame( '#003DA5', Lite_Site::get_palette_accent_color( $palettes ) );
	}

	/**
	 * Without an accent, links follow the text color.
	 */
	public function test_no_accent_uses_currentcolor() {
		$palettes = [
			'theme' => [
				[
					'slug'  => 'base',
					'color' => '#FFFFFF',
				],
				[
					'slug'  => 'contrast',
					'color' => '#111111',
				],
			],
		];

		$this->assertSame( 'currentcolor', Lite_Site::get_palette_accent_color( $palettes ) );
		$this->assertSame( 'currentcolor', Lite_Site::get_palette_accent_color( [] ) );
	}
}
