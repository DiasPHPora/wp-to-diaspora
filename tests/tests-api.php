<?php
/**
 * WP2D_API tests.
 *
 * @package WP_To_Diaspora\Tests\WP2D_API
 * @since   1.7.0
 */

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Main API test class.
 *
 * @since 1.7.0
 */
class Tests_WP2D_API extends WP_UnitTestCase {

	/**
	 * Test the constructor, to make sure that the correct class variables are set.
	 *
	 * @since 1.7.0
	 */
	public function test_constructor() {
		$api = new WP2D_API( 'pod1' );
		$this->assertTrue( wp2d_helper_get_private_property( $api, 'is_secure' ) );
		$this->assertSame( 'pod1', wp2d_helper_get_private_property( $api, 'pod' ) );

		$api = new WP2D_API( 'pod2', false );
		$this->assertFalse( wp2d_helper_get_private_property( $api, 'is_secure' ) );
		$this->assertSame( 'pod2', wp2d_helper_get_private_property( $api, 'pod' ) );

	}

	/**
	 * Test getting a pod url in different formats.
	 *
	 * @since 1.7.0
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
	 * @since 1.7.0
	 */
	public function test_init_fail() {
		add_filter( 'wp2d_guzzle_handler', $handler = static function () {
			return HandlerStack::create( new MockHandler( [
				new RequestException( 'Error Communicating with Server', new Request( 'GET', '/users/sign_in' ) ),
				new Response( 200, [], '<meta name="not-a-csrf-token" content="nope" />' ),
			] ) );
		} );

		$api = new WP2D_API( 'pod' );

		// Directly check if the connection has been initialised.
		$this->assertFalse( wp2d_helper_call_private_method( $api, 'check_init' ) );
		$this->assertEquals(
			'Connection not initialised.',
			$api->get_last_error()
		);

		// False response, can't resolve host.
		$this->assertFalse( $api->init() );
		$this->assertStringContainsString(
			'Failed to initialise connection to pod "https://pod".',
			$api->get_last_error()
		);

		// Response has an invalid token.
		$this->assertFalse( $api->init() );
		$this->assertEquals(
			'Failed to initialise connection to pod "https://pod".',
			$api->get_last_error()
		);

		remove_filter( 'wp2d_guzzle_handler', $handler );
	}

	/**
	 * Test the successful initialisation and pod changes.
	 *
	 * @since 1.7.0
	 */
	public function test_init_success() {
		$mock = new MockHandler();
		add_filter( 'wp2d_guzzle_handler', $handler = static fn() => HandlerStack::create( $mock ) );

		$api = new WP2D_API( 'pod1' );

		// First initialisation.
		$mock->append( new Response( 200, body: '<meta name="csrf-token" content="token-a" />' ) );
		$this->assertTrue( $api->init() );
		$this->assertSame( 'token-a', wp2d_helper_get_private_property( $api, 'csrf_token' ) );

		// Reinitialise with same pod, token isn't reloaded.
		$this->assertTrue( $api->init( 'pod1' ) );
		$this->assertSame( 'token-a', wp2d_helper_get_private_property( $api, 'csrf_token' ) );

		// Reinitialise with different pod.
		$mock->append( new Response( 200, body: '<meta name="csrf-token" content="token-b" />' ) );
		$this->assertTrue( $api->init( 'pod2' ) );
		$this->assertSame( 'token-b', wp2d_helper_get_private_property( $api, 'csrf_token' ) );

		// Reinitialise with different protocol.
		$mock->append( new Response( 200, body: '<meta name="csrf-token" content="token-c" />' ) );
		$this->assertTrue( $api->init( 'pod2', false ) );
		$this->assertSame( 'token-c', wp2d_helper_get_private_property( $api, 'csrf_token' ) );

		remove_filter( 'wp2d_guzzle_handler', $handler );
	}

