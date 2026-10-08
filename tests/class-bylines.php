<?php
/**
 * Test double for the Newspack plugin's Bylines class.
 *
 * The suite runs without the Newspack plugin, so this stub stands in for
 * \Newspack\Bylines to exercise the lite site's custom-byline integration.
 *
 * @package newspack-lite-site
 */

namespace Newspack;

/**
 * Minimal stand-in mirroring the get_custom_byline_html() contract.
 */
class Bylines {

	/**
	 * The byline HTML the stub returns; null mimics "no active custom byline".
	 *
	 * @var string|null
	 */
	public static $custom_byline_html = null;

	/**
	 * Mirror of \Newspack\Bylines::get_custom_byline_html().
	 *
	 * @param int|null $post_id The post ID (unused by the stub).
	 * @return string|null The configured byline HTML, or null.
	 */
	public static function get_custom_byline_html( $post_id = null ) {
		return self::$custom_byline_html;
	}
}
