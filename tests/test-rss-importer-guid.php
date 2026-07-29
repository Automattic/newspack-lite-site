<?php
/**
 * Tests for the RSS importer's GUID derivation.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\RSS_Importer;

/**
 * Covers the three-tier fallback that gives every feed item a stable identity,
 * which is what stops the importer creating duplicate posts on each run.
 */
class Test_RSS_Importer_Guid extends Lite_Site_TestCase {

	/**
	 * Derive the GUID for a stubbed feed item.
	 *
	 * @param array $props Feed item properties.
	 * @return string The derived GUID.
	 */
	private function guid_for( array $props ) {
		return self::invoke_private(
			RSS_Importer::class,
			'get_item_guid',
			[ new Lite_Site_Test_Feed_Item( $props ) ]
		);
	}

	/**
	 * An explicit feed GUID is used verbatim.
	 */
	public function test_uses_feed_guid_when_present() {
		$guid = $this->guid_for(
			[
				'id'        => 'https://example.com/?p=123',
				'permalink' => 'https://example.com/a-post/',
				'title'     => 'A post',
			]
		);

		$this->assertSame( 'https://example.com/?p=123', $guid, 'The feed GUID should win outright.' );
	}

	/**
	 * Without a GUID the permalink is hashed.
	 */
	public function test_falls_back_to_permalink() {
		$permalink = 'https://example.com/a-post/';

		$guid = $this->guid_for(
			[
				'id'        => '',
				'permalink' => $permalink,
				'title'     => 'A post',
			]
		);

		$this->assertSame( 'nls_url:' . md5( $permalink ), $guid, 'The permalink should be hashed with the nls_url prefix.' );
	}

	/**
	 * With neither a GUID nor a permalink, title and date are hashed together.
	 */
	public function test_falls_back_to_title_and_date_hash() {
		$title = 'A post without identifiers';
		$date  = '2026-07-29T12:00:00+00:00';

		$guid = $this->guid_for(
			[
				'id'        => '',
				'permalink' => '',
				'title'     => $title,
				'date'      => $date,
			]
		);

		$this->assertSame( 'nls_hash:' . md5( $title . $date ), $guid, 'Title and date should be hashed with the nls_hash prefix.' );
	}

	/**
	 * An item with no usable fields still yields a non-empty GUID.
	 */
	public function test_returns_non_empty_guid_for_empty_item() {
		$guid = $this->guid_for( [] );

		$this->assertNotEmpty( $guid, 'Every item should get a GUID.' );
		$this->assertStringStartsWith( 'nls_hash:', $guid, 'The last resort should be the title and date hash.' );
	}

	/**
	 * Two items differing only by title hash differently.
	 */
	public function test_distinguishes_items_by_title() {
		$first  = $this->guid_for( [ 'title' => 'First' ] );
		$second = $this->guid_for( [ 'title' => 'Second' ] );

		$this->assertNotSame( $first, $second, 'Different titles should produce different GUIDs.' );
	}

	/**
	 * The same item yields the same GUID across runs, which is what makes
	 * duplicate detection work.
	 */
	public function test_guid_is_stable_across_calls() {
		$props = [
			'id'        => '',
			'permalink' => 'https://example.com/stable/',
		];

		$this->assertSame(
			$this->guid_for( $props ),
			$this->guid_for( $props ),
			'GUID derivation should be deterministic.'
		);
	}
}
