<?php
/**
 * API-like class to deal with HTTP(S) requests to diaspora* using Guzzle.
 *
 * Basic functionality includes:
 * - Logging in to diaspora*
 * - Fetching a user's aspects and connected services
 * - Posting to diaspora*
 *
 * Ideas in this class are based on classes from:
 * https://github.com/Faldrian/WP-diaspora-postwidget/blob/master/wp-diaspora-postwidget/diasphp.php -- Thanks, Faldrian!
 * https://github.com/meitar/diasposter/blob/master/lib/Diaspora_Connection.php -- Thanks, Meitar
 *
 * Which in turn are based on:
 * https://github.com/cocreature/diaspy/blob/master/client.py -- Thanks, Moritz
 *
 * @package WP_To_Diaspora\API
 * @since   1.2.7
 */

use WP2D\Dependencies\GuzzleHttp\Client;
use WP2D\Dependencies\GuzzleHttp\Exception\ClientException;
use WP2D\Dependencies\GuzzleHttp\HandlerStack;
use WP2D\Dependencies\GuzzleHttp\Middleware;
use WP2D\Dependencies\Psr\Http\Client\ClientInterface;
use WP2D\Dependencies\Psr\Http\Message\RequestInterface;
use WP2D\Dependencies\Psr\Http\Message\ResponseInterface;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * API class to talk to diaspora*.
 */
class WP2D_API {

	/**
	 * @var string The provider name to display when posting to diaspora*.
	 */
	public string $provider = 'WP to diaspora*';

	/**
	 * @var ClientInterface Guzzle client for HTTP requests.
	 */
	protected ClientInterface $client;

	/**
	 * @var WP_Error|null The last HTTP request error that occurred.
	 */
	private ?WP_Error $last_error = null;

	/**
	 * @var string CSRF token to be used for making requests.
	 */
	private string $csrf_token = '';

	/**
	 * @var string CSRF token parameter name.
	 */
	private string $csrf_param = '';

	/**
	 * @var ResponseInterface|null The last http response returned from diaspora*.
	 */
	private ?ResponseInterface $last_response = null;

	/**
	 * @var string Username to use when logging in to diaspora*.
	 */
	private string $username = '';

	/**
	 * @var string Password to use when logging in to diaspora*.
	 */
	private string $password = '';

	/**
	 * @var bool Remember the current login state.
	 */
	private bool $is_logged_in = false;

	/**
	 * @var array The list of user's aspects, which get set after ever http request.
	 */
	private array $aspects = [];

	/**
	 * @var array The list of user's connected services, which get set after ever http request.
	 */
	private array $services = [];

	/**
	 * @var array List of regex expressions used to filter out details from http request responses.
	 */
	private static array $regexes = [
		'csrf_param' => '/content="(.*?)" name="csrf-param"|name="csrf-param" content="(.*?)"/',
		'csrf_token' => '/content="(.*?)" name="csrf-token"|name="csrf-token" content="(.*?)"/',
		'aspects'    => '/"aspects"\:(\[.*?\])/',
		'services'   => '/"configured_services"\:(\[.*?\])/',
	];

	/**
	 * Constructor to initialise the connection to diaspora*.
	 *
	 * @param string $pod       The pod domain to connect to.
	 * @param bool   $is_secure Is this a secure server? (Default: true).
	 */
	public function __construct(
		private string $pod,
		private bool $is_secure = true
	) {
		$this->client = new Client( [
			'base_uri'        => $this->get_pod_url(),
			'cookies'         => true, // We need cookies for session.
			'allow_redirects' => false, // Don't redirect to validate return codes.
			'handler'         => $this->get_guzzle_handler(),
		] );
	}

