<?php
/**
 * API-like class to deal with HTTP(S) requests to diaspora* using WP_HTTP API.
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

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * API class to talk to diaspora*.
 */
class WP2D_API {

	/**
	 * The provider name to display when posting to diaspora*.
	 *
	 * @var string
	 */
	public string $provider = 'WP to diaspora*';

	/**
	 * The last http request error that occurred.
	 *
	 * @var WP_Error|null
	 */
	private ?WP_Error $last_error;

	/**
	 * Security token to be used for making requests.
	 *
	 * @var string
	 */
	private string $token = '';

	/**
	 * Save the cookies for the requests.
	 *
	 * @var array
	 */
	private array $cookies = [];

	/**
	 * The last http request made to diaspora*.
	 * Contains the response and request infos.
	 *
	 * @var object|null
	 */
	private ?object $last_request = null;

	/**
	 * Is this a secure server, use HTTPS instead of HTTP?
	 *
	 * @var bool
	 */
	private bool $is_secure;

	/**
	 * The pod domain to make the http requests to.
	 *
	 * @var string
	 */
	private string $pod;

	/**
	 * Username to use when logging in to diaspora*.
	 *
	 * @var string
	 */
	private string $username = '';

	/**
	 * Password to use when logging in to diaspora*.
	 *
	 * @var string
	 */
	private string $password = '';

	/**
	 * Remember the current login state.
	 *
	 * @var bool
	 */
	private bool $is_logged_in = false;

	/**
	 * The list of user's aspects, which get set after ever http request.
	 *
	 * @var array
	 */
	private array $aspects = [];

	/**
	 * The list of user's connected services, which get set after ever http request.
	 *
	 * @var array
	 */
	private array $services = [];

