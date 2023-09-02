<?php
/**
 * Bootstrap to set up test environment.
 *
 * @package WP_To_Diaspora\Tests
 * @since   1.6.0
 */

// Required for a composer installation of PHPUnit when using PhpStorm:
// https://www.drupal.org/node/2597814
defined( 'PHPUNIT_COMPOSER_INSTALL' ) || define( 'PHPUNIT_COMPOSER_INSTALL', __DIR__ . '/../vendor/autoload.php' );

/**
 * Determine where the WP test suite lives.
 *
 * Support for:
 * 1. `WP_DEVELOP_DIR` environment variable, which points to a checkout
 *   of the develop.svn.wordpress.org repository (this is recommended)
 * 2. `WP_TESTS_DIR` environment variable, which points to a checkout
 * 3. `WP_ROOT_DIR` environment variable, which points to a checkout
 * 4. Plugin installed inside of WordPress.org developer checkout
 * 5. Tests checked out to /tmp
 */
if ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$test_root = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_ROOT_DIR' ) ) {
	$test_root = getenv( 'WP_ROOT_DIR' ) . '/tests/phpunit';
} elseif ( file_exists( '../../../../tests/phpunit/includes/bootstrap.php' ) ) {
	$test_root = '../../../../tests/phpunit';
} elseif ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
	$test_root = '/tmp/wordpress-tests-lib';
}

require_once $test_root . '/includes/functions.php';

// Load the plugin manually.
tests_add_filter( 'muplugins_loaded', static function () {
	require_once __DIR__ . '/../wp-to-diaspora.php';
} );

require_once $test_root . '/includes/bootstrap.php';

require_once __DIR__ . '/helpers/wp2d-unit-test-case.php';
