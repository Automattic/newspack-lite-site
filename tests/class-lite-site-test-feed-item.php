<?php
/**
 * Test double for a SimplePie feed item.
 *
 * `RSS_Importer::get_item_guid()` and `RSS_Importer::get_featured_image_url()`
 * accept an untyped `$item`, so a plain stub is used here rather than a mock of
 * `SimplePie_Item`. That keeps the suite working across the WordPress 6.7 move
 * from `SimplePie_Item` to the namespaced `SimplePie\Item`.
 *
 * @package newspack-lite-site
 */

/**
 * Stands in for a single feed item.
 */
class Lite_Site_Test_Feed_Item {

	/**
	 * Item properties, keyed by name.
	 *
	 * @var array
	 */
	private $props;

	/**
	 * Constructor.
	 *
	 * @param array $props Any of: id, permalink, title, date, content,
	 *                     enclosure, enclosures.
	 */
	public function __construct( array $props = [] ) {
		$this->props = $props;
	}

	/**
	 * Return the item GUID.
	 *
	 * @return string|null
	 */
	public function get_id() {
		return $this->props['id'] ?? null;
	}

	/**
	 * Return the item permalink.
	 *
	 * @return string|null
	 */
	public function get_permalink() {
		return $this->props['permalink'] ?? null;
	}

	/**
	 * Return the item title.
	 *
	 * @return string|null
	 */
	public function get_title() {
		return $this->props['title'] ?? null;
	}

	/**
	 * Return the item date.
	 *
	 * The stub ignores the requested format and returns a fixed string, which is
	 * all the code under test needs.
	 *
	 * @param string $format Date format requested by the caller.
	 * @return string|null
	 */
	public function get_date( $format = '' ) {
		return $this->props['date'] ?? null;
	}

	/**
	 * Return the item content.
	 *
	 * @return string|null
	 */
	public function get_content() {
		return $this->props['content'] ?? null;
	}

	/**
	 * Return the primary enclosure.
	 *
	 * @return Lite_Site_Test_Enclosure|null
	 */
	public function get_enclosure() {
		return $this->props['enclosure'] ?? null;
	}

	/**
	 * Return all enclosures.
	 *
	 * @return Lite_Site_Test_Enclosure[]
	 */
	public function get_enclosures() {
		return $this->props['enclosures'] ?? [];
	}
}
