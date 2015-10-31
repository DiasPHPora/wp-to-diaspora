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
 * Custom HTTP request responses for the API login.
 *
 * @return array Response for either the sign-in or bookmarklet requests.
 */
function wp2d_api_pre_http_request_filter_login() {
	// Create a static array of responses and return them one by one.
	static $responses = array(
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
	static $responses = array(
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

	return array_shift( $responses );
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



