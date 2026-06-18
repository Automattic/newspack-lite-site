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

		register_activation_hook( NEWSPACK_LITE_SITE_PLUGIN_FILE, [ __CLASS__, 'activation_hook' ] );
	}

	/**
	 * Runs on plugin activation.
	 */
	public static function activation_hook() {
		Lite_Site::register_rewrite_rules();
		flush_rewrite_rules(); // phpcs:ignore
	}
}
