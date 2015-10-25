<?php
/**
 * WP2D_API tests.
 *
 * @package WP_To_Diaspora\Tests\WP2D_API
 * @since 1.6.0
 */

/**
 * Main API test class.
 *
 * @since 1.6.0
 *
 * @coversDefaultClass WP2D_API
 */
class Tests_WP2D_API extends WP_UnitTestCase {

	/**
	 * Instance of the WP2D_API class.
	 *
	 * @since 1.6.0
	 *
	 * @var WP2D_API
	 */
	protected static $api;

	/**
	 * Set up the instance of the API class.
	 *
	 * @since 1.6.0
	 */
	public static function setUpBeforeClass() {
		// Set up our API instance.
		self::$api = new WP2D_API( 'pod', true );
	}

	/**
	 * Little helper method to get the last API error message.
	 *
	 * @since 1.6.0
	 *
	 * @return string The last error message or null.
	 */
	private function _get_last_error_message() {
		if ( is_wp_error( self::$api->last_error ) ) {
			return self::$api->last_error->get_error_message();
		}
		return null;
	}

	/**
	 * Test getting a pod url.
	 *
	 * @covers ::get_pod_url
	 */
	public function test_get_pod_url() {
		$this->assertEquals( 'https://pod', self::$api->get_pod_url() );
		$this->assertEquals( 'https://pod/a', self::$api->get_pod_url( 'a' ) );
		$this->assertEquals( 'https://pod/a/', self::$api->get_pod_url( 'a/' ) );
		$this->assertNotEquals( 'https://pod/a/', self::$api->get_pod_url( 'a//' ) );
	}

