<?php

use WP2D\Dependencies\GuzzleHttp\Handler\MockHandler;
use WP2D\Dependencies\GuzzleHttp\HandlerStack;

/**
 * WP_To_Diaspora tests.
 *
 * @package WP_To_Diaspora\Tests\WP_To_Diaspora
 * @since   1.7.0
 */
class Tests_WP2D_UnitTestCase extends WP_UnitTestCase {

	protected Closure $handler;
	public MockHandler $mock;

	public function set_up(): void {
		$this->mock    = new MockHandler();
		$this->handler = fn() => HandlerStack::create( $this->mock );
		add_filter( 'wp2d_guzzle_handler', $this->handler );

		parent::set_up();
	}

	public function tear_down(): void {
		remove_filter( 'wp2d_guzzle_handler', $this->handler );

		parent::tear_down();
	}

	public function getFakeApi( $pod = 'pod', $token = 'token' ): WP2D_API {
		$api = new WP2D_API( $pod );

		// Fake initialisation.
		invade( $api )->csrf_token = $token;

		return $api;
	}

	public function getFakeApiLogin( $pod = 'pod', $token = 'token', $username = 'username', $password = 'password' ): WP2D_API {
		$api = $this->getFakeApi( $pod, $token );

		// Fake valid login.
		invade( $api )->is_logged_in = true;
		invade( $api )->username     = $username;
		invade( $api )->password     = $password;

		return $api;
	}
}