	/**
	 * Test fetching and forcefully re-fetching the token.
	 *
	 * @since 1.7.0
	 */
	public function test_load_csrf_token() {
		$mock = new MockHandler();
		add_filter( 'wp2d_guzzle_handler', $handler = static fn() => HandlerStack::create( $mock ) );

		$api = wp2d_api_helper_get_fake_api_init( 'pod', 'token-initial' );

		// Check the initial token.
		$this->assertEquals( 'token-initial', wp2d_helper_call_private_method( $api, 'load_csrf_token' ) );

		// Directly set a new token.
		wp2d_helper_set_private_property( $api, 'csrf_token', 'token-new' );
		$this->assertEquals( 'token-new', wp2d_helper_call_private_method( $api, 'load_csrf_token' ) );

		// Force fetch a new token.
		$mock->append( new Response( 200, body: '<meta name="csrf-token" content="token-forced" />' ) );
		$this->assertEquals( 'token-forced', wp2d_helper_call_private_method( $api, 'load_csrf_token', true ) );

		remove_filter( 'wp2d_guzzle_handler', $handler );
	}

	/**
	 * Test the login checker.
	 *
	 * @since 1.7.0
	 */
	public function test_check_login() {
		$api = new WP2D_API( 'pod' );
		// Try to check login before initialised.
		$this->assertFalse( $api->is_logged_in() );
		$this->assertFalse( wp2d_helper_call_private_method( $api, 'check_login' ) );
		$this->assertEquals( 'Connection not initialised.', $api->get_last_error() );

		$api = wp2d_api_helper_get_fake_api_init();

		$this->assertFalse( $api->is_logged_in() );
		$this->assertFalse( wp2d_helper_call_private_method( $api, 'check_login' ) );
		$this->assertEquals( 'Not logged in.', $api->get_last_error() );

		wp2d_helper_set_private_property( $api, 'is_logged_in', true );

		$this->assertTrue( $api->is_logged_in() );
		$this->assertTrue( wp2d_helper_call_private_method( $api, 'check_login' ) );
	}

	/**
	 * Test an invalid login.
	 *
	 * @since 1.7.0
	 */
	public function test_login_fail() {
		add_filter( 'wp2d_guzzle_handler', $handler = static function () {
			return HandlerStack::create( new MockHandler( [
				new Response( 401, [], 'Login error.' ),
			] ) );
		} );

		$api = new WP2D_API( 'pod' );
		// Try login before initialised.
		$this->assertFalse( $api->login( 'username', 'password' ) );
		$this->assertEquals( 'Connection not initialised.', $api->get_last_error() );

		$api = wp2d_api_helper_get_fake_api_init();

		// Both username AND password are required!
		$this->assertFalse( $api->login( '', '' ) );
		$this->assertFalse( $api->is_logged_in() );
		$this->assertEquals( 'Invalid credentials. Please re-save your login info.', $api->get_last_error( true ) );

		$this->assertFalse( $api->login( 'username-only', '' ) );
		$this->assertFalse( $api->is_logged_in() );
		$this->assertEquals( 'Invalid credentials. Please re-save your login info.', $api->get_last_error( true ) );

		$this->assertFalse( $api->login( '', 'password-only' ) );
		$this->assertFalse( $api->is_logged_in() );
		$this->assertEquals( 'Invalid credentials. Please re-save your login info.', $api->get_last_error( true ) );

		$this->assertFalse( $api->login( 'username-wrong', 'password-wrong' ) );
		$this->assertEquals( 'Login failed. Check your login details.', $api->get_last_error( true ) );

		remove_filter( 'wp2d_guzzle_handler', $handler );
	}

	/**
	 * Test a successful login, re-login and forced re-login.
	 *
	 * @since 1.7.0
	 */
	public function test_login_success() {
		$response_found = new Response( 302, reason: 'Found' );
		$response_ok    = new Response( 200, reason: 'OK' );

		$mock = new MockHandler();
		add_filter( 'wp2d_guzzle_handler', $handler = static fn() => HandlerStack::create( $mock ) );

		$api = wp2d_api_helper_get_fake_api_init();

		// First login.
		$mock->append( $response_found, $response_ok );
		$this->assertTrue( $api->login( 'username', 'password' ) );
		$this->assertTrue( $api->is_logged_in() );

		// Trying to log in again with same credentials just returns true, without making a new sign in attempt.
		$this->assertTrue( $api->login( 'username', 'password' ) );
		$this->assertTrue( $api->is_logged_in() );

		// Force a new sign in.
		$mock->append( $response_found, $response_ok );
		$this->assertTrue( $api->login( 'username', 'password', true ) );
		$this->assertTrue( $api->is_logged_in() );

		// Login with new credentials.
		$mock->append( $response_found, $response_ok );
		$this->assertTrue( $api->login( 'username-new', 'password-new' ) );
		$this->assertTrue( $api->is_logged_in() );

		remove_filter( 'wp2d_guzzle_handler', $handler );
	}

