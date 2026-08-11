<?php
/**
 * Tests for featured image detection on imported RSS items.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\RSS_Importer;

/**
 * Covers the priority chain in `get_featured_image_url()`:
 * media:thumbnail, then an image enclosure, then the first inline `<img>`.
 *
 * Image URLs use literal public addresses so the safety check never performs a
 * DNS lookup.
 */
class Test_RSS_Importer_Image extends Lite_Site_TestCase {

	/**
	 * A safe, routable image URL.
	 */
	const SAFE_IMAGE = 'http://93.184.216.34/image.jpg';

	/**
	 * A second safe image URL, used to tell priorities apart.
	 */
	const SAFE_THUMB = 'http://93.184.216.34/thumbnail.jpg';

	/**
	 * An image URL on a private address, which must never be used.
	 */
	const UNSAFE_IMAGE = 'http://192.168.1.10/image.jpg';

	/**
	 * Resolve the featured image URL for a stubbed feed item.
	 *
	 * @param array $props Feed item properties.
	 * @return string The detected image URL.
	 */
	private function image_for( array $props ) {
		return self::invoke_private(
			RSS_Importer::class,
			'get_featured_image_url',
			[ new Lite_Site_Test_Feed_Item( $props ) ]
		);
	}

	/**
	 * A media:thumbnail takes priority over the enclosure link.
	 */
	public function test_thumbnail_wins_over_enclosure_link() {
		$url = $this->image_for(
			[ 'enclosure' => new Lite_Site_Test_Enclosure( self::SAFE_THUMB, self::SAFE_IMAGE, 'image/jpeg' ) ]
		);

		$this->assertSame( self::SAFE_THUMB, $url, 'The thumbnail should be preferred.' );
	}

	/**
	 * An enclosure with an image MIME type is used when there is no thumbnail.
	 */
	public function test_uses_enclosure_link_with_image_mime_type() {
		$url = $this->image_for(
			[ 'enclosure' => new Lite_Site_Test_Enclosure( '', self::SAFE_IMAGE, 'image/png' ) ]
		);

		$this->assertSame( self::SAFE_IMAGE, $url, 'An image enclosure should be used.' );
	}

	/**
	 * A non-image enclosure is ignored.
	 */
	public function test_ignores_non_image_enclosure() {
		$url = $this->image_for(
			[ 'enclosure' => new Lite_Site_Test_Enclosure( '', 'http://93.184.216.34/podcast.mp3', 'audio/mpeg' ) ]
		);

		$this->assertSame( '', $url, 'An audio enclosure should not become a featured image.' );
	}

	/**
	 * A thumbnail on a private address is rejected.
	 */
	public function test_rejects_unsafe_thumbnail() {
		$url = $this->image_for(
			[ 'enclosure' => new Lite_Site_Test_Enclosure( self::UNSAFE_IMAGE, '', '' ) ]
		);

		$this->assertSame( '', $url, 'A thumbnail on a private address should be rejected.' );
	}

	/**
	 * An enclosure link on a private address is rejected.
	 */
	public function test_rejects_unsafe_enclosure_link() {
		$url = $this->image_for(
			[ 'enclosure' => new Lite_Site_Test_Enclosure( '', self::UNSAFE_IMAGE, 'image/jpeg' ) ]
		);

		$this->assertSame( '', $url, 'An enclosure on a private address should be rejected.' );
	}

	/**
	 * When the first enclosure has no image, later enclosures are checked.
	 */
	public function test_falls_back_to_later_enclosures() {
		$url = $this->image_for(
			[
				'enclosure'  => new Lite_Site_Test_Enclosure( '', '', '' ),
				'enclosures' => [
					new Lite_Site_Test_Enclosure( '', 'http://93.184.216.34/notes.pdf', 'application/pdf' ),
					new Lite_Site_Test_Enclosure( '', self::SAFE_IMAGE, 'image/jpeg' ),
				],
			]
		);

		$this->assertSame( self::SAFE_IMAGE, $url, 'A later image enclosure should be found.' );
	}

	/**
	 * With no usable enclosure, the first inline image is used.
	 */
	public function test_falls_back_to_first_inline_image() {
		$url = $this->image_for(
			[ 'content' => '<p>Intro</p><img src="http://93.184.216.34/inline.jpg" alt="Inline"><img src="http://93.184.216.34/second.jpg">' ]
		);

		$this->assertSame( 'http://93.184.216.34/inline.jpg', $url, 'The first inline image should win.' );
	}

	/**
	 * Inline images are returned without a safety check — callers are
	 * responsible for validating the URL before fetching it.
	 *
	 * `import_item()` does exactly that before sideloading.
	 */
	public function test_inline_image_is_not_safety_checked() {
		$url = $this->image_for(
			[ 'content' => '<img src="' . self::UNSAFE_IMAGE . '">' ]
		);

		$this->assertSame(
			self::UNSAFE_IMAGE,
			$url,
			'Inline image URLs are returned unvalidated; import_item() screens them.'
		);
	}

	/**
	 * An item with nothing to offer yields an empty string.
	 */
	public function test_returns_empty_string_when_no_image_found() {
		$this->assertSame( '', $this->image_for( [ 'content' => '<p>No pictures here.</p>' ] ), 'No image should be detected.' );
		$this->assertSame( '', $this->image_for( [] ), 'An empty item should yield no image.' );
	}
}
