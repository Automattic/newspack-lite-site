<?php
/**
 * Plugin Name: Newspack Lite Site
 * Description: A lightweight, text-only version of your WordPress site for fast access in low-bandwidth or high-traffic conditions.
 * Version: 0.1.0-alpha.1
 * Author: Automattic
 * Author URI: https://newspack.com/
 * Requires at least: 6.6
 * Requires PHP: 7.2
 * License: GPL3
 * Text Domain: newspack-lite-site
 * Domain Path: /languages/
 *
 * @package newspack-lite-site
 */

defined( 'ABSPATH' ) || exit;

// Define NEWSPACK_LITE_SITE_PLUGIN_DIR.
if ( ! defined( 'NEWSPACK_LITE_SITE_PLUGIN_DIR' ) ) {
	define( 'NEWSPACK_LITE_SITE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Define NEWSPACK_LITE_SITE_PLUGIN_FILE.
if ( ! defined( 'NEWSPACK_LITE_SITE_PLUGIN_FILE' ) ) {
	define( 'NEWSPACK_LITE_SITE_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/vendor/autoload.php';

Newspack_Lite_Site\Initializer::init();

// Load language files.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'newspack-lite-site', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);