	/**
	 * List of regex expressions used to filter out details from http request responses.
	 *
	 * @var array
	 */
	private static array $regexes = [
		'token'    => '/content="(.*?)" name="csrf-token"|name="csrf-token" content="(.*?)"/',
		'aspects'  => '/"aspects"\:(\[.*?\])/',
		'services' => '/"configured_services"\:(\[.*?\])/',
	];

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
	 * Constructor to initialise the connection to diaspora*.
	 *
	 * @param string $pod       The pod domain to connect to.
	 * @param bool   $is_secure Is this a secure server? (Default: true).
	 */
	public function __construct( string $pod, bool $is_secure = true ) {
		$this->pod       = $pod;
		$this->is_secure = $is_secure;
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
		if ( null === $this->fetch_token( $force_new_token ) ) {
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

		return true;
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
	 * Fetch the secure token from Diaspora and save it for future use.
	 *
	 * @param bool $force Force to fetch a new token.
	 *
	 * @return string The fetched token.
	 */
	private function fetch_token( bool $force = false ): string {
		if ( '' === $this->token || $force ) {
			// Go directly to the sign in page, as it would redirect to there anyway.
			// Since _request function automatically saves the new token, just call it with no data.
			$this->request( '/users/sign_in' );
		}

		return $this->token;
	}

	/**
	 * Check if the API has been initialised. Otherwise, set the last error.
	 *
	 * @return bool Has the connection been initialised?
	 */
	private function check_init(): bool {
		if ( '' === $this->token ) {
			$this->error( 'wp2d_api_connection_not_initialised', __( 'Connection not initialised.', 'wp-to-diaspora' ) );

			return false;
		}

		return true;
	}

	/**
	 * Check if we're logged in. Otherwise set the last error.
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
		// Has the connection been initialised?
		if ( ! $this->check_init() ) {
			$this->logout();

			return false;
		}

		// Username and password both need to be set.
		if ( ! isset( $username, $password ) || '' === $username || '' === $password ) {
			// Invalid credentials.
			$this->error(
				'wp2d_api_login_failed',
				__( 'Invalid credentials. Please re-save your login info.', 'wp-to-diaspora' ),
				[ 'help_tab' => 'troubleshooting' ]
			);
			$this->logout();

			return false;
		}

		// If we are already logged in and not forcing a relogin, return.
		if ( ! $force &&
			$username === $this->username &&
			$password === $this->password &&
			$this->is_logged_in()
		) {
			return true;
		}

		// Set the newly passed username and password.
		$this->username = $username;
		$this->password = $password;

		// Set up the login parameters.
		$params = [
			'user[username]'     => $this->username,
			'user[password]'     => $this->password,
			'authenticity_token' => $this->fetch_token(),
		];

		$args = [
			'method' => 'POST',
			'body'   => $params,
		];

		// Try to sign in.
		$this->request( '/users/sign_in', $args );

		// Can we load the bookmarklet to make sure we're logged in?
		$response = $this->request( '/bookmarklet' );

		// If the request isn't successful, we are not logged in correctly.
		if ( 200 !== $response?->code || is_wp_error( $response ) ) {
			// Login failed.
			$this->error(
				'wp2d_api_login_failed',
				__( 'Login failed. Check your login details.', 'wp-to-diaspora' ),
				[ 'help_tab' => 'troubleshooting' ]
			);
			$this->logout();

			return false;
		}

		// Login succeeded.
		$this->is_logged_in = true;

		return true;
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
		$this->last_error   = null;
		$this->token        = '';
		$this->cookies      = [];
		$this->last_request = null;
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
		$post_data = [
			'aspect_ids'     => $aspects,
			'status_message' => [
				'text'                  => $text,
				'provider_display_name' => $this->provider,
			],
		];

		// Add any extra data to the post.
		if ( ! empty( $extra_data ) ) {
			$post_data += $extra_data;
		}

		$post_data = wp_json_encode( $post_data );

		$args = [
			'method'  => 'POST',
			'body'    => $post_data,
			'headers' => [
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'X-CSRF-Token' => $this->fetch_token(),
			],
		];

		// Submit the post.
		$response = $this->request( '/status_messages', $args );

		if ( is_wp_error( $response ) ) {
			$this->error( 'wp2d_api_post_failed', $response?->get_error_message() );

			return false;
		}

		$diaspost = json_decode( $response?->body, false );
		if ( 201 !== $response?->code ) {
			$this->error(
				'wp2d_api_post_failed',
				$diaspost->error ?? _x( 'Unknown error occurred.', 'When an unknown error occurred in the WP2D_API object.', 'wp-to-diaspora' )
			);

			return false;
		}

		// Add additional info to our diaspora post object.
		$diaspost->permalink = $this->get_pod_url( '/posts/' . $diaspost->guid );

		return $diaspost;
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
		// Are we logged in?
		if ( ! $this->check_login() ) {
			return false;
		}

		// For now, only deleting posts and comments is allowed.
		if ( ! in_array( $what, [ 'post', 'comment' ], true ) ) {
			$this->error( 'wp2d_api_delete_failed', __( 'You can only delete posts and comments.', 'wp-to-diaspora' ) );

			return false;
		}

		$args = [
			'method'  => 'DELETE',
			'headers' => [
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'X-CSRF-Token' => $this->fetch_token(),
			],
		];

		// Try to delete the post or comment.
		$response = $this->request( '/' . $what . 's/' . $id, $args );

		if ( is_wp_error( $response ) ) {
			$error_message = $response?->get_error_message();
		} else {
			switch ( $response?->code ) {
				case 204:
					return true;
				case 404:
					$error_message = ( 'post' === $what )
						? __( 'The post you tried to delete does not exist.', 'wp-to-diaspora' )
						: __( 'The comment you tried to delete does not exist.', 'wp-to-diaspora' );
					break;
				case 403:
					$error_message = ( 'post' === $what )
						? __( 'The post you tried to delete does not belong to you.', 'wp-to-diaspora' )
						: __( 'The comment you tried to delete does not belong to you.', 'wp-to-diaspora' );
					break;
				case 500:
				default:
					$error_message = _x( 'Unknown error occurred.', 'When an unknown error occurred in the WP2D_API object.', 'wp-to-diaspora' );
					break;
			}
		}

		$this->error( 'wp2d_api_delete_' . $what . '_failed', $error_message );

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
		$this->aspects = $this->get_aspects_services( 'aspects', $this->aspects, $force );

		return is_array( $this->aspects ) ? $this->aspects : false;
	}

	/**
	 * Get the list of connected services.
	 *
	 * @param bool $force Force to fetch new connected services.
	 *
	 * @return array|bool Array of service objects or false.
	 */
	public function get_services( bool $force = false ): bool|array {
		$this->services = $this->get_aspects_services( 'services', $this->services, $force );

		return is_array( $this->services ) ? $this->services : false;
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

		// Fetch the new list if the current list is empty or a reload is forced.
		if ( $force || empty( $list ) ) {
			$response = $this->request( '/bookmarklet' );

			if ( 200 !== $response?->code || is_wp_error( $response ) ) {
				switch ( $type ) {
					case 'aspects':
						$this->error( 'wp2d_api_getting_aspects_failed', __( 'Error loading aspects.', 'wp-to-diaspora' ) );
						break;
					case 'services':
						$this->error( 'wp2d_api_getting_services_failed', __( 'Error loading services.', 'wp-to-diaspora' ) );
						break;
					default:
						$this->error( 'wp2d_api_getting_aspects_services_failed', _x( 'Unknown error occurred.', 'When an unknown error occurred in the WP2D_API object.', 'wp-to-diaspora' ) );
						break;
				}

				return false;
			}

			// Load the aspects or services.
			$raw_list = json_decode( $this->parse_regex( $type, $response?->body ), false );
			if ( is_array( $raw_list ) ) {
				// In case this fetch is forced, empty the list.
				$list = [];

				if ( 'aspects' === $type ) {
					// Add the 'public' aspect, as it's global and not user specific.
					$list['public'] = __( 'Public', 'wp-to-diaspora' );

					// Add all user specific aspects.
					foreach ( $raw_list as $aspect ) {
						$list[ $aspect->id ] = $aspect->name;
					}
				} elseif ( 'services' === $type ) {
					foreach ( $raw_list as $service ) {
						$list[ $service ] = ucfirst( $service );
					}
				}
			}
		}

		return $list;
	}

	/**
	 * Send an http(s) request via WP_HTTP API.
	 *
	 * @see WP_Http::request()
	 *
	 * @param string $url  The URL to request.
	 * @param array  $args Arguments to be posted with the request.
	 *
	 * @return object|null An object containing details about this request.
	 */
	private function request( string $url, array $args = [] ): ?object {
		// Prefix the full pod URL if necessary.
		if ( str_starts_with( $url, '/' ) ) {
			$url = $this->get_pod_url( $url );
		}

		// Disable redirections so we can verify HTTP response codes.
		$defaults = [
			'method'      => 'GET',
			'redirection' => 0,
			'sslverify'   => true,
			'timeout'     => 60,
		];

		// If the certificate bundle has been downloaded manually, use that instead.
		// NOTE: This should actually never be necessary, it's a fallback!
		if ( file_exists( WP2D_DIR . '/cacert.pem' ) ) {
			$defaults['sslcertificates'] = WP2D_DIR . '/cacert.pem';
		}

		// Set the correct cookie.
		if ( ! empty( $this->cookies ) ) {
			$defaults['cookies'] = $this->cookies;
		}

		$args = wp_parse_args( $args, $defaults );

		// Get the response from the WP_HTTP request.
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response;

			return $response;
		}

		// Get the headers and the HTML response.
		$headers = wp_remote_retrieve_headers( $response );
		$body    = wp_remote_retrieve_body( $response );

		// Remember this request.
		$this->last_request           = new stdClass();
		$this->last_request->response = $response;
		$this->last_request->headers  = $headers;
		$this->last_request->body     = $body;
		$this->last_request->message  = wp_remote_retrieve_response_message( $response );
		$this->last_request->code     = wp_remote_retrieve_response_code( $response );

		// Save the new token.
		if ( $token = $this->parse_regex( 'token', $body ) ) {
			$this->token = $token;
		}

		// Save the latest cookies.
		if ( isset( $response['cookies'] ) ) {
			$this->cookies = $response['cookies'];
		}

		// Return the last request details.
		return $this->last_request;
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
			'code'    => $this->last_request->code ?? null,
			'message' => $this->last_request->message ?? null,
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

		return trim( array_pop( $matches ) );
	}
}
