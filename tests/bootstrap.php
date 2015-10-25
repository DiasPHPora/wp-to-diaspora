<?php
/**
 * Bootstrap to set up test environment.
 *
 * @package WP_To_Diaspora\Tests
 * @since 1.6.0
 */

// Include helpers.
require_once __DIR__ . '/helpers/general.php';
require_once __DIR__ . '/helpers/http-request-filters.php';

// Load the WP Tests.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_tests_dir ) {
	$wp_tests_dir = '/tmp/wordpress-tests-lib';
}
require_once $wp_tests_dir . '/includes/functions.php';

// Load the plugin manually.
tests_add_filter( 'muplugins_loaded', function() {
	require_once __DIR__ . '/../wp-to-diaspora.php';
} );

require_once $wp_tests_dir . '/includes/bootstrap.php';
