<?php
/**
 * Tests for the RSS importer's SSRF defences and outbound request limits.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\RSS_Importer;

/**
 * Exercises `block_ssrf_request()` — the public seam over the private
 * `is_safe_url()` — and the request argument capping applied during imports.
 *
 * Every URL here is chosen so no DNS lookup is required: literal IPv4
 * addresses resolve to themselves, so the suite never touches the network.
 */
class Test_RSS_Importer_Security extends Lite_Site_TestCase {

	/**
	 * A routable public address, used for the cases that should be allowed.
	 */
	const PUBLIC_URL = 'http://93.184.216.34/feed.xml';

	/**
	 * URLs that must never be fetched, with the reason each is rejected.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function unsafe_url_provider() {
		return [
			'loopback'               => [ 'http://127.0.0.1/feed.xml' ],
			'private 10.0.0.0/8'     => [ 'http://10.0.0.5/feed.xml' ],
			'private 172.16.0.0/12'  => [ 'http://172.16.0.1/feed.xml' ],
			'private 192.168.0.0/16' => [ 'http://192.168.1.1/feed.xml' ],
			'link-local metadata'    => [ 'http://169.254.169.254/latest/meta-data/' ],
			'IPv6 loopback'          => [ 'http://[::1]/feed.xml' ],
			'unspecified 0.0.0.0'    => [ 'http://0.0.0.0/feed.xml' ],
			'non-HTTP scheme'        => [ 'ftp://93.184.216.34/feed.xml' ],
			'malformed'              => [ 'not a url' ],
			'empty'                  => [ '' ],
		];
	}

	/**
	 * Unsafe URLs are short-circuited with an error before any request is made.
	 *
	 * @dataProvider unsafe_url_provider
	 *
	 * @param string $url The URL under test.
	 */
	public function test_blocks_unsafe_urls( $url ) {
		$result = RSS_Importer::block_ssrf_request( false, [], $url );

		$this->assertInstanceOf( WP_Error::class, $result, sprintf( '%s should be blocked.', $url ) );
		$this->assertSame( 'ssrf_blocked', $result->get_error_code(), 'The SSRF error code should be returned.' );
	}

	/**
	 * The cloud metadata endpoint is rejected.
	 *
	 * `is_safe_url()` gates on `wp_http_validate_url()` and then on `filter_var()`
	 * with `FILTER_FLAG_NO_RES_RANGE`, so either layer is enough to reject this
	 * address. Which one catches it depends on the core version — WordPress 7.0.3
	 * added `169.254.0.0/16` to core's own list — so this asserts the outcome
	 * rather than the layer.
	 */
	public function test_blocks_link_local_metadata_endpoint() {
		$this->assertInstanceOf(
			WP_Error::class,
			RSS_Importer::block_ssrf_request( false, [], 'http://169.254.169.254/latest/meta-data/' ),
			'The cloud metadata endpoint should be blocked.'
		);
	}

	/**
	 * A publicly routable address is allowed through untouched.
	 */
	public function test_allows_public_url() {
		$this->assertFalse(
			RSS_Importer::block_ssrf_request( false, [], self::PUBLIC_URL ),
			'A public URL should not short-circuit the request.'
		);
	}

	/**
	 * An earlier filter's short-circuit value survives the safety check.
	 */
	public function test_preserves_existing_preempt_value() {
		$preempt = [ 'response' => [ 'code' => 200 ] ];

		$this->assertSame(
			$preempt,
			RSS_Importer::block_ssrf_request( $preempt, [], self::PUBLIC_URL ),
			'A preempted response should be passed through unchanged.'
		);
	}

	/**
	 * A hostname that does not resolve is rejected.
	 *
	 * Skipped when the resolver hijacks NXDOMAIN, which would otherwise hand
	 * back a routable address and make this assertion meaningless.
	 */
	public function test_blocks_unresolvable_host() {
		$host = 'newspack-lite-site-nonexistent.invalid';

		if ( gethostbyname( $host ) !== $host ) {
			$this->markTestSkipped( 'The local resolver hijacks NXDOMAIN responses.' );
		}

		$this->assertInstanceOf(
			WP_Error::class,
			RSS_Importer::block_ssrf_request( false, [], 'http://' . $host . '/feed.xml' ),
			'An unresolvable host should be blocked.'
		);
	}

	/**
	 * Redirects are capped at two hops during an import.
	 */
	public function test_caps_redirection() {
		$capped = RSS_Importer::cap_import_request_args( [ 'redirection' => 10 ], self::PUBLIC_URL );
		$this->assertSame( 2, $capped['redirection'], 'Redirects should be capped at two.' );

		$under = RSS_Importer::cap_import_request_args( [ 'redirection' => 1 ], self::PUBLIC_URL );
		$this->assertSame( 1, $under['redirection'], 'A lower redirect count should be left alone.' );
	}

	/**
	 * Responses are capped at 3MB during an import.
	 */
	public function test_caps_response_size() {
		$capped = RSS_Importer::cap_import_request_args( [ 'redirection' => 5 ], self::PUBLIC_URL );

		$this->assertSame( 3 * 1024 * 1024, $capped['limit_response_size'], 'Responses should be capped at 3MB.' );
	}
}
