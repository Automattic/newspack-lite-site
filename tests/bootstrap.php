<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package newspack-lite-site
 */

// Load the Composer autoloader.
$nls_autoload = __DIR__ . '/../vendor/autoload.php';
if ( ! file_exists( $nls_autoload ) ) {
	fwrite( STDERR, "Composer autoloader not found. Run `composer install` before running the test suite.\n" ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
	exit( 1 );
}
require_once $nls_autoload;

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/newspack-lite-site.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Shared base class and test doubles for the plugin's test cases.
require_once __DIR__ . '/class-lite-site-testcase.php';
require_once __DIR__ . '/class-lite-site-test-enclosure.php';
require_once __DIR__ . '/class-lite-site-test-feed-item.php';
require_once __DIR__ . '/class-lite-site-test-bylines.php';
