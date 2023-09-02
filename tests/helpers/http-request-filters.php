<?php
/**
 * All helper methods for the WP2D tests.
 *
 * @package WP_To_Diaspora\Tests\Helpers
 * @since   1.7.0
 */

/**
 * Custom HTTP request responses for test_update_aspects_services_list testing both aspects and services.
 *
 * @since 1.7.0
 */
function wp_to_diaspora_pre_http_request_filter_update_aspects() {
	static $i = 0;
	$success_bodies = [
		// Aspect bodies to return.
		'"aspects":[{"id":1,"name":"Family","selected":true}]',
		'"aspects":[{"id":2,"name":"Friends","selected":true}]',
		'WP_Error',
		'error',
		'"aspects":[]',
		// Service bodies to return.
		'"configured_services":["facebook"]',
		'"configured_services":["twitter"]',
		'WP_Error',
		'error',
		'"configured_services":[]',
	];

	$body = $success_bodies[ $i++ ];
	if ( 'WP_Error' === $body ) {
		return new WP_Error( 'wp_error_code', 'WP_Error message' );
	} elseif ( 'error' === $body ) {
		return [ 'response' => [ 'code' => 999, 'message' => 'Error code message' ] ];
	} else {
		return [
			'body'     => $body,
			'response' => [ 'code' => 200, 'message' => 'OK' ],
		];
	}
}


/**
 * Custom HTTP request responses for test_init_fail.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_init_fail() {
	static $responses = [
		false, // Will result in "Could not resolve host" error.
		[
			'body'     => '<meta name="not-a-csrf-token" content="nope" />',
			'response' => [ 'code' => 200, 'message' => 'OK' ],
		],
	];

	return array_shift( $responses );
}

/**
 * Custom HTTP request responses for test_init_success.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_init_success() {
	static $tokens = [ 'token-a', 'token-b', 'token-c' ];

	return [
		'cookies'  => [ 'the_cookie' ],
		'body'     => sprintf( '<meta name="csrf-token" content="%s" />', array_shift( $tokens ) ),
		'response' => [ 'code' => 200, 'message' => 'OK' ],
	];
}

/**
 * Custom HTTP request response for test_fetch_token.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_fetch_token() {
	return [
		'body'     => '<meta name="csrf-token" content="token-forced" />',
		'response' => [ 'code' => 200, 'message' => 'OK' ],
	];
}

/**
 * Custom HTTP request response for test_login_fail.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_login_fail() {
	return [ 'response' => [ 'code' => 999, 'message' => 'Error code message' ] ];
}

/**
 * Custom HTTP request response for test_login_success.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_login_success() {
	static $i = 0;
	$responses = [
		[ 'response' => [ 'code' => 302, 'message' => 'Found' ] ],
		[ 'response' => [ 'code' => 200, 'message' => 'OK' ] ],
	];

	// Since the same response pattern is used multiple times, just keep on looping through the responses.
	return $responses[ $i++ % count( $responses ) ];
}

/**
 * Custom HTTP request responses for:
 * test_get_aspects_services_invalid_argument
 * test_get_aspects_fail
 * test_get_services_fail.
 *
 * Return either a WP_Error object or and invalid response code.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_get_aspects_services_fail() {
	// Loop through responses using a static incrementing variable.
	// This is required, because no objects can be added to a static array.
	// see http://stackoverflow.com/a/10771559/3757422 for more info.
	static $i = 0;
	$responses = [
		new WP_Error( 'wp_error_code', 'WP_Error message' ),
		[ 'response' => [ 'code' => 999, 'message' => 'Error code message' ] ],
	];

	// Since this filter is used by different tests, just keep on looping through the responses.
	return $responses[ $i++ % count( $responses ) ];
}

/**
 * Custom HTTP request responses for test_get_aspects_success.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_get_aspects_success() {
	static $aspects_bodies = [
		'[{"id":1,"name":"Family","selected":true}]',
		'[{"id":2,"name":"Friends","selected":true}]',
		'[]',
	];

	return [
		'body'     => '"aspects":' . array_shift( $aspects_bodies ),
		'response' => [ 'code' => 200, 'message' => 'OK' ],
	];
}

/**
 * Custom HTTP request responses for test_get_services_success.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_get_services_success() {
	static $services_bodies = [ '["facebook"]', '["twitter"]', '[]' ];

	return [
		'body'     => '"configured_services":' . array_shift( $services_bodies ),
		'response' => [ 'code' => 200, 'message' => 'OK' ],
	];
}

/**
 * Custom HTTP request responses for test_post_fail.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_post_fail() {
	// Loop through responses using a static incrementing variable.
	// This is required, because no objects can be added to a static array.
	// see http://stackoverflow.com/a/10771559/3757422 for more info.
	static $i = 0;
	$responses = [
		new WP_Error( 'wp_error_code', 'WP_Error message' ),
		[
			'body'     => '{"error":"Error code message"}',
			'response' => [ 'code' => 999, 'message' => 'Error code message' ],
		],
	];

	return $responses[ $i++ ];
}