	/**
	 * Test _get_aspects_services method with an invalid argument.
	 *
	 * @since 1.7.0
	 */
	public function test_get_aspects_services_invalid_argument() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_services_fail' );

		// Testing with WP_Error response (check filter).
		$this->assertFalse( wp2d_helper_call_private_method( $api, 'get_aspects_services', 'invalid-argument', [], true ) );
		$this->assertEquals( 'Unknown error occurred.', $api->get_last_error() );

		// Testing invalid code response (check filter).
		$this->assertFalse( wp2d_helper_call_private_method( $api, 'get_aspects_services', 'invalid-argument', [], true ) );
		$this->assertEquals( 'Unknown error occurred.', $api->get_last_error() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_services_fail' );
	}

	/**
	 * Test getting aspects when an error occurs.
	 *
	 * @since 1.7.0
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
	 * @since 1.7.0
	 */
	public function test_get_aspects_success() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_success' );

		// The aspects that should be returned.
		$aspects = [ 'public' => 'Public', 1 => 'Family' ];
		$this->assertEquals( $aspects, $api->get_aspects() );
		$this->assertAttributeSame( $aspects, 'aspects', $api );

		// Fetching the aspects again should pass the same list without a new request.
		$this->assertEquals( $aspects, $api->get_aspects() );
		$this->assertAttributeSame( $aspects, 'aspects', $api );

		// Force a new fetch request.
		$aspects = [ 'public' => 'Public', 2 => 'Friends' ];
		$this->assertEquals( $aspects, $api->get_aspects( true ) );
		$this->assertAttributeSame( $aspects, 'aspects', $api );

		// Make sure that there is always at least a Public aspect.
		$aspects = [ 'public' => 'Public' ];
		$this->assertEquals( $aspects, $api->get_aspects( true ) );
		$this->assertAttributeSame( $aspects, 'aspects', $api );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects_success' );
	}

	/**
	 * Test getting services when an error occurs.
	 *
	 * @since 1.7.0
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
	 * @since 1.7.0
	 */
	public function test_get_services_success() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_services_success' );

		// The services that should be returned.
		$services = [ 'facebook' => 'Facebook' ];
		$this->assertEquals( $services, $api->get_services() );
		$this->assertAttributeSame( $services, 'services', $api );

		// Fetching the services again should pass the same list without a new request.
		$this->assertEquals( $services, $api->get_services() );
		$this->assertAttributeSame( $services, 'services', $api );

		// Force a new fetch request.
		$services = [ 'twitter' => 'Twitter' ];
		$this->assertEquals( $services, $api->get_services( true ) );
		$this->assertAttributeSame( $services, 'services', $api );

		// If no services are connected, make sure we get an empty array.
		$this->assertEquals( [], $api->get_services( true ) );
		$this->assertAttributeSame( [], 'services', $api );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_services_success' );
	}

	/**
	 * Test posting when an error occurs.
	 *
	 * @since 1.7.0
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
	 * @since 1.7.0
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

		$post3 = $api->post( 'text', [ '1' ] );
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
	 * @since 1.7.0
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
	 * @since 1.7.0
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
	 * @since 1.7.0
	 */
	public function test_logout() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		$api->logout();

		$this->assertAttributeSame( false, 'is_logged_in', $api );
		$this->assertAttributeSame( null, 'username', $api );
		$this->assertAttributeSame( null, 'password', $api );
		$this->assertAttributeSame( [], 'aspects', $api );
		$this->assertAttributeSame( [], 'services', $api );
	}

	/**
	 * Test the deinitialisation.
	 *
	 * @since 1.7.0
	 */
	public function test_deinit() {
		$api = wp2d_api_helper_get_fake_api_init_login();

		$api->deinit();

		$this->assertFalse( $api->has_last_error() );
		$this->assertAttributeSame( null, 'token', $api );
		$this->assertAttributeSame( [], 'cookies', $api );
		$this->assertAttributeSame( null, 'last_request', $api );
		$this->assertAttributeSame( false, 'is_logged_in', $api );
		$this->assertAttributeSame( null, 'username', $api );
		$this->assertAttributeSame( null, 'password', $api );
		$this->assertAttributeSame( [], 'aspects', $api );
		$this->assertAttributeSame( [], 'services', $api );
	}
}