	/**
	 * The full pod url, with the used protocol.
	 *
	 * @param string $path Path to add to the pod url.
	 *
	 * @return string Full pod url.
	 */
	public function get_pod_url( string $path = '' ): string {
		$path = trim( $path, ' /' );

		// Add a slash to the beginning?
		if ( '' !== $path ) {
			$path = '/' . $path;
		}

		return sprintf( 'http%s://%s%s', $this->is_secure ? 's' : '', $this->pod, $path );
	}

	/**
	 * Initialise the connection to diaspora*. The pod and protocol can be changed by passing new parameters.
	 * Check if we can connect to the pod to retrieve the token.
	 *
	 * @param string $pod       Pod domain to connect to, if it should be changed.
	 * @param bool   $is_secure Is this a secure server? (Default: true).
	 *
	 * @return bool True if we could get the token, else false.
	 */
	public function init( string $pod = '', bool $is_secure = true ): bool {
		// If we are changing pod, we need to fetch a new token.
		$force_new_token = false;

		// When initialising a connection, clear the last error.
		// This is important with multiple inits.
		$this->last_error = null;

		// Change the pod we are connecting to?
		if ( '' !== $pod && ( $this->pod !== $pod || $this->is_secure !== $is_secure ) ) {
			$this->pod       = $pod;
			$this->is_secure = $is_secure;
			$force_new_token = true;
		}

		// Get and save the token.
		if ( '' !== $this->load_csrf_token( $force_new_token ) ) {
			return true;
		}

		$error = $this->has_last_error() ? ' ' . $this->get_last_error() : '';
		$this->error( 'wp2d_api_init_failed',
			sprintf(
				_x( 'Failed to initialise connection to pod "%s".', 'Placeholder is the full pod URL.', 'wp-to-diaspora' ),
				$this->get_pod_url()
			) . $error,
			[ 'help_tab' => 'troubleshooting' ]
		);

		return false;
	}

	/**
	 * Check if there is an API error around.
	 *
	 * @return bool If there is an API error around.
	 */
	public function has_last_error(): bool {
		return is_wp_error( $this->last_error );
	}

	/**
	 * Get the last API error object.
	 *
	 * @param bool $clear If the error should be cleared after returning it.
	 *
	 * @return WP_Error|null The last API error object or null.
	 */
	public function get_last_error_object( bool $clear = true ): ?WP_Error {
		$last_error = $this->last_error;
		if ( $clear ) {
			$this->last_error = null;
		}

		return $last_error;
	}

	/**
	 * Get the last API error message.
	 *
	 * @param bool $clear If the error should be cleared after returning it.
	 *
	 * @return string The last API error message.
	 */
	public function get_last_error( bool $clear = false ): string {
		$last_error = $this->has_last_error() ? $this->last_error->get_error_message() : '';
		if ( $clear ) {
			$this->last_error = null;
		}

		return $last_error;
	}

	/**
	 * Fetch the CSRF token from Diaspora and save it for future use.
	 *
	 * @param bool $force Force to fetch a new token.
	 *
	 * @return string The loaded CSRF token.
	 */
	private function load_csrf_token( bool $force = false ): string {
		if ( '' === $this->csrf_token || $force ) {
			try {
				/**
				 * @see get_guzzle_handler() Token is extracted as part of Handler Stack
				 */
				$this->client->get( '/users/sign_in' );
			} catch ( Throwable ) {
				$this->csrf_token = '';
			}
		}

		return $this->csrf_token;
	}

	/**
	 * Check if the API has been initialised. Otherwise, set the last error.
	 *
	 * @return bool Has the connection been initialised?
	 */
	private function check_init(): bool {
		if ( '' === $this->csrf_token ) {
			$this->error( 'wp2d_api_connection_not_initialised', __( 'Connection not initialised.', 'wp-to-diaspora' ) );

			return false;
		}

		return true;
	}

	/**
	 * Check if we're logged in. Otherwise, set the last error.
	 *
	 * @return bool Are we logged in already?
	 */
	private function check_login(): bool {
		if ( ! $this->check_init() ) {
			return false;
		}

		if ( ! $this->is_logged_in() ) {
			$this->error( 'wp2d_api_not_logged_in', __( 'Not logged in.', 'wp-to-diaspora' ) );

			return false;
		}

		return true;
	}

