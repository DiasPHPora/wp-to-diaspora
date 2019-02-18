<?php
/**
 * WP_To_Diaspora tests.
 *
 * @package WP_To_Diaspora\Tests\WP_To_Diaspora
 * @since   1.7.0
 */

/**
 * Main WP to diaspora test class.
 *
 * @since 1.7.0
 */
class Tests_WP2D_WP_To_Diaspora extends WP_UnitTestCase {

	/**
	 * Test for the static instance attribute.
	 *
	 * @since 1.7.0
	 */
	public function test_wp_to_diaspora_instance() {
		$this->assertClassHasStaticAttribute( '_instance', 'WP_To_Diaspora' );
	}

	/**
	 * Test for all defined constants.
	 *
	 * @since 1.7.0
	 */
	public function test_constants_defined() {
		$path = str_replace( '/tests', '', __DIR__ );
		$this->assertSame( WP2D_DIR, $path );
		$this->assertSame( WP2D_LIB_DIR, $path . '/lib' );
		$this->assertSame( WP2D_VENDOR_DIR, $path . '/vendor' );
	}

	/**
	 * Test for existence of included files.
	 *
	 * @since 1.7.0
	 */
	public function test_includes_exist() {
		$this->assertFileExists( WP2D_VENDOR_DIR . '/autoload.php' );
		$this->assertFileExists( WP2D_LIB_DIR . '/class-api.php' );
		$this->assertFileExists( WP2D_LIB_DIR . '/class-contextual-help.php' );
		$this->assertFileExists( WP2D_LIB_DIR . '/class-helpers.php' );
		$this->assertFileExists( WP2D_LIB_DIR . '/class-options.php' );
		$this->assertFileExists( WP2D_LIB_DIR . '/class-post.php' );
	}

	/**
	 * Test that all admin scripts and stylesheets exist.
	 *
	 * @since 1.7.0
	 */
	public function test_styles_scripts_exist() {
		$this->assertFileExists( WP2D_DIR . '/css/jquery.tagit.css' );
		$this->assertFileExists( WP2D_DIR . '/css/chosen.min.css' );
		$this->assertFileExists( WP2D_DIR . '/css/wp-to-diaspora.css' );
		$this->assertFileExists( WP2D_DIR . '/js/chosen.jquery.min.js' );
		$this->assertFileExists( WP2D_DIR . '/js/tag-it.min.js' );
		$this->assertFileExists( WP2D_DIR . '/js/wp-to-diaspora.js' );
	}

	/**
	 * Test for actions and filters which should be set up.
	 *
	 * @since 1.7.0
	 */
	public function test_setup() {
		$wp2d = WP_To_Diaspora::instance();
		$hook = plugin_basename( WP2D_DIR . '/wp-to-diaspora.php' );

		// Load languages.
		$this->assertNotEmpty( has_action( 'plugins_loaded', [ $wp2d, 'l10n' ] ) );

		// Add "Settings" link to plugin page.
		$this->assertNotEmpty( has_filter( 'plugin_action_links_' . $hook, [ $wp2d, 'settings_link' ] ) );

		// Perform any necessary data upgrades.
		$this->assertNotEmpty( has_action( 'admin_init', [ $wp2d, 'upgrade' ] ) );

		// Enqueue CSS and JS scripts.
		$this->assertNotEmpty( has_action( 'admin_enqueue_scripts', [ $wp2d, 'admin_load_scripts' ] ) );

		// Set up the options.
		$this->assertNotEmpty( has_action( 'init', [ 'WP2D_Options', 'instance' ] ) );

		// WP2D Post.
		$this->assertNotEmpty( has_action( 'init', [ 'WP2D_Post', 'setup' ] ) );

		// AJAX actions for loading aspects and services.
		$this->assertNotEmpty( has_action( 'wp_ajax_wp_to_diaspora_update_aspects_list', [ $wp2d, 'update_aspects_list_callback' ] ) );
		$this->assertNotEmpty( has_action( 'wp_ajax_wp_to_diaspora_update_services_list', [ $wp2d, 'update_services_list_callback' ] ) );

		// Check the pod connection status on the options page.
		$this->assertNotEmpty( has_action( 'wp_ajax_wp_to_diaspora_check_pod_connection_status', [ $wp2d, 'check_pod_connection_status_callback' ] ) );
	}

