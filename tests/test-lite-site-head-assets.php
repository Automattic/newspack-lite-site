<?php
/**
 * Tests for the font stylesheet and GA4 tag printed in lite page heads.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers print_font_stylesheet() and print_analytics_tag().
 */
class Test_Lite_Site_Head_Assets extends Lite_Site_TestCase {

	/**
	 * Start each test with fresh script and style registries.
	 */
	public function set_up() {
		parent::set_up();
		$GLOBALS['wp_scripts'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset so each test registers from scratch.
		$GLOBALS['wp_styles']  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset so each test registers from scratch.
	}

	/**
	 * Drop the registries the test filled.
	 */
	public function tear_down() {
		$GLOBALS['wp_scripts'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset so each test registers from scratch.
		$GLOBALS['wp_styles']  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset so each test registers from scratch.
		parent::tear_down();
	}

	/**
	 * The font stylesheet prints with the provider URL as set, with no version added.
	 */
	public function test_prints_font_stylesheet() {
		self::set_settings( [ 'font_import_url' => 'https://fonts.googleapis.com/css2?family=Inter&display=swap' ] );

		$output = get_echo( [ Lite_Site::class, 'print_font_stylesheet' ] );

		$this->assertStringContainsString( "id='newspack-lite-site-fonts-css'", $output );
		$this->assertStringContainsString( "href='https://fonts.googleapis.com/css2?family=Inter&#038;display=swap'", $output );
		$this->assertStringNotContainsString( 'ver=', $output );
	}

	/**
	 * Nothing prints without a font import URL.
	 */
	public function test_prints_no_font_stylesheet_when_unset() {
		$this->assertSame( '', get_echo( [ Lite_Site::class, 'print_font_stylesheet' ] ) );
	}

	/**
	 * The GA4 loader prints async, after the config that queues the Measurement ID.
	 */
	public function test_prints_analytics_tag() {
		self::set_settings( [ 'ga4_measurement_id' => 'G-TEST123' ] );

		$output = get_echo( [ Lite_Site::class, 'print_analytics_tag' ] );

		$config = strpos( $output, 'gtag(\'config\', "G-TEST123");' );
		$loader = strpos( $output, 'src="https://www.googletagmanager.com/gtag/js?id=G-TEST123"' );

		$this->assertNotFalse( $config );
		$this->assertNotFalse( $loader );
		$this->assertLessThan( $loader, $config );
		$this->assertMatchesRegularExpression( '/<script async[^>]+id="newspack-lite-site-gtag-js"/', $output );
	}

	/**
	 * A Measurement ID with quotes can't break out of the script or its URL.
	 */
	public function test_encodes_measurement_id() {
		self::set_settings( [ 'ga4_measurement_id' => 'G-1\'"<x' ] );

		$output = get_echo( [ Lite_Site::class, 'print_analytics_tag' ] );

		$this->assertStringContainsString( 'gtag/js?id=G-1%27%22%3Cx', $output );
		$this->assertStringContainsString( 'gtag(\'config\', "G-1\'\\"<x");', $output );
	}

	/**
	 * Nothing prints without a Measurement ID.
	 */
	public function test_prints_no_analytics_tag_when_unset() {
		$this->assertSame( '', get_echo( [ Lite_Site::class, 'print_analytics_tag' ] ) );
	}

	/**
	 * Lite pages print only their own assets, never what the site enqueued.
	 */
	public function test_prints_only_lite_assets() {
		self::set_settings(
			[
				'font_import_url'    => 'https://fonts.googleapis.com/css2?family=Inter',
				'ga4_measurement_id' => 'G-TEST123',
			]
		);
		wp_enqueue_script( 'site-script', 'https://example.test/site.js', [], '1.0', false );
		wp_enqueue_style( 'site-style', 'https://example.test/site.css', [], '1.0' );

		$output = get_echo( [ Lite_Site::class, 'print_font_stylesheet' ] ) . get_echo( [ Lite_Site::class, 'print_analytics_tag' ] );

		$this->assertStringNotContainsString( 'example.test', $output );
	}
}