	/**
	 * Check if we are logged in.
	 *
	 * @return bool Are we logged in already?
	 */
	public function is_logged_in(): bool {
		return $this->is_logged_in;
	}

	/**
	 * Log in to diaspora*.
	 *
	 * @param string $username Username used for login.
	 * @param string $password Password used for login.
	 * @param bool   $force    Force a new login even if we are already logged in.
	 *
	 * @return bool Did the login succeed?
	 */
	public function login( string $username, string $password, bool $force = false ): bool {
		if ( ! $this->check_init() ) {
			$this->logout();

			return false;
		}

		// Username and password both need to be set.
		if ( ! isset( $username, $password ) || '' === $username || '' === $password ) {
			$this->error(
				'wp2d_api_login_failed',
				__( 'Invalid credentials. Please re-save your login info.', 'wp-to-diaspora' ),
				[ 'help_tab' => 'troubleshooting' ]
			);
			$this->logout();

			return false;
		}

		// If we are already logged in and not forcing a relogin, return.
		if ( ! $force
			&& $username === $this->username
			&& $password === $this->password
			&& $this->is_logged_in()
		) {
			return true;
		}

		// Set the newly passed username and password.
		$this->username = $username;
		$this->password = $password;

		// Try to sign in.
		try {
			$this->client->post( '/users/sign_in', [
				'form_params' => [
					'user'            => compact( 'username', 'password' ),
					$this->csrf_param => $this->csrf_token,
				],
			] );

			$response = $this->client->get( '/bookmarklet' );

			$this->is_logged_in = 200 === $response->getStatusCode();
		} catch ( Throwable ) {
			$this->logout();
		}

		if ( ! $this->is_logged_in ) {
			$this->error(
				'wp2d_api_login_failed',
				__( 'Login failed. Check your login details.', 'wp-to-diaspora' ),
				[ 'help_tab' => 'troubleshooting' ]
			);
		}

		return $this->is_logged_in;
	}

	/**
	 * Perform a logout, resetting all login info.
	 *
	 * @since 1.6.0
	 */
	public function logout(): void {
		$this->is_logged_in = false;
		$this->username     = '';
		$this->password     = '';
		$this->aspects      = [];
		$this->services     = [];
	}

	/**
	 * Perform a de-initialisation, resetting all class variables.
	 *
	 * @since 1.7.0
	 */
	public function deinit(): void {
		$this->logout();
		$this->csrf_token    = '';
		$this->last_error    = null;
		$this->last_response = null;
	}

	/**
	 * Post to diaspora*.
	 *
	 * @param string       $text       The text to post.
	 * @param array|string $aspects    The aspects to post to. Array or comma separated ids.
	 * @param array        $extra_data Any extra data to be added to the post call.
	 *
	 * @return bool|object Return the response data of the new diaspora* post if successfully posted, else false.
	 */
	public function post( string $text, array|string $aspects = 'public', array $extra_data = [] ): object|bool {
		// Are we logged in?
		if ( ! $this->check_login() ) {
			return false;
		}

		// Put the aspects into a clean array.
		$aspects = array_filter( WP2D_Helpers::str_to_arr( $aspects ) );

		// If no aspects have been selected or the public one is also included, choose public only.
		if ( empty( $aspects ) || in_array( 'public', $aspects, true ) ) {
			$aspects = 'public';
		}

		// Prepare post data.
		$post_data = array_merge( [
			'aspect_ids'     => $aspects,
			'status_message' => [
				'text'                  => $text,
				'provider_display_name' => $this->provider,
			],
		], $extra_data );

		// Submit the post.
		try {
			$response = $this->client->post( '/status_messages', [
				'json' => $post_data,
			] );

			if ( 201 === $response->getStatusCode() ) {
				$diaspost = json_decode( (string) $response->getBody(), false );

				$diaspost->permalink = $this->get_pod_url( "/posts/{$diaspost->guid}" );

				return $diaspost;
			}
		} catch ( ClientException $e ) {
			$error_message = json_decode( (string) $e->getResponse()?->getBody(), false )?->error;
		} catch ( Throwable $e ) {
			$error_message = $e->getMessage();
		}

		$this->error(
			'wp2d_api_post_failed',
			$error_message ?? _x( 'Unknown error occurred.', 'When an unknown error occurred in the WP2D_API object.', 'wp-to-diaspora' ),
		);

		return false;
	}

