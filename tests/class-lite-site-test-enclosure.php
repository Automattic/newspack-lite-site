<?php
/**
 * Test double for a SimplePie enclosure.
 *
 * @package newspack-lite-site
 */

/**
 * Stands in for a single enclosure or media:content element.
 */
class Lite_Site_Test_Enclosure {

	/**
	 * Thumbnail URL, as returned by media:thumbnail.
	 *
	 * @var string
	 */
	private $thumbnail;

	/**
	 * Enclosure URL.
	 *
	 * @var string
	 */
	private $link;

	/**
	 * Enclosure MIME type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Constructor.
	 *
	 * @param string $thumbnail Thumbnail URL.
	 * @param string $link      Enclosure URL.
	 * @param string $type      Enclosure MIME type.
	 */
	public function __construct( $thumbnail = '', $link = '', $type = '' ) {
		$this->thumbnail = $thumbnail;
		$this->link      = $link;
		$this->type      = $type;
	}

	/**
	 * Return the thumbnail URL.
	 *
	 * @return string
	 */
	public function get_thumbnail() {
		return $this->thumbnail;
	}

	/**
	 * Return the enclosure URL.
	 *
	 * @return string
	 */
	public function get_link() {
		return $this->link;
	}

	/**
	 * Return the enclosure MIME type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}
}
