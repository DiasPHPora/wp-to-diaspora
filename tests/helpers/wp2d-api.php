<?php
/**
 * All helper methods for the WP2D tests.
 *
 * @package WP_To_Diaspora\Tests\Helpers
 * @since next-release
 */

/**
 * Little helper method to get the last API error message.
 *
 * @since next-release
 *
 * @param WP2D_API $api   The API object to get the message from.
 * @param bool     $clear If the last error should be cleared after fetching.
 * @return string The last error message or null.
 */
function wp2d_api_helper_get_last_error_message( $api, $clear = false ) {
	if ( is_wp_error( $api->last_error ) ) {
		$error = $api->last_error->get_error_message();
		$clear && $api->last_error = null;
		return $error;
	}
	return null;
}

/**
 * Create an API instance and fake it's initialisation.
 *
 * This method helps to prevent HTTP requests for tests that need a valid token.
 *
 * @since next-release
 *
 * @param string $pod   Pod to fake.
 * @param string $token Token to fake.
 * @return WP2D_API The fakely initialised API instance.
 */
function wp2d_api_helper_get_fake_api_init( $pod = 'pod', $token = 'token' ) {
	$api = new WP2D_API( $pod );

	// Fake initialisation.
	wp2d_helper_set_private_property( $api, '_token', $token );

	return $api;
}

/**
 * Create an API instance and fake it's initialisation.
 *
 * This method helps to prevent HTTP requests for tests that need a valid login.
 *
 * @since next-release
 *
 * @param string $pod      Pod to fake.
 * @param string $token    Token to fake.
 * @param string $username Username to fake.
 * @param string $password Password to fake.
 * @return WP2D_API The fakely initialised and logged in API instance.
 */
function wp2d_api_helper_get_fake_api_init_login( $pod = 'pod', $token = 'token', $username = 'username', $password = 'password' ) {
	$api = wp2d_api_helper_get_fake_api_init( $pod, $token );

	// Fake valid login.
	wp2d_helper_set_private_property( $api, '_is_logged_in', true );
	wp2d_helper_set_private_property( $api, '_username', $username );
	wp2d_helper_set_private_property( $api, '_password', $password );

	return $api;
}
