<?php

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class Tests_WP2D_API extends Tests_WP2D_UnitTestCase {

	public function test_constructor() {
		$api = new WP2D_API( 'pod1' );
		$this->assertTrue( invade( $api )->is_secure );
		$this->assertEquals( 'pod1', invade( $api )->pod );

		$api = new WP2D_API( 'pod2', false );
		$this->assertFalse( invade( $api )->is_secure );
		$this->assertEquals( 'pod2', invade( $api )->pod );
	}

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

	public function test_init_failure() {
		$api = new WP2D_API( 'pod' );

		// Directly check if the connection has been initialised.
		$this->assertFalse( invade( $api )->check_init() );
		$this->assertEquals( 'Connection not initialised.', $api->get_last_error() );

		// False response, can't resolve host.
		$this->mock->append( new RequestException( 'Error Communicating with Server', new Request( 'GET', '/users/sign_in' ) ) );
		$this->assertFalse( $api->init() );
		$this->assertStringContainsString( 'Failed to initialise connection to pod "https://pod".', $api->get_last_error() );

		// Response has an invalid token.
		$this->mock->append( new Response( 200, [], '<meta name="not-a-csrf-token" content="nope" />' ) );
		$this->assertFalse( $api->init() );
		$this->assertEquals( 'Failed to initialise connection to pod "https://pod".', $api->get_last_error() );
	}

	public function test_init_successfully() {
		$api = new WP2D_API( 'pod1' );

		// First initialisation.
		$this->mock->append( new Response( 200, body: '<meta name="csrf-token" content="token-a" />' ) );
		$this->assertTrue( $api->init() );
		$this->assertSame( 'token-a', invade( $api )->csrf_token );

		// Reinitialise with same pod, token isn't reloaded.
		$this->assertTrue( $api->init( 'pod1' ) );
		$this->assertSame( 'token-a', invade( $api )->csrf_token );

		// Reinitialise with different pod.
		$this->mock->append( new Response( 200, body: '<meta name="csrf-token" content="token-b" />' ) );
		$this->assertTrue( $api->init( 'pod2' ) );
		$this->assertSame( 'token-b', invade( $api )->csrf_token );

		// Reinitialise with different protocol.
		$this->mock->append( new Response( 200, body: '<meta name="csrf-token" content="token-c" />' ) );
		$this->assertTrue( $api->init( 'pod2', false ) );
		$this->assertSame( 'token-c', invade( $api )->csrf_token );
	}

	public function test_load_csrf_token() {
		$api = $this->getFakeApi( 'pod', 'token-initial' );

		// Check the initial token.
		$this->assertEquals( 'token-initial', invade( $api )->load_csrf_token() );

		// Directly set a new token.
		invade( $api )->csrf_token = 'token-new';
		$this->assertEquals( 'token-new', invade( $api )->load_csrf_token() );

		// Force fetch a new token.
		$this->mock->append( new Response( 200, body: '<meta name="csrf-token" content="token-forced" />' ) );
		$this->assertEquals( 'token-forced', invade( $api )->load_csrf_token( true ) );
	}

	public function test_check_login() {
		$api = new WP2D_API( 'pod' );

		// Try to check login before initialised.
		$this->assertFalse( $api->is_logged_in() );
		$this->assertFalse( invade( $api )->check_login() );
		$this->assertEquals( 'Connection not initialised.', $api->get_last_error() );

		$api = $this->getFakeApi();

		$this->assertFalse( $api->is_logged_in() );
		$this->assertFalse( invade( $api )->check_login() );
		$this->assertEquals( 'Not logged in.', $api->get_last_error() );

		invade( $api )->is_logged_in = true;

		$this->assertTrue( $api->is_logged_in() );
		$this->assertTrue( invade( $api )->check_login() );
	}

	public function test_login_failure() {
		$api = new WP2D_API( 'pod' );

		// Try login before initialised.
		$this->assertFalse( $api->login( 'username', 'password' ) );
		$this->assertEquals( 'Connection not initialised.', $api->get_last_error() );

		$api = $this->getFakeApi();

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

		$this->mock->append( new Response( 401, [], 'Login error.' ) );
		$this->assertFalse( $api->login( 'username-wrong', 'password-wrong' ) );
		$this->assertEquals( 'Login failed. Check your login details.', $api->get_last_error( true ) );
	}

	public function test_login_success() {
		$response_found = static fn() => new Response( 302, reason: 'Found' );
		$response_ok    = static fn() => new Response( 200, reason: 'OK' );

		$api = $this->getFakeApi();

		// First login.
		$this->mock->append( $response_found(), $response_ok() );
		$this->assertTrue( $api->login( 'username', 'password' ) );
		$this->assertTrue( $api->is_logged_in() );
		$first_response = invade( $api )->last_response;

		// Trying to log in again with same credentials just returns true, without making a new sign in attempt.
		$this->assertTrue( $api->login( 'username', 'password' ) );
		$this->assertTrue( $api->is_logged_in() );
		// Response should be identical to first one, as there shouldn't be a new one.
		$this->assertSame( $first_response, invade( $api )->last_response );

		// Force a new sign in.
		$this->mock->append( $response_found(), $response_ok() );
		$this->assertTrue( $api->login( 'username', 'password', true ) );
		$this->assertTrue( $api->is_logged_in() );
		$this->assertNotSame( $first_response, invade( $api )->last_response );

		// Login with new credentials.
		$this->mock->append( $response_found(), $response_ok() );
		$this->assertTrue( $api->login( 'username-new', 'password-new' ) );
		$this->assertTrue( $api->is_logged_in() );
		$this->assertNotSame( $first_response, invade( $api )->last_response );
	}

	public function test_get_aspects_services_with_invalid_argument() {
		$api = $this->getFakeApiLogin();

		$this->assertFalse( invade( $api )->get_aspects_services( 'invalid-argument', [], true ) );
		$this->assertEquals( 'Unknown error occurred.', $api->get_last_error() );

		$this->markTestIncomplete( 'Should probably be deleted...' );
	}

	public function test_get_aspects_failure() {
		$api = $this->getFakeApi();

		$this->assertFalse( $api->get_aspects() );
		$this->assertEquals( 'Not logged in.', $api->get_last_error() );

		$api = $this->getFakeApiLogin();

		$this->mock->append( new Response( 400 ) );

		$this->assertFalse( $api->get_aspects() );
		$this->assertEquals( 'Error loading aspects.', $api->get_last_error() );
	}

	public function test_get_aspects_success() {
		$api = $this->getFakeApiLogin();

		$aspects = [ 'public' => 'Public', 1 => 'Family' ];
		$this->mock->append( new Response( 200, body: '"aspects":[{"id":1,"name":"Family","selected":true}]', reason: 'OK' ) );
		$this->assertEquals( $aspects, $api->get_aspects() );
		$this->assertSame( $aspects, invade( $api )->aspects );

		// Fetching the aspects again should pass the same list without a new request.
		$this->assertEquals( $aspects, $api->get_aspects() );
		$this->assertSame( $aspects, invade( $api )->aspects );

		// Force a new fetch request.
		$aspects = [ 'public' => 'Public', 2 => 'Friends' ];
		$this->mock->append( new Response( 200, body: '"aspects":[{"id":2,"name":"Friends","selected":true}]', reason: 'OK' ) );
		$this->assertEquals( $aspects, $api->get_aspects( true ) );
		$this->assertSame( $aspects, invade( $api )->aspects );

		// Make sure that there is always at least a Public aspect.
		$aspects = [ 'public' => 'Public' ];
		$this->mock->append( new Response( 200, body: '"aspects":[]', reason: 'OK' ) );
		$this->assertEquals( $aspects, $api->get_aspects( true ) );
		$this->assertSame( $aspects, invade( $api )->aspects );
	}

	public function test_get_services_failure() {
		$api = $this->getFakeApi();

		$this->assertFalse( $api->get_services() );
		$this->assertEquals( 'Not logged in.', $api->get_last_error() );

		$api = $this->getFakeApiLogin();

		$this->mock->append( new Response( 400 ) );

		$this->assertFalse( $api->get_services() );
		$this->assertEquals( 'Error loading services.', $api->get_last_error() );
	}

	public function test_get_services_successfully() {
		$api = $this->getFakeApiLogin();

		$this->mock->append( new Response( 200, body: '"configured_services":["facebook"]', reason: 'OK' ) );
		$services = [ 'facebook' => 'Facebook' ];
		$this->assertEquals( $services, $api->get_services() );
		$this->assertEquals( $services, invade( $api )->services );

		// Fetching the services again should pass the same list without a new request.
		$this->assertEquals( $services, $api->get_services() );
		$this->assertEquals( $services, invade( $api )->services );

		$this->mock->append( new Response( 200, body: '"configured_services":["twitter"]', reason: 'OK' ) );
		$services = [ 'twitter' => 'Twitter' ];
		$this->assertEquals( $services, $api->get_services( true ) );
		$this->assertEquals( $services, invade( $api )->services );

		$this->mock->append( new Response( 200, body: '"configured_services":[]', reason: 'OK' ) );
		$this->assertEquals( [], $api->get_services( true ) );
		$this->assertEquals( [], invade( $api )->services );
	}

	public function test_create_post_failure() {
		$api = $this->getFakeApi();

		$this->assertFalse( $api->post( 'text' ) );
		$this->assertEquals( 'Not logged in.', $api->get_last_error() );

		$api = $this->getFakeApiLogin();

		$this->mock->append( new Response( 400, body: '{"error":"Some API error from server"}', reason: 'Error code message' ) );
		$this->assertFalse( $api->post( 'text' ) );
		$this->assertEquals( 'Some API error from server', $api->get_last_error() );

		$this->mock->append( new Response( 500, reason: 'Some server error' ) );
		$this->assertFalse( $api->delete( 'post', 'anything' ) );
		$this->assertStringContainsString( 'Some server error', $api->get_last_error() );
	}

	public function test_create_post_successfully() {
		$api = $this->getFakeApiLogin();

		$this->mock->append( new Response( 201, body: '{"id":1,"public":true,"guid":"guid1","text":"text1"}', reason: 'Created' ) );
		$post1 = $api->post( 'text' );
		$this->assertEquals( 1, $post1->id );
		$this->assertEquals( true, $post1->public );
		$this->assertEquals( 'guid1', $post1->guid );
		$this->assertEquals( 'text1', $post1->text );
		$this->assertEquals( 'https://pod/posts/guid1', $post1->permalink );

		$this->mock->append( new Response( 201, body: '{"id":2,"public":false,"guid":"guid2","text":"text2"}', reason: 'Created' ) );
		$post2 = $api->post( 'text', '1' );
		$this->assertEquals( 2, $post2->id );
		$this->assertEquals( false, $post2->public );
		$this->assertEquals( 'guid2', $post2->guid );
		$this->assertEquals( 'text2', $post2->text );
		$this->assertEquals( 'https://pod/posts/guid2', $post2->permalink );

		$this->mock->append( new Response( 201, body: '{"id":3,"public":false,"guid":"guid3","text":"text3"}', reason: 'Created' ) );
		$post3 = $api->post( 'text', [ '1' ] );
		$this->assertEquals( 3, $post3->id );
		$this->assertEquals( false, $post3->public );
		$this->assertEquals( 'guid3', $post3->guid );
		$this->assertEquals( 'text3', $post3->text );
		$this->assertEquals( 'https://pod/posts/guid3', $post3->permalink );

		$this->markTestIncomplete( 'Need a test for the extra data parameter!' );
	}

	public function test_general_delete_failure() {
		$api = $this->getFakeApi();

		$this->assertFalse( $api->delete( 'post', 'anything' ) );
		$this->assertEquals( 'Not logged in.', $api->get_last_error() );

		$api = $this->getFakeApiLogin();

		$this->assertFalse( $api->delete( 'internet', 'allofit' ) );
		$this->assertEquals( 'You can only delete posts and comments.', $api->get_last_error() );

		$this->mock->append( new Response( 400, body: 'You are not allowed to do that', reason: 'Some client error' ) );
		$this->assertFalse( $api->delete( 'post', 'anything' ) );
		$this->assertEquals( 'You are not allowed to do that', $api->get_last_error() );

		$this->mock->append( new Response( 500, reason: 'Some server error' ) );
		$this->assertFalse( $api->delete( 'post', 'anything' ) );
		$this->assertStringContainsString( 'Some server error', $api->get_last_error() );
	}

	public function test_delete_post_failure() {
		$api = $this->getFakeApiLogin();

		$this->mock->append( new Response( 404, reason: 'Not Found' ) );
		$this->assertFalse( $api->delete( 'post', 'invalid_id' ) );
		$this->assertEquals( 'The post you tried to delete does not exist.', $api->get_last_error() );

		$this->mock->append( new Response( 403, reason: 'Forbidden' ) );
		$this->assertFalse( $api->delete( 'post', 'not_my_id' ) );
		$this->assertEquals( 'The post you tried to delete does not belong to you.', $api->get_last_error() );
	}

	public function test_delete_comment_failure() {
		$api = $this->getFakeApiLogin();

		$this->mock->append( new Response( 404, reason: 'Not Found' ) );
		$this->assertFalse( $api->delete( 'comment', 'invalid_id' ) );
		$this->assertEquals( 'The comment you tried to delete does not exist.', $api->get_last_error() );

		$this->mock->append( new Response( 403, reason: 'Forbidden' ) );
		$this->assertFalse( $api->delete( 'comment', 'not_my_id' ) );
		$this->assertEquals( 'The comment you tried to delete does not belong to you.', $api->get_last_error() );

	}

	public function test_delete_post_successfully() {
		$api = $this->getFakeApiLogin();

		$this->mock->append( new Response( 204, reason: 'No Content' ) );
		$this->assertTrue( $api->delete( 'post', 'my_valid_id' ) );
	}

	public function test_delete_comment_successfully() {
		$api = $this->getFakeApiLogin();

		$this->mock->append( new Response( 204, reason: 'No Content' ) );
		$this->assertTrue( $api->delete( 'comment', 'my_valid_id' ) );
	}

	public function test_logout() {
		$api = $this->getFakeApiLogin();

		$api->logout();

		$this->assertFalse( invade( $api )->is_logged_in );
		$this->assertSame( '', invade( $api )->username );
		$this->assertSame( '', invade( $api )->password );
		$this->assertSame( [], invade( $api )->aspects );
		$this->assertSame( [], invade( $api )->services );
	}

	public function test_deinit() {
		$api = $this->getFakeApiLogin();

		$api->deinit();

		$this->assertFalse( $api->has_last_error() );
		$this->assertSame( '', invade( $api )->csrf_token );
		$this->assertNull( invade( $api )->last_response );
		$this->assertFalse( invade( $api )->is_logged_in );
		$this->assertSame( '', invade( $api )->username );
		$this->assertSame( '', invade( $api )->password );
		$this->assertSame( [], invade( $api )->aspects );
		$this->assertSame( [], invade( $api )->services );
	}
}