	/**
	 * Test fetching the correct aspects and services.
	 *
	 * @since 1.7.0
	 */
	public function test_update_aspects_services_list() {
		// Get the necessary instances.
		$wp2d    = WP_To_Diaspora::instance();
		$options = WP2D_Options::instance();
		$api     = wp2d_api_helper_get_fake_api_init_login();

		// Set our fake initialised API object.
		wp2d_helper_set_private_property( $wp2d, '_api', $api );

		add_filter( 'pre_http_request', 'wp_to_diaspora_pre_http_request_filter_update_aspects' );

		// Make sure the options start off empty.
		$this->assertEmpty( $options->get_option( 'aspects_list' ) );

		/**
		 * Make update calls for aspects.
		 */
		$res = [ 'public' => 'Public', 1 => 'Family' ];
		$this->assertEquals( $res, wp2d_helper_call_private_method( $wp2d, '_update_aspects_services_list', 'aspects' ) );
		$this->assertEquals( $res, $options->get_option( 'aspects_list' ) );

		$res = [ 'public' => 'Public', 2 => 'Friends' ];
		$this->assertEquals( $res, wp2d_helper_call_private_method( $wp2d, '_update_aspects_services_list', 'aspects' ) );
		$this->assertEquals( $res, $options->get_option( 'aspects_list' ) );

		// When an update fails (WP_Error or error code response), the previously set option remains unchanged.
		$this->assertFalse( wp2d_helper_call_private_method( $wp2d, '_update_aspects_services_list', 'aspects' ) );
		$this->assertEquals( $res, $options->get_option( 'aspects_list' ) );
		$this->assertEquals( 'Error loading aspects.', $api->get_last_error( true ) );
		$this->assertFalse( wp2d_helper_call_private_method( $wp2d, '_update_aspects_services_list', 'aspects' ) );
		$this->assertEquals( $res, $options->get_option( 'aspects_list' ) );
		$this->assertEquals( 'Error loading aspects.', $api->get_last_error( true ) );

		// When getting an empty return, only the public aspect should exist.
		$res = [ 'public' => 'Public' ];
		$this->assertEquals( $res, wp2d_helper_call_private_method( $wp2d, '_update_aspects_services_list', 'aspects' ) );
		$this->assertEquals( $res, $options->get_option( 'aspects_list' ) );

		/**
		 * Make update calls for services.
		 */
		$res = [ 'facebook' => 'Facebook' ];
		$this->assertEquals( $res, wp2d_helper_call_private_method( $wp2d, '_update_aspects_services_list', 'services' ) );
		$this->assertEquals( $res, $options->get_option( 'services_list' ) );

		$res = [ 'twitter' => 'Twitter' ];
		$this->assertEquals( $res, wp2d_helper_call_private_method( $wp2d, '_update_aspects_services_list', 'services' ) );
		$this->assertEquals( $res, $options->get_option( 'services_list' ) );

		// When an update fails (WP_Error or error code response), the previously set option remains unchanged.
		$this->assertFalse( wp2d_helper_call_private_method( $wp2d, '_update_aspects_services_list', 'services' ) );
		$this->assertEquals( $res, $options->get_option( 'services_list' ) );
		$this->assertEquals( 'Error loading services.', $api->get_last_error( true ) );
		$this->assertFalse( wp2d_helper_call_private_method( $wp2d, '_update_aspects_services_list', 'services' ) );
		$this->assertEquals( $res, $options->get_option( 'services_list' ) );
		$this->assertEquals( 'Error loading services.', $api->get_last_error( true ) );

		// When getting an empty return, we get an empty array.
		$res = [];
		$this->assertEquals( $res, wp2d_helper_call_private_method( $wp2d, '_update_aspects_services_list', 'services' ) );
		$this->assertEquals( $res, $options->get_option( 'services_list' ) );

		remove_filter( 'pre_http_request', 'wp_to_diaspora_pre_http_request_filter_update_aspects' );
	}

	/**
	 * Test the connection to the pod.
	 *
	 * @since 1.7.0
	 */
	public function test_check_pod_connection_status() {
		// Get the necessary instances.
		$wp2d    = WP_To_Diaspora::instance();
		$options = WP2D_Options::instance();
		$api     = wp2d_api_helper_get_fake_api_init_login();
		// Set our fake initialised API object.
		wp2d_helper_set_private_property( $wp2d, '_api', $api );

		// Pod hasn't been set up yet, so return is null.
		$this->assertNull( wp2d_helper_call_private_method( $wp2d, '_check_pod_connection_status' ) );

		// Set pseudo options to simulate a set up pod.
		$options->set_option( 'pod', 'pod1' );
		$options->set_option( 'username', 'encrypted-username' );
		$options->set_option( 'password', 'encrypted-password' );
		$options->save();

		// Fake init has no last_error, so it simulates a successful connection.
		$this->assertTrue( wp2d_helper_call_private_method( $wp2d, '_check_pod_connection_status' ) );

		// Simulate an error in the API object.
		wp2d_helper_set_private_property( $api, '_last_error', new WP_Error( 'wp_error_code', 'WP_Error message' ) );
		$this->assertFalse( wp2d_helper_call_private_method( $wp2d, '_check_pod_connection_status' ) );
	}
}
