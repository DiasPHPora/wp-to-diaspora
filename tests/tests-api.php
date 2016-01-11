<?php
/**
 * WP2D_API tests.
 *
 * @package WP_To_Diaspora\Tests\WP2D_API
 * @since next-release
 */

/**
 * Main API test class.
 *
 * @since next-release
 */
class Tests_WP2D_API extends WP_UnitTestCase {

	/**
	 * Test the constructor, to make sure that the correct class variables are set.
	 *
	 * @since next-release
	 */
	public function test_constructor() {
		$api = new WP2D_API( 'pod1' );
		$this->assertAttributeSame( true, '_is_secure', $api );
		$this->assertAttributeSame( 'pod1', '_pod', $api );

		$api = new WP2D_API( 'pod2', false );
		$this->assertAttributeSame( false, '_is_secure', $api );
		$this->assertAttributeSame( 'pod2', '_pod', $api );
	}

	/**
	 * Test getting a pod url in different formats.
	 *
	 * @since next-release
	 */
	public function test_get_pod_url() {
		// Default is HTTPS.
		$api = new WP2D_API( 'pod' );
		$this->assertEquals( 'https://pod', $api->get_pod_url() );
		$this->assertEquals( 'https://pod', $api->get_pod_url( '' ) );
		$this->assertEquals( 'https://pod', $api->get_pod_url( '/' ) );
		$this->assertEquals( 'https://pod/a', $api->get_pod_url( 'a' ) );
		$this->assertEquals( 'https://pod/a', $api->get_pod_url( '/a' ) );
		$this->assertEquals( 'https://pod/a', $api->get_pod_url( 'a/' ) );
		$this->assertEquals( 'https://pod/a', $api->get_pod_url( 'a//' ) );

		// Using HTTP.
		$api = new WP2D_API( 'pod', false );
		$this->assertEquals( 'http://pod', $api->get_pod_url() );
	}

	/**
	 * Test init when there is no valid token.
	 *
	 * @since next-release
	 */
	public function test_init_fail() {
		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_init_fail' );

		$api = new WP2D_API( 'pod' );

		// Directly check if the connection has been initialised.
		$this->assertFalse( wp2d_helper_call_private_method( $api, '_check_init' ) );
		$this->assertEquals(
			'Connection not initialised.',
			$api->get_last_error()
		);

		// False response, can't resolve host.
		$this->assertFalse( $api->init() );
		$this->assertContains(
			'Failed to initialise connection to pod "https://pod".',
			$api->get_last_error()
		);

		// Response has an invalid token.
		$this->assertFalse( $api->init() );
		$this->assertEquals(
			'Failed to initialise connection to pod "https://pod".',
			$api->get_last_error()
		);

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_init_fail' );
	}

	/**
	 * Test the successful initialisation and pod changes.
	 *
	 * @since next-release
	 */
	public function test_init_success() {
		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_init_success' );

		$api = new WP2D_API( 'pod1' );

		// First initialisation.
		$this->assertTrue( $api->init() );
		$this->assertAttributeSame( 'token-a', '_token', $api );
		// Only check for the cookie once, since it's always the same one.
		$this->assertAttributeSame( array( 'the_cookie' ), '_cookies', $api );

		// Reinitialise with same pod, token isn't reloaded.
		$this->assertTrue( $api->init( 'pod1' ) );
		$this->assertAttributeSame( 'token-a', '_token', $api );

		// Reinitialise with different pod.
		$this->assertTrue( $api->init( 'pod2' ) );
		$this->assertAttributeSame( 'token-b', '_token', $api );

		// Reinitialise with different protocol.
		$this->assertTrue( $api->init( 'pod2', false ) );
		$this->assertAttributeSame( 'token-c', '_token', $api );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_init_success' );
	}

	/**
	 * Test fetching and forcefully re-fetching the token.
	 *
	 * @since next-release
	 */
	public function test_fetch_token() {
		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_fetch_token' );

		$api = wp2d_api_helper_get_fake_api_init( 'pod', 'token-initial' );

		// Check the initial token.
		$this->assertEquals( 'token-initial', wp2d_helper_call_private_method( $api, '_fetch_token' ) );

		// Directly set a new token.
		wp2d_helper_set_private_property( $api, '_token', 'token-new' );
		$this->assertEquals( 'token-new', wp2d_helper_call_private_method( $api, '_fetch_token' ) );

		// Force fetch a new token.
		$this->assertEquals( 'token-forced', wp2d_helper_call_private_method( $api, '_fetch_token', true ) );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_fetch_token' );
	}