	/**
	 * Delete a post or comment from diaspora*.
	 *
	 * @since 1.6.0
	 *
	 * @param string $what What to delete, 'post' or 'comment'.
	 * @param string $id   The ID of the post or comment to delete.
	 *
	 * @return bool If the deletion was successful.
	 */
	public function delete( string $what, string $id ): bool {
		if ( ! $this->check_login() ) {
			return false;
		}

		// For now, only deleting posts and comments is allowed.
		if ( ! in_array( $what, [ 'post', 'comment' ], true ) ) {
			$this->error( 'wp2d_api_delete_failed', __( 'You can only delete posts and comments.', 'wp-to-diaspora' ) );

			return false;
		}

		// Try to delete the post or comment.
		try {
			$response = $this->client->delete( "/{$what}s/{$id}" );

			if ( 204 === $response->getStatusCode() ) {
				return true;
			}

			$error_message = $response->getReasonPhrase();
		} catch ( ClientException $e ) {
			$error_message = match ( $what . $e->getResponse()?->getStatusCode() ) {
				'comment403' => __( 'The comment you tried to delete does not belong to you.', 'wp-to-diaspora' ),
				'comment404' => __( 'The comment you tried to delete does not exist.', 'wp-to-diaspora' ),
				'post403' => __( 'The post you tried to delete does not belong to you.', 'wp-to-diaspora' ),
				'post404' => __( 'The post you tried to delete does not exist.', 'wp-to-diaspora' ),
				default => (string) $e->getResponse()->getBody()
			};
		} catch ( Throwable $e ) {
			$error_message = $e->getMessage();
		}

		$this->error(
			"wp2d_api_delete_{$what}_failed",
			$error_message ?? _x( 'Unknown error occurred.', 'When an unknown error occurred in the WP2D_API object.', 'wp-to-diaspora' )
		);

		return false;
	}

	/**
	 * Get the list of aspects.
	 *
	 * @param bool $force Force to fetch new aspects.
	 *
	 * @return array|bool Array of aspect objects or false.
	 */
	public function get_aspects( bool $force = false ): bool|array {
		$aspects = $this->get_aspects_services( 'aspects', $this->aspects, $force );
		if ( false !== $aspects ) {
			$this->aspects = $aspects;
		}

		return $aspects;
	}

	/**
	 * Get the list of connected services.
	 *
	 * @param bool $force Force to fetch new connected services.
	 *
	 * @return array|bool Array of service objects or false.
	 */
	public function get_services( bool $force = false ): bool|array {
		$services = $this->get_aspects_services( 'services', $this->services, $force );
		if ( false !== $services ) {
			$this->services = $services;
		}

		return $services;
	}

