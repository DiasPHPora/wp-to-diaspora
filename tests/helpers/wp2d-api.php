<?php
/**
 * All helper methods for the WP2D tests.
 *
 * @package WP_To_Diaspora\Tests\Helpers
 * @since   1.7.0
 */

/**
 * Create an API instance and fake it's initialisation.
 *
 * This method helps to prevent HTTP requests for tests that need a valid token.
 *
 * @since 1.7.0
 *
 * @param string $pod   Pod to fake.
 * @param string $token Token to fake.
 *
 * @return WP2D_API The fakely initialised API instance.
 */
function wp2d_api_helper_get_fake_api_init( $pod = 'pod', $token = 'token' ) {
	$api = new WP2D_API( $pod );

	// Fake initialisation.
	wp2d_helper_set_private_property( $api, 'token', $token );

	return $api;
}

/**
 * Create an API instance and fake it's initialisation.
 *
 * This method helps to prevent HTTP requests for tests that need a valid login.
 *
 * @since 1.7.0
 *
 * @param string $pod      Pod to fake.
 * @param string $token    Token to fake.
 * @param string $username Username to fake.
 * @param string $password Password to fake.
 *
 * @return WP2D_API The fakely initialised and logged in API instance.
 */
function wp2d_api_helper_get_fake_api_init_login( $pod = 'pod', $token = 'token', $username = 'username', $password = 'password' ) {
	$api = wp2d_api_helper_get_fake_api_init( $pod, $token );

	// Fake valid login.
	wp2d_helper_set_private_property( $api, 'is_logged_in', true );
	wp2d_helper_set_private_property( $api, 'username', $username );
	wp2d_helper_set_private_property( $api, 'password', $password );

	return $api;
}
