<?php
/**
 * Newspack Lite Site plugin initialization.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

defined( 'ABSPATH' ) || exit;

/**
 * Class to handle the plugin initialization.
 */
class Initializer {

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		Lite_Site::init();
		Lite_Site_Settings::init();
		RSS_Importer::init();
	}
}
