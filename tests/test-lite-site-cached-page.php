<?php
/**
 * Tests for what the lite single page cache stores.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site;

/**
 * Covers what cache_single_page() stores in the page cache.
 */
class Test_Lite_Site_Cached_Page extends Lite_Site_TestCase {

	/**
	 * Page cache key for the story's lite URL.
	 *
	 * @var string
	 */
	private $cache_key;

	/**
	 * The request globals from before the test, put back after it.
	 *
	 * @var array
	 */
	private $request_globals;

	/**
	 * A published story with blocks that render differently for signed-in
	 * readers, requested at its lite URL by a plain GET request. Its last
	 * group stays empty unless a test renders into it from the request.
	 */
	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );

		// phpcs:ignore WordPress.Security.NonceVerification, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- Saved to restore after the test.
		$this->request_globals = [ $_SERVER, $_GET, $_POST, $_REQUEST, $_COOKIE ];
		$this->make_request( 'GET', '/lite/a-story' );

		self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_name'    => 'a-story',
				'post_content' => '<!-- wp:paragraph --><p>Story copy.</p><!-- /wp:paragraph -->'
					. '<!-- wp:group {"className":"members-only"} --><div class="wp-block-group members-only"><!-- wp:paragraph --><p>Members-only copy.</p><!-- /wp:paragraph --></div><!-- /wp:group -->'
					. '<!-- wp:group {"className":"signed-out-only"} --><div class="wp-block-group signed-out-only"><!-- wp:paragraph --><p>Subscribe prompt.</p><!-- /wp:paragraph --></div><!-- /wp:group -->'
					. '<!-- wp:group {"className":"from-request"} --><div class="wp-block-group from-request"></div><!-- /wp:group -->',
			]
		);

		set_query_var( 'lite_path', 'a-story' );
		$this->cache_key = Lite_Site::get_page_cache_key( '/lite/a-story' );

		// The single template exits when its post doesn't resolve, which would
		// end the whole run with a passing status, so fail here instead.
		$this->assertNotNull( Lite_Site::resolve_post( 'a-story' ), 'The story resolves at its lite path.' );

		add_filter( 'render_block', [ __CLASS__, 'render_block_per_reader' ], 10, 2 );
	}

	/**
	 * Put back the request globals the test changed.
	 */
	public function tear_down() {
		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- Restores the globals saved in set_up().
		list( $_SERVER, $_GET, $_POST, $_REQUEST, $_COOKIE ) = $this->request_globals;
		parent::tear_down();
	}

	/**
	 * Set the request globals as PHP and WordPress fill them for a request.
	 *
	 * @param string $method  Request method.
	 * @param string $url     URL requested, with any query string. A path alone is requested at the site's address.
	 * @param array  $fields  Form fields in the request body.
	 * @param array  $cookies Cookies the request carries.
	 */
	private function make_request( $method, $url, array $fields = [], array $cookies = [] ) {
		$url   = wp_parse_url( $url );
		$query = $url['query'] ?? '';
		$host  = isset( $url['host'] ) ? $url : wp_parse_url( home_url() );

		$_SERVER['REQUEST_METHOD'] = $method;
		$_SERVER['HTTP_HOST']      = $host['host'] . ( isset( $host['port'] ) ? ':' . $host['port'] : '' );
		$_SERVER['REQUEST_URI']    = $url['path'] . ( '' !== $query ? '?' . $query : '' );
		$_SERVER['QUERY_STRING']   = $query;

		parse_str( $query, $params );
		$_GET     = $params;
		$_POST    = $fields;
		$_REQUEST = array_merge( $params, $fields ); // As wp_magic_quotes() rebuilds it.
		$_COOKIE  = $cookies; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
	}

	/**
	 * Render the story's from-request group with a callback, standing in for
	 * a block whose output depends on the request.
	 *
	 * @param callable $render Returns the group's markup for the current request.
	 */
	private function render_from_request( callable $render ) {
		add_filter(
			'render_block',
			function ( $block_content, $block ) use ( $render ) {
				return 'from-request' === ( $block['attrs']['className'] ?? '' ) ? $render() : $block_content;
			},
			10,
			2
		);
	}

	/**
	 * Stands in for Newspack's block visibility rules, which decide each
	 * block for the current user: one group shows only to signed-in readers,
	 * the other only to signed-out ones.
	 *
	 * @param string $block_content Rendered block.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public static function render_block_per_reader( $block_content, $block ) {
		switch ( $block['attrs']['className'] ?? '' ) {
			case 'members-only':
				return is_user_logged_in() ? $block_content : '';
			case 'signed-out-only':
				return is_user_logged_in() ? '' : $block_content;
			default:
				return $block_content;
		}
	}

	/**
	 * A signed-in reader fills the cache with the page a signed-out visitor
	 * gets, so every later reader is served the same copy whoever came first.
	 */
	public function test_cached_page_is_the_same_whoever_fills_it() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		Lite_Site::cache_single_page( $this->cache_key );
		$filled_by_member = get_transient( $this->cache_key );

		wp_set_current_user( 0 );
		Lite_Site::cache_single_page( $this->cache_key );
		$filled_by_visitor = get_transient( $this->cache_key );

		$this->assertStringContainsString( 'Subscribe prompt.', $filled_by_visitor, 'A signed-out visitor gets the blocks shown to signed-out readers.' );
		$this->assertStringNotContainsString( 'Members-only copy.', $filled_by_visitor, 'A signed-out visitor does not get the blocks shown only to members.' );
		$this->assertSame( $filled_by_visitor, $filled_by_member, 'A member fills the cache with the page a signed-out visitor gets.' );
	}

	/**
	 * The rest of the request runs for the reader who made it, not as the
	 * signed-out reader the page was rendered for.
	 */
	public function test_restores_the_signed_in_reader_after_rendering() {
		$member_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $member_id );

		Lite_Site::cache_single_page( $this->cache_key );

		$this->assertSame( $member_id, get_current_user_id(), 'The member is the current user again.' );
	}

	/**
	 * A caller that recovers from a failed render carries on as the reader
	 * who made the request.
	 */
	public function test_restores_the_signed_in_reader_when_rendering_fails() {
		$member_id    = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$render_error = new RuntimeException( 'The render failed.' );
		add_action(
			'newspack_lite_site_single_after_footer',
			function () use ( $render_error ) {
				throw $render_error;
			}
		);
		wp_set_current_user( $member_id );

		$caught_error = null;
		try {
			Lite_Site::cache_single_page( $this->cache_key );
		} catch ( RuntimeException $error ) {
			$caught_error = $error;
		}

		$this->assertSame( $render_error, $caught_error, 'The render error reaches the caller.' );
		$this->assertSame( $member_id, get_current_user_id(), 'The member is the current user again.' );
	}

	/**
	 * A request that sends text a block prints, in its query string, posted
	 * fields or host, is served that page but doesn't fill the cache: the
	 * cache key leaves all of them out, so readers who never sent the text
	 * would get it too.
	 *
	 * @dataProvider data_requests_sending_text
	 *
	 * @param string $method    Request method.
	 * @param string $url       URL requested.
	 * @param array  $fields    Form fields in the request body.
	 * @param string $sent_text What the request sent, as the visitor's page shows it.
	 */
	public function test_request_sending_text_a_block_prints_does_not_fill_the_cache( $method, $url, $fields, $sent_text ) {
		// Stands in for a block that prints a parameter and one that links
		// to the URL as requested.
		$this->render_from_request(
			function () {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$note = isset( $_REQUEST['note'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['note'] ) ) : '';
				$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
				$path = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
				return '<p>' . esc_html( $note ) . '</p><p><a href="' . esc_url( 'http://' . $host . $path ) . '">Back</a></p>';
			}
		);
		$this->make_request( $method, $url, $fields );

		$page = Lite_Site::cache_single_page( $this->cache_key );

		$this->assertStringContainsString( $sent_text, $page, 'The visitor is served the page their request asked for.' );
		$this->assertFalse( get_transient( $this->cache_key ), 'The page is not stored for later readers.' );
	}

	/**
	 * Requests sending text that blocks on the page can print.
	 *
	 * @return array[]
	 */
	public function data_requests_sending_text() {
		return [
			'query string'                    => [ 'GET', '/lite/a-story?note=A+note+from+the+visitor.', [], 'A note from the visitor.' ],
			'query string with no parameters' => [ 'GET', '/lite/a-story?=A+note+from+the+visitor.', [], '?=A+note+from+the+visitor.' ],
			'form post'                       => [ 'POST', '/lite/a-story', [ 'note' => 'A note from the visitor.' ], 'A note from the visitor.' ],
			'another host'                    => [ 'GET', 'http://another-host.example.test/lite/a-story', [], 'another-host.example.test' ],
		];
	}

	/**
	 * A request carrying a cookie that keeps full-page caches from storing
	 * its page is served the page rendered for it, but doesn't fill the
	 * cache: a block may have shown that visitor something other readers
	 * shouldn't get.
	 *
	 * @dataProvider data_cache_bypass_cookies
	 *
	 * @param string $cookie Cookie name.
	 */
	public function test_request_with_a_cache_bypass_cookie_does_not_fill_the_cache( $cookie ) {
		$this->render_from_request(
			function () use ( $cookie ) {
				return isset( $_COOKIE[ $cookie ] ) ? '<p>Shown to visitors with the cookie.</p>' : '';
			}
		);
		$this->make_request( 'GET', '/lite/a-story', [], [ $cookie => '1' ] );

		$page = Lite_Site::cache_single_page( $this->cache_key );

		$this->assertStringContainsString( 'Shown to visitors with the cookie.', $page, 'The visitor is served the page rendered for them.' );
		$this->assertFalse( get_transient( $this->cache_key ), 'The page is not stored for later readers.' );
	}

	/**
	 * One cookie for each name prefix Batcache treats as marking a visitor
	 * whose pages can differ.
	 *
	 * @return array[]
	 */
	public function data_cache_bypass_cookies() {
		return [
			'session'        => [ 'wordpress_logged_in_0123456789abcdef' ],
			'access bypass'  => [ 'wp_access_bypass' ],
			'comment author' => [ 'comment_author_0123456789abcdef' ],
		];
	}

	/**
	 * A page with no cache key, as for a request WordPress routed no path
	 * for, is served but not stored: every such request would share the
	 * empty key.
	 */
	public function test_page_without_a_cache_key_is_served_but_not_stored() {
		$page = Lite_Site::cache_single_page( '' );

		$this->assertStringContainsString( 'Story copy.', $page, 'The visitor is served the page.' );
		$this->assertFalse( get_transient( '' ), 'Nothing is stored under the empty key.' );
	}

	/**
	 * A request a full-page cache would store fills the cache, even when
	 * it's a HEAD or carries cookies that don't mark a visitor whose pages
	 * can differ, so ordinary visits keep the cache warm.
	 *
	 * @dataProvider data_requests_full_page_caches_store
	 *
	 * @param string $method  Request method.
	 * @param array  $cookies Cookies the request carries.
	 */
	public function test_request_a_full_page_cache_would_store_fills_the_cache( $method, $cookies ) {
		$this->make_request( $method, '/lite/a-story', [], $cookies );

		$page = Lite_Site::cache_single_page( $this->cache_key );

		$this->assertSame( $page, get_transient( $this->cache_key ), 'The page is stored for later readers.' );
	}

	/**
	 * Requests that only differ from a plain GET in ways that can't shape
	 * the page.
	 *
	 * @return array[]
	 */
	public function data_requests_full_page_caches_store() {
		return [
			'HEAD'              => [ 'HEAD', [] ],
			'login test cookie' => [ 'GET', [ 'wordpress_test_cookie' => 'WP Cookie check' ] ],
			'analytics cookie'  => [ 'GET', [ '_ga' => 'GA1.1.123.456' ] ],
		];
	}

	/**
	 * A query string the server's rewrite adds, with none in the URL the
	 * visitor requested, fills the cache: it carries nothing the path
	 * doesn't.
	 */
	public function test_query_string_a_server_rewrite_adds_fills_the_cache() {
		$this->make_request( 'GET', '/lite/a-story' );
		$rewrite_params          = [ 'q' => '/lite/a-story' ];
		$_SERVER['QUERY_STRING'] = 'q=/lite/a-story&';
		$_GET                    = $rewrite_params;
		$_REQUEST                = $rewrite_params;

		$page = Lite_Site::cache_single_page( $this->cache_key );

		$this->assertSame( $page, get_transient( $this->cache_key ), 'The page is stored for later readers.' );
	}

	/**
	 * A browser's request to the site's address fills the cache however the
	 * address is written: browsers send the host in lowercase, with the port
	 * only when it isn't the scheme's default.
	 *
	 * @dataProvider data_site_addresses_browsers_send_differently
	 *
	 * @param string $site_address The site address as stored.
	 * @param string $url          URL a browser requests for the story.
	 */
	public function test_request_to_the_site_address_fills_the_cache( $site_address, $url ) {
		add_filter(
			'pre_option_home',
			function () use ( $site_address ) {
				return $site_address;
			}
		);
		// The single template exits when its post doesn't resolve, which would
		// end the whole run with a passing status, so fail here instead.
		$this->assertNotNull( Lite_Site::resolve_post( 'a-story' ), 'The story resolves at the site address.' );
		$this->make_request( 'GET', $url );

		$page = Lite_Site::cache_single_page( $this->cache_key );

		$this->assertSame( $page, get_transient( $this->cache_key ), 'The page is stored for later readers.' );
	}

	/**
	 * Site addresses a browser's Host header doesn't spell the same way.
	 *
	 * @return array[]
	 */
	public function data_site_addresses_browsers_send_differently() {
		return [
			'capitals and a port'      => [ 'http://Lite-Site.example.test:8080', 'http://lite-site.example.test:8080/lite/a-story' ],
			'default port spelled out' => [ 'https://lite-site.example.test:443', 'https://lite-site.example.test/lite/a-story' ],
		];
	}
}