	/**
	 * Test the initialisation.
	 *
	 * @covers ::init
	 */
	public function test_init() {
		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_init' );

		$this->assertTrue( self::$api->init() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_init' );
	}

	/**
	 * Test fetching the token.
	 *
	 * @covers ::_fetch_token
	 * @depends test_init
	 */
	public function test_fetch_token() {
		$this->assertEquals( 'xyz', wp2d_helper_call_private_method( self::$api, '_fetch_token' ) );

		wp2d_helper_set_private_property( self::$api, '_token', 'uvw' );
		$this->assertEquals( 'uvw', wp2d_helper_call_private_method( self::$api, '_fetch_token' ) );

		wp2d_helper_set_private_property( self::$api, '_token', 'xyz' );
	}

	/**
	 * Test not being logged in.
	 *
	 * @covers ::is_logged_in
	 * @depends test_init
	 */
	public function test_not_logged_in() {
		$this->assertFalse( self::$api->is_logged_in() );

		$this->assertFalse( self::$api->get_aspects() );
		$this->assertEquals( 'Not logged in.', $this->_get_last_error_message() );
		self::$api->last_error = null;

		$this->assertFalse( self::$api->get_services() );
		$this->assertEquals( 'Not logged in.', $this->_get_last_error_message() );
		self::$api->last_error = null;

		$this->assertFalse( self::$api->post( 'text' ) );
		$this->assertEquals( 'Not logged in.', $this->_get_last_error_message() );
		self::$api->last_error = null;

		$this->assertFalse( self::$api->delete( 'post', '1' ) );
		$this->assertEquals( 'Not logged in.', $this->_get_last_error_message() );
		self::$api->last_error = null;
	}

	/**
	 * Test the login check.
	 *
	 * @covers ::_check_login
	 * @depends test_init
	 */
	public function test_check_login() {
		wp2d_helper_set_private_property( self::$api, '_is_logged_in', true );
		$this->assertTrue( wp2d_helper_call_private_method( self::$api, '_check_login' ) );

		wp2d_helper_set_private_property( self::$api, '_is_logged_in', false );
		$this->assertFalse( wp2d_helper_call_private_method( self::$api, '_check_login' ) );
		$this->assertInstanceOf( 'WP_Error', self::$api->last_error );
		$this->assertEquals( 'Not logged in.', $this->_get_last_error_message() );
	}

	/**
	 * Test the login.
	 *
	 * @covers ::login
	 * @depends test_not_logged_in
	 */
	public function test_login() {
		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_login' );

		$this->assertTrue( self::$api->login( 'user', 'pass' ) );
		$this->assertTrue( self::$api->is_logged_in() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_login' );
	}

	/**
	 * Test getting aspects.
	 *
	 * @covers ::get_aspects
	 * @depends test_login
	 */
	public function test_get_aspects() {
		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects' );

		$this->assertEquals( array( 'public' => 'Public', 1 => 'Family', 2 => 'Friends' ), self::$api->get_aspects() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_aspects' );
	}

	/**
	 * Test getting services.
	 *
	 * @covers ::get_services
	 * @depends test_login
	 */
	public function test_get_services() {
		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_services' );

		$this->assertEquals( array( 'facebook' => 'Facebook', 'twitter' => 'Twitter' ), self::$api->get_services() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_get_services' );
	}

	/**
	 * Test the posting.
	 *
	 * @covers ::post
	 * @depends test_login
	 */
	public function test_post() {
		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_post' );

		$post1 = self::$api->post( 'text' );
		$this->assertEquals( 1, $post1->id );
		$this->assertEquals( true, $post1->public );
		$this->assertEquals( 'abc', $post1->guid );
		$this->assertEquals( 'text1', $post1->text );
		$this->assertEquals( 'https://pod/posts/abc', $post1->permalink );

		$post2 = self::$api->post( 'text', '1' );
		$this->assertEquals( 2, $post2->id );
		$this->assertEquals( false, $post2->public );
		$this->assertEquals( 'def', $post2->guid );
		$this->assertEquals( 'text2', $post2->text );
		$this->assertEquals( 'https://pod/posts/def', $post2->permalink );

		$post3 = self::$api->post( 'text', array( '1' ) );
		$this->assertEquals( 3, $post3->id );
		$this->assertEquals( false, $post3->public );
		$this->assertEquals( 'ghi', $post3->guid );
		$this->assertEquals( 'text3', $post3->text );
		$this->assertEquals( 'https://pod/posts/ghi', $post3->permalink );

		// Need a test for the extra data parameter!

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_post' );
	}

	/**
	 * Test deleting posts and comments.
	 *
	 * @covers ::delete
	 * @depends test_login
	 */
	public function test_delete() {
		add_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_delete' );

		// Deleting something other than posts or comments.
		$this->assertFalse( self::$api->delete( 'internet', 'allofit' ) );
		$this->assertEquals( 'You can only delete posts and comments.', $this->_get_last_error_message() );

		// Deleting posts.
		$this->assertFalse( self::$api->delete( 'post', 'invalid_id' ) );
		$this->assertEquals( 'The post you tried to delete does not exist.', $this->_get_last_error_message() );

		$this->assertFalse( self::$api->delete( 'post', 'not_my_id' ) );
		$this->assertEquals( 'The post you tried to delete does not belong to you.', $this->_get_last_error_message() );

		$this->assertTrue( self::$api->delete( 'post', 'my_valid_id' ) );

		// Deleting comments.
		$this->assertFalse( self::$api->delete( 'comment', 'invalid_id' ) );
		$this->assertEquals( 'The comment you tried to delete does not exist.', $this->_get_last_error_message() );

		$this->assertFalse( self::$api->delete( 'comment', 'not_my_id' ) );
		$this->assertEquals( 'The comment you tried to delete does not belong to you.', $this->_get_last_error_message() );

		$this->assertTrue( self::$api->delete( 'comment', 'my_valid_id' ) );

		// Unknown error, due to an invalid response code.
		$this->assertFalse( self::$api->delete( 'post', 'anything' ) );
		$this->assertEquals( 'Unknown error occurred.', $this->_get_last_error_message() );

		remove_filter( 'pre_http_request', 'wp2d_api_pre_http_request_filter_delete' );
	}

	/**
	 * Test logging out.
	 *
	 * Make sure we perform all tests that require a login, before logging out.
	 *
	 * @covers ::logout
	 * @depends test_get_aspects
	 * @depends test_get_services
	 * @depends test_post
	 * @depends test_delete
	 */
	public function test_logout() {
		self::$api->logout();

		$this->assertNull( self::$api->last_error );
		$this->assertAttributeSame( null,    '_token',        self::$api );
		$this->assertAttributeSame( array(), '_cookies',      self::$api );
		$this->assertAttributeSame( null,    '_last_request', self::$api );
		$this->assertAttributeSame( null,    '_username',     self::$api );
		$this->assertAttributeSame( null,    '_password',     self::$api );
		$this->assertAttributeSame( false,   '_is_logged_in', self::$api );
		$this->assertAttributeSame( array(), '_aspects',      self::$api );
		$this->assertAttributeSame( array(), '_services',     self::$api );
	}
}