	/**
	 * Get the list of aspects or connected services.
	 *
	 * @param string $type  Type of list to get.
	 * @param array  $list  The current list of items.
	 * @param bool   $force Force to fetch new list.
	 *
	 * @return array|bool List of fetched aspects or services, or false.
	 */
	private function get_aspects_services( string $type, array $list, bool $force ): bool|array {
		if ( ! $this->check_login() ) {
			return false;
		}

		if ( $list && ! $force ) {
			return $list;
		}

		try {
			$response = $this->client->get( '/bookmarklet' );
		} catch ( Throwable ) {
			match ( $type ) {
				'aspects' => $this->error( 'wp2d_api_getting_aspects_failed', __( 'Error loading aspects.', 'wp-to-diaspora' ) ),
				'services' => $this->error( 'wp2d_api_getting_services_failed', __( 'Error loading services.', 'wp-to-diaspora' ) ),
				default => $this->error( 'wp2d_api_getting_aspects_services_failed', _x( 'Unknown error occurred.', 'When an unknown error occurred in the WP2D_API object.', 'wp-to-diaspora' ) ),
			};

			return false;
		}

		// Load the aspects or services.
		$raw_list = json_decode( $this->parse_regex( $type, (string) $response->getBody() ), false );
		if ( is_array( $raw_list ) ) {
			$list = [];

			if ( 'aspects' === $type ) {
				// Add the 'public' aspect, as it's global and not user specific.
				$list['public'] = __( 'Public', 'wp-to-diaspora' );

				// Add all user specific aspects.
				foreach ( $raw_list as $aspect ) {
					$list[ $aspect->id ] = $aspect->name;
				}
			}

			if ( 'services' === $type ) {
				foreach ( $raw_list as $service ) {
					$list[ $service ] = ucfirst( $service );
				}
			}
		}

		return $list;
	}

	/**
	 * Get the Handler Stack for Guzzle to extend Requests and Responses.
	 *
	 * @since 4.0.0
	 *
	 * @return HandlerStack
	 */
	private function get_guzzle_handler(): HandlerStack {
		$handler = HandlerStack::create();

		$handler = apply_filters( 'wp2d_guzzle_handler', $handler );

		$handler->push( Middleware::mapRequest( function ( RequestInterface $request ) {
			if ( 'DELETE' === $request->getMethod() || '/status_messages' === $request->getUri()->getPath() ) {
				return $request
					->withHeader( 'Accept', 'application/json' )
					->withHeader( 'Content-Type', 'application/json' )
					->withHeader( 'X-CSRF-Token', $this->csrf_token );
			}

			return $request;
		} ) );

		// Save the most recent response and token after each request.
		$handler->push( Middleware::mapResponse( function ( ResponseInterface $response ) {
			$body = (string) $response->getBody();

			if ( $csrf_token = $this->parse_regex( 'csrf_token', $body ) ) {
				$this->csrf_token = $csrf_token;
			}

			if ( $csrf_param = $this->parse_regex( 'csrf_param', $body ) ) {
				$this->csrf_param = $csrf_param;
			}

			$this->last_response = $response;

			return $response;
		} ) );

		return $handler;
	}

	/**
	 * Helper method to set the last occurred error.
	 *
	 * @since 1.6.0
	 *
	 * @see   WP_Error::__construct()
	 *
	 * @param string|int $code    Error code.
	 * @param string     $message Error message.
	 * @param mixed      $data    Error data.
	 */
	private function error( string|int $code, string $message, mixed $data = '' ): void {
		// Always add the code and message of the last request.
		$data = array_merge( array_filter( (array) $data ), [
			'code'    => $this->last_response?->getStatusCode(),
			'message' => $this->last_response?->getReasonPhrase(),
		] );

		$this->last_error = new WP_Error( $code, $message, $data );
	}

	/**
	 * Parse the regex and return the found string.
	 *
	 * @param string $regex   Shorthand of a saved regex or a custom regex.
	 * @param string $content Text to parse the regex with.
	 *
	 * @return string The found string, or an empty string.
	 */
	private function parse_regex( string $regex, string $content ): string {
		// Use a shorthand regex if available.
		if ( array_key_exists( $regex, self::$regexes ) ) {
			$regex = self::$regexes[ $regex ];
		}

		preg_match( $regex, $content, $matches );

		return trim( array_pop( $matches ) ?? '' );
	}
}
