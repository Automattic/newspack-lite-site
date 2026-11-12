<?php
/**
 * Tests for the RSS Entries admin page redirect and REST additions.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site_Settings;
use Newspack_Lite_Site\Post_Type;

/**
 * Covers the legacy list redirect target and the REST feed filter and fields.
 */
class Test_RSS_Entries_Admin extends Lite_Site_TestCase {

	/**
	 * The redirect targets the React page.
	 */
	public function test_redirect_target_points_to_page() {
		$target = Lite_Site_Settings::get_entries_redirect_target( [] );
		$this->assertSame( admin_url( 'admin.php?page=newspack-lite-site-rss-entries' ), $target );
	}

	/**
	 * Only the supported args are forwarded, encoded, and empty ones are dropped.
	 */
	public function test_redirect_target_forwards_supported_args() {
		$target = Lite_Site_Settings::get_entries_redirect_target(
			[
				'post_status' => 'draft',
				's'           => 'a&b=c',
				'author'      => '',
				'orderby'     => 'title',
			]
		);
		$this->assertStringContainsString( 'post_status=draft', $target );
		$this->assertStringContainsString( 's=a%26b%3Dc', $target );
		$this->assertStringNotContainsString( 'author=', $target );
		$this->assertStringNotContainsString( 'orderby', $target );
	}

	/**
	 * The feed collection param filters entries by feed URL.
	 */
	public function test_feed_param_filters_by_feed_url() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$a = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		$b = self::factory()->post->create( [ 'post_type' => Post_Type::POST_TYPE ] );
		update_post_meta( $a, '_rss_import_feed_url', 'https://a.example/feed' );
		update_post_meta( $b, '_rss_import_feed_url', 'https://b.example/feed' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/' . Post_Type::POST_TYPE );
		$request->set_param( 'feed', 'https://a.example/feed' );
		$data = rest_get_server()->dispatch( $request )->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( $a, $data[0]['id'] );
		$this->assertSame( 'https://a.example/feed', $data[0]['feed_url'] );
	}
}