	/**
	 * Test the login checker.
	 *
	 * @since next-release
	 */
	public function test_check_login() {
		$api = new WP2D_API( 'pod' );
		// Try to check login before initialised.
		$this->assertFalse( $api->is_logged_in() );
		$this->assertFalse( wp2d_helper_call_private_method( $api, '_check_login' ) );
		$this->assertEquals( 'Connection not initialised.', $api->get_last_error() );

		$api = wp2d_api_helper_get_fake_api_init();

		$this->assertFalse( $api->is_logged_in() );
		$this->assertFalse( wp2d_helper_call_private_method( $api, '_check_login' ) );
		$this->assertEquals( 'Not logged in.', $api->get_last_error() );

		wp2d_helper_set_private_property( $api, '_is_logged_in', true );

		$this->assertTrue( $api->is_logged_in() );
		$this->assertTrue( wp2d_helper_call_private_method( $api, '_check_login' ) );
	}

	/**
	 * Test an invalid login.
	 *
	 * @since next-release
	 */
	public function test_login_fail() {
		$api = new WP2D_API( 'pod' );
		// Try login before initialised.
		$this->assertFalse( $api->login( 'username', 'password' ) );
		$this->assertEquals( 'Connection not initialised.', $api->get_last_error() );

		$api = wp2d_api_helper_get_fake_api_init();

		// Both username AND password are required!
		$this->assertFalse( $api->login( '', '' ) );
		$this->assertFalse( $api->is_logged_in() );

		$this->assertFalse( $api->login( 'username-only', '' ) );
		$this->assertFalse( $api->is_logged_in() );

		$this->assertFalse( $api->login( '', 'password-only' ) );
		$this->assertFalse( $api->is_logged_in() );

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_login_fail' );

		$this->assertFalse( $api->login( 'username-wrong', 'password-wrong' ) );
		$this->assertEquals( 'Login failed. Check your login details.', $api->get_last_error() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_login_fail' );
	}

	/**
	 * Test a successful login, re-login and forced re-login.
	 *
	 * @since next-release
	 */
	public function test_login_success() {
		$api = wp2d_api_helper_get_fake_api_init();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_login_success' );

		// First login.
		$this->assertTrue( $api->login( 'username', 'password' ) );
		$this->assertTrue( $api->is_logged_in() );

		// Trying to log in again with same credentials just returns true, without making a new sign in attempt.
		$this->assertTrue( $api->login( 'username', 'password' ) );
		$this->assertTrue( $api->is_logged_in() );

		// Force a new sign in.
		$this->assertTrue( $api->login( 'username', 'password', true ) );
		$this->assertTrue( $api->is_logged_in() );

		// Login with new credentials.
		$this->assertTrue( $api->login( 'username-new', 'password-new' ) );
		$this->assertTrue( $api->is_logged_in() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_login_success' );
	}

	/**
	 * Test _get_aspects_services method with an invalid argument.
	 *
	 * @since next-release
	 */
	public function test_get_aspects_services_invalid_argument() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_services_fail' );

		// Testing with WP_Error response (check filter).
		$this->assertFalse( wp2d_helper_call_private_method( $api, '_get_aspects_services', 'invalid-argument', array(), true ) );
		$this->assertEquals( 'Unknown error occurred.', $api->get_last_error() );

		// Testing invalid code response (check filter).
		$this->assertFalse( wp2d_helper_call_private_method( $api, '_get_aspects_services', 'invalid-argument', array(), true ) );
		$this->assertEquals( 'Unknown error occurred.', $api->get_last_error() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_services_fail' );
	}

	/**
	 * Test getting aspects when an error occurs.
	 *
	 * @since next-release
	 */
	public function test_get_aspects_fail() {
		// Test getting aspects when not logged in.
		$api = wp2d_api_helper_get_fake_api_init();
		$this->assertFalse( $api->get_aspects() );
		$this->assertEquals( 'Not logged in.', $api->get_last_error() );

		// Test getting aspects when logged in.
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_services_fail' );

		// Testing with WP_Error response.
		$this->assertFalse( $api->get_aspects() );
		$this->assertEquals( 'Error loading aspects.', $api->get_last_error() );

		// Testing invalid code response.
		$this->assertFalse( $api->get_aspects() );
		$this->assertEquals( 'Error loading aspects.', $api->get_last_error() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_services_fail' );
	}

	/**
	 * Test getting aspects successfully.
	 *
	 * @since next-release
	 */
	public function test_get_aspects_success() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_success' );

		// The aspects that should be returned.
		$aspects = array( 'public' => 'Public', 1 => 'Family' );
		$this->assertEquals( $aspects, $api->get_aspects() );
		$this->assertAttributeSame( $aspects, '_aspects', $api );

		// Fetching the aspects again should pass the same list without a new request.
		$this->assertEquals( $aspects, $api->get_aspects() );
		$this->assertAttributeSame( $aspects, '_aspects', $api );

		// Force a new fetch request.
		$aspects = array( 'public' => 'Public', 2 => 'Friends' );
		$this->assertEquals( $aspects, $api->get_aspects( true ) );
		$this->assertAttributeSame( $aspects, '_aspects', $api );

		// Make sure that there is always at least a Public aspect.
		$aspects = array( 'public' => 'Public' );
		$this->assertEquals( $aspects, $api->get_aspects( true ) );
		$this->assertAttributeSame( $aspects, '_aspects', $api );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_success' );
	}

	/**
	 * Test getting services when an error occurs.
	 *
	 * @since next-release
	 */
	public function test_get_services_fail() {
		// Test getting services when not logged in.
		$api = wp2d_api_helper_get_fake_api_init();
		$this->assertFalse( $api->get_services() );
		$this->assertEquals( 'Not logged in.', $api->get_last_error() );

		// Test getting services when logged in.
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_services_fail' );

		// Testing with WP_Error response.
		$this->assertFalse( $api->get_services() );
		$this->assertEquals( 'Error loading services.', $api->get_last_error() );

		// Testing invalid code response.
		$this->assertFalse( $api->get_services() );
		$this->assertEquals( 'Error loading services.', $api->get_last_error() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_services_fail' );
	}

	/**
	 * Test getting services successfully.
	 *
	 * @since next-release
	 */
	public function test_get_services_success() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_services_success' );

		// The services that should be returned.
		$services = array( 'facebook' => 'Facebook' );
		$this->assertEquals( $services, $api->get_services() );
		$this->assertAttributeSame( $services, '_services', $api );

		// Fetching the services again should pass the same list without a new request.
		$this->assertEquals( $services, $api->get_services() );
		$this->assertAttributeSame( $services, '_services', $api );

		// Force a new fetch request.
		$services = array( 'twitter' => 'Twitter' );
		$this->assertEquals( $services, $api->get_services( true ) );
		$this->assertAttributeSame( $services, '_services', $api );

		// If no services are connected, make sure we get an empty array.
		$this->assertEquals( array(), $api->get_services( true ) );
		$this->assertAttributeSame( array(), '_services', $api );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_services_success' );
	}

	/**
	 * Test posting when an error occurs.
	 *
	 * @since next-release
	 */
	public function test_post_fail() {
		// Test post when not logged in.
		$api = wp2d_api_helper_get_fake_api_init();
		$this->assertFalse( $api->post( 'text' ) );
		$this->assertEquals( 'Not logged in.', $api->get_last_error() );

		// Test post when logged in.
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_post_fail' );

		// Returning a WP_Error object.
		$this->assertFalse( $api->post( 'text' ) );
		$this->assertEquals( 'WP_Error message', $api->get_last_error() );

		// Returning an error code.
		$this->assertFalse( $api->post( 'text' ) );
		$this->assertEquals( 'Error code message', $api->get_last_error() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_post_fail' );
	}

	/**
	 * Test posting successfully.
	 *
	 * @since next-release
	 */
	public function test_post_success() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_post_success' );

		$post1 = $api->post( 'text' );
		$this->assertEquals( 1, $post1->id );
		$this->assertEquals( true, $post1->public );
		$this->assertEquals( 'guid1', $post1->guid );
		$this->assertEquals( 'text1', $post1->text );
		$this->assertEquals( 'https://pod/posts/guid1', $post1->permalink );

		$post2 = $api->post( 'text', '1' );
		$this->assertEquals( 2, $post2->id );
		$this->assertEquals( false, $post2->public );
		$this->assertEquals( 'guid2', $post2->guid );
		$this->assertEquals( 'text2', $post2->text );
		$this->assertEquals( 'https://pod/posts/guid2', $post2->permalink );

		$post3 = $api->post( 'text', array( '1' ) );
		$this->assertEquals( 3, $post3->id );
		$this->assertEquals( false, $post3->public );
		$this->assertEquals( 'guid3', $post3->guid );
		$this->assertEquals( 'text3', $post3->text );
		$this->assertEquals( 'https://pod/posts/guid3', $post3->permalink );

		// Need a test for the extra data parameter!

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_post_success' );
	}

	/**
	 * Test deleting posts and comments when an error occurs.
	 *
	 * @since next-release
	 */
	public function test_delete_fail() {
		// Test delete when not logged in.
		$api = wp2d_api_helper_get_fake_api_init();
		$this->assertFalse( $api->delete( 'post', 'anything' ) );
		$this->assertEquals( 'Not logged in.', $api->get_last_error() );

		// Test delete when logged in.
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_delete_fail' );

		// Getting a WP_Error response.
		$this->assertFalse( $api->delete( 'post', 'wp_error' ) );
		$this->assertEquals( 'WP_Error message', $api->get_last_error() );

		// Deleting something other than posts or comments.
		$this->assertFalse( $api->delete( 'internet', 'allofit' ) );
		$this->assertEquals( 'You can only delete posts and comments.', $api->get_last_error() );

		// Deleting posts.
		$this->assertFalse( $api->delete( 'post', 'invalid_id' ) );
		$this->assertEquals( 'The post you tried to delete does not exist.', $api->get_last_error() );

		$this->assertFalse( $api->delete( 'post', 'not_my_id' ) );
		$this->assertEquals( 'The post you tried to delete does not belong to you.', $api->get_last_error() );

		// Deleting comments.
		$this->assertFalse( $api->delete( 'comment', 'invalid_id' ) );
		$this->assertEquals( 'The comment you tried to delete does not exist.', $api->get_last_error() );

		$this->assertFalse( $api->delete( 'comment', 'not_my_id' ) );
		$this->assertEquals( 'The comment you tried to delete does not belong to you.', $api->get_last_error() );

		// Unknown error, due to an invalid response code.
		$this->assertFalse( $api->delete( 'post', 'anything' ) );
		$this->assertEquals( 'Unknown error occurred.', $api->get_last_error() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_delete_fail' );
	}

	/**
	 * Test deleting posts and comments successfully.
	 *
	 * @since next-release
	 */
	public function test_delete_success() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_delete_success' );

		// Delete post.
		$this->assertTrue( $api->delete( 'post', 'my_valid_id' ) );

		// Delete comment.
		$this->assertTrue( $api->delete( 'comment', 'my_valid_id' ) );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_delete_success' );
	}

	/**
	 * Test logging out.
	 *
	 * @since next-release
	 */
	public function test_logout() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		$api->logout();

		$this->assertAttributeSame( false,   '_is_logged_in', $api );
		$this->assertAttributeSame( null,    '_username',     $api );
		$this->assertAttributeSame( null,    '_password',     $api );
		$this->assertAttributeSame( array(), '_aspects',      $api );
		$this->assertAttributeSame( array(), '_services',     $api );
	}

	/**
	 * Test the deinitialisation.
	 *
	 * @since next-release
	 */
	public function test_deinit() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		$api->deinit();

		$this->assertFalse( $api->has_last_error() );
		$this->assertAttributeSame( null,    '_token',        $api );
		$this->assertAttributeSame( array(), '_cookies',      $api );
		$this->assertAttributeSame( null,    '_last_request', $api );
		$this->assertAttributeSame( false,   '_is_logged_in', $api );
		$this->assertAttributeSame( null,    '_username',     $api );
		$this->assertAttributeSame( null,    '_password',     $api );
		$this->assertAttributeSame( array(), '_aspects',      $api );
		$this->assertAttributeSame( array(), '_services',     $api );
	}
}
