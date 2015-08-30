<?php
// Load the WP Tests.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_tests_dir ) {
	$wp_tests_dir = '/tmp/wordpress-tests-lib';
}
require_once $wp_tests_dir . '/includes/functions.php';

// Load the plugin manually.
tests_add_filter( 'muplugins_loaded', function() {
	require_once dirname( __FILE__ ) . '/../wp-to-diaspora.php';
} );

require_once $wp_tests_dir . '/includes/bootstrap.php';
