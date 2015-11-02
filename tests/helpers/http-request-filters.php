<?php
/**
 * All helper methods for the WP2D tests.
 *
 * @package WP_To_Diaspora\Tests\Helpers
 * @since 1.6.0
 */

/**
 * Custom HTTP request response for the API init.
 *
 * @return array Response from the API init.
 */
function wp2d_api_pre_http_request_filter_init() {
	return array(
		'body'     => '<meta name="csrf-token" content="xyz" />',
		'response' => array( 'code' => 200, 'message' => 'OK' ),
	);
}

/**
 * Custom HTTP request response for the API init with no valid token.
 *
 * @return array Response from the API init.
 */
function wp2d_api_pre_http_request_filter_init_no_valid_token() {
	return array(
		'body'     => '<meta name="not-a-csrf-token" content="nope" />',
		'response' => array( 'code' => 200, 'message' => 'OK' ),
	);
}

/**
 * Force fetching token.
 *
 * @return array Response.
 */
function wp2d_api_pre_http_request_filter_fetch_token() {
	return array(
		'body'     => '<meta name="csrf-token" content="forced" />',
		'response' => array( 'code' => 200, 'message' => 'OK' ),
	);
}

/**
 * Custom HTTP request responses for changing the pod.
 *
 * @return array Response from the API init.
 */
function wp2d_api_pre_http_request_filter_init_change_pod() {
	// Create a static array of responses and return them one by one.
	static $responses = array(
		// 1st init.
		array(
			'body' => '<meta name="csrf-token" content="xyz" />',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
		// 2nd init.
		array(
			'body' => '<meta name="csrf-token" content="uvw" />',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
	);

	return array_shift( $responses );
}

/**
 * Custom HTTP request responses for an invalid login.
 *
 * @return array Response for either the sign-in or bookmarklet requests.
 */
function wp2d_api_pre_http_request_filter_login_invalid() {
	return array( 'response' => array( 'code' => 999, 'message' => 'invalid-login' ) );
}

/**
 * Custom HTTP request responses for the API login.
 *
 * @return array Response for either the sign-in or bookmarklet requests.
 */
function wp2d_api_pre_http_request_filter_login() {
	// Create a static array of responses and return them one by one.
	// After an empty login, which results in a logout(), remember to fetch the new token first.
	static $responses = array(
		array(
			'body'     => '<meta name="csrf-token" content="xyz" />',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
		array(
			'body'     => '<meta name="csrf-token" content="xyz" />',
			'response' => array( 'code' => 302, 'message' => 'Found' ),
		),
		array(
			'body'     => '<meta name="csrf-token" content="xyz" />',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
	);

	return array_shift( $responses );
}

/**
 * Custom HTTP request responses for the API forced login.
 *
 * @return array Response for either the sign-in or bookmarklet requests.
 */
function wp2d_api_pre_http_request_filter_login_forced() {
	// Create a static array of responses and return them one by one.
	// Forcing a login also forces to fetch a new token.
	static $responses = array(
		array(
			'body'     => '<meta name="csrf-token" content="abc" />',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
		array(
			'body'     => '<meta name="csrf-token" content="abc" />',
			'response' => array( 'code' => 302, 'message' => 'Found' ),
		),
		array(
			'body'     => '<meta name="csrf-token" content="abc" />',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
		array(
			'body'     => '<meta name="csrf-token" content="xyz" />',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
		array(
			'body'     => '<meta name="csrf-token" content="xyz" />',
			'response' => array( 'code' => 302, 'message' => 'Found' ),
		),
		array(
			'body'     => '<meta name="csrf-token" content="xyz" />',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
	);

	return array_shift( $responses );
}

/**
 * Custom HTTP request responses for the failed API post calls.
 *
 * @return array Responses for the different posting scenarios.
 */
function wp2d_api_pre_http_request_filter_post_failed() {
	// These are the different responses for the post calls made in Tests_WP2D_API::test_post().
	// Create an array of responses and a static variable that increments after each request,
	// returning the next response. This is required, because no objects can be added to a static array.
	// see http://stackoverflow.com/a/10771559/3757422 for more info.
	static $i = 0;
	$responses = array(
		new WP_Error( 'wp2d_api_post_failed', 'Fail message' ),
		array(
			'body' => '{"error":"Some Post Error"}',
			'response' => array( 'code' => 999, 'message' => 'Some Error' ),
		),
	);
	return $responses[ $i++ ];
}

/**
 * Custom HTTP request responses for the API post calls.
 *
 * @return array Responses for the different posting scenarios.
 */
function wp2d_api_pre_http_request_filter_post() {
	// These are the different responses for the post calls made in Tests_WP2D_API::test_post().
	// Create a static array of response bodies and return them one by one.
	static $bodies = array(
		'{"id":1,"public":true,"guid":"abc","text":"text1"}',
		'{"id":2,"public":false,"guid":"def","text":"text2"}',
		'{"id":3,"public":false,"guid":"ghi","text":"text3"}',
	);

	return array(
		'body'     => array_shift( $bodies ),
		'response' => array( 'code' => 201, 'message' => 'Created' ),
	);
}

/**
 * Custom HTTP request responses for the API delete calls.
 *
 * @return array Responses for the different delete scenarios.
 */
function wp2d_api_pre_http_request_filter_delete() {
	// These are the different responses for the delete calls made in Tests_WP2D_API::test_delete().
	// Create a static array of responses and return them one by one.
	static $i = 0;
	$responses = array(
		// WP_Error.
		new WP_Error( 'wp_error', 'Error message' ),
		// Posts.
		array( 'response' => array( 'code' => 404, 'message' => 'Not Found' ) ),
		array( 'response' => array( 'code' => 500, 'message' => 'Internal Server Error' ) ),
		array( 'response' => array( 'code' => 204, 'message' => 'No Content' ) ),
		// Comments.
		array( 'response' => array( 'code' => 404, 'message' => 'Not Found' ) ),
		array( 'response' => array( 'code' => 403, 'message' => 'Forbidden' ) ),
		array( 'response' => array( 'code' => 204, 'message' => 'No Content' ) ),
		// Invalid response code.
		array( 'response' => array( 'code' => 999, 'message' => 'Anything Really' ) ),
	);

	return $responses[ $i++ ];
}

/**
 * Custom HTTP request responses for the failed API get_aspects, get_services and direct _get_aspects_services calls.
 *
 * @return array Responses with either a WP_Error object or and invalid response code.
 */
function wp2d_api_pre_http_request_filter_get_aspects_services_failed() {
	static $i = 0;
	$responses = array(
		new WP_Error( 'wp_error', 'error' ),
		array( 'body' => '', 'response' => array( 'code' => 999, 'message' => 'error' ) ),
	);
	// Since this filter is used by different tests, just keep on looping through the responses.
	return $responses[ $i++ % count( $responses ) ];
}

/**
 * Custom HTTP request responses for the API get_aspects call.
 *
 * @return array Response with the available aspects.
 */
function wp2d_api_pre_http_request_filter_get_aspects() {
	return array(
		'body'     => '"aspects":[{"id":1,"name":"Family","selected":true},{"id":2,"name":"Friends","selected":true}]',
		'response' => array( 'code' => 200, 'message' => 'OK' ),
	);
}

/**
 * Custom HTTP request responses for the API get_services call.
 *
 * @return array Response with the available services.
 */
function wp2d_api_pre_http_request_filter_get_services() {
	return array(
		'body'     => '"configured_services":["facebook","twitter"]',
		'response' => array( 'code' => 200, 'message' => 'OK' ),
	);
}
