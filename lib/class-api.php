<?php
/**
 * API-like class to deal with HTTP(S) requests to diaspora* using cURL.
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
 * @since 1.2.7
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * API class to talk to diaspora*.
 */
class WP2D_API {

	/**
	 * The last http request error that occurred.
	 *
	 * @var string
	 */
	public $last_error;

	/**
	 * The provider name to display when posting to diaspora*.
	 *
	 * @var string
	 */
	public $provider = 'WP to diaspora*';

	/**
	 * Security token to be used for making requests.
	 *
	 * @var string
	 */
	private $_token;

	/**
	 * The last http request made to diaspora*.
	 * Contains the response and request infos.
	 *
	 * @var object
	 */
	private $_last_request;

	/**
	 * Is this a secure server, use HTTPS instead of HTTP?
	 *
	 * @var boolean
	 */
	private $_is_secure;

	/**
	 * The pod domain to make the http requests to.
	 *
	 * @var string
	 */
	private $_pod;

	/**
	 * Username to use when logging in to diaspora*.
	 *
	 * @var string
	 */
	private $_username;

	/**
	 * Password to use when logging in to diaspora*.
	 *
	 * @var string
	 */
	private $_password;

	/**
	 * Save the cookie string for the requests.
	 *
	 * @var string
	 */
	private $_cookie;

	/**
	 * Remember the current login state.
	 *
	 * @var boolean
	 */
	private $_is_logged_in = false;

	/**
	 * The list of user's aspects, which get set after ever http request.
	 *
	 * @var array
	 */
	private $_aspects = array();

	/**
	 * The list of user's connected services, which get set after ever http request.
	 *
	 * @var array
	 */
	private $_services = array();

	/**
	 * List of regex expressions used to filter out details from http request responses.
	 *
	 * @var array
	 */
	private $_regexes = array(
		'token'    => '/content="(.*?)" name="csrf-token"|name="csrf-token" content="(.*?)"/',
		'cookie'   => '/Set-Cookie: (.*?);/',
		'aspects'  => '/"aspects"\:(\[.+?\])/',
		'services' => '/"configured_services"\:(\[.+?\])/',
	);

	/**
	 * The full pod url, with the used protocol.
	 *
	 * @param string $path Path to add to the pod url.
	 * @return string Full pod url.
	 */
	public function get_pod_url( $path = '' ) {
		// Add a slash to the beginning?
		if ( '' !== $path && '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		return sprintf( 'http%s://%s%s', ( $this->_is_secure ) ? 's' : '', $this->_pod, $path );
	}

	/**
	 * Constructor to initialise the connection to diaspora*.
	 *
	 * @param string  $pod       The pod domain to connect to.
	 * @param boolean $is_secure Is this a secure server? (Default: true).
	 */
	public function __construct( $pod, $is_secure = true ) {
		// Set class variables.
		$this->_pod       = $pod;
		$this->_is_secure = (bool) $is_secure;
	}

	/**
	 * Initialise the connection to diaspora*. The pod and protocol can be changed by passing new parameters.
	 * Check if we can connect to the pod to retrieve the token.
	 *
	 * @param string  $pod       Pod domain to connect to, if it should be changed.
	 * @param boolean $is_secure Is this a secure server? (Default: true).
	 * @return boolean True if we could get the token, else false.
	 */
	public function init( $pod = null, $is_secure = true ) {
		// If we are changing pod, we need to fetch a new token.
		$force_new_token = false;

		// Change the pod we are connecting to?
		if ( isset( $pod ) && ( $this->_pod !== $pod || $this->_is_secure !== $is_secure ) ) {
			$this->_pod       = $pod;
			$this->_is_secure = (bool) $is_secure;
			$force_new_token  = true;
		}

		// Get and save the token.
		if ( null === $this->_fetch_token( $force_new_token ) ) {
			// Code 60 is a CA certificate problem.
			if ( 60 === $this->_last_request->errno ) {
				$this->last_error = sprintf(
					_x( 'There seems to be a problem with your server\'s CA certificate bundle. %sHelp%s', 'Placeholders are HTML for a link.', 'wp-to-diaspora' ),
					'<a href="#" class="open-help-tab" data-help-tab="ssl">', '</a>'
				);
			} else {
				$this->last_error = sprintf(
					_x( 'Failed to initialise connection to pod "%s".', 'Placeholder is the full pod URL.', 'wp-to-diaspora' ),
					$this->get_pod_url()
				);
			}
			return false;
		}
		return true;
	}

	/**
	 * Fetch the secure token from Diaspora and save it for future use.
	 *
	 * @param boolean $force Force to fetch a new token.
	 * @return string The fetched token.
	 */
	private function _fetch_token( $force = false ) {
		if ( ! isset( $this->_token ) || (bool) $force ) {
			// Go directly to the sign in page, as it would redirect to there anyway.
			// Since _http_request function automatically saves the new token, just call it with no data.
			$this->_http_request( '/users/sign_in' );
		}
		return $this->_token;
	}

	/**
	 * Check if we're logged in. Otherwise set the last error.
	 *
	 * @return boolean Are we logged in already?
	 */
	private function _check_login() {
		if ( ! $this->is_logged_in() ) {
			$this->last_error = __( 'Not logged in.', 'wp-to-diaspora' );
			return false;
		}
		return true;
	}

	/**
	 * Check if we are logged in.
	 *
	 * @return boolean Are we logged in already?
	 */
	public function is_logged_in() {
		return $this->_is_logged_in;
	}

	/**
	 * Log in to diaspora*.
	 *
	 * @param string  $username Username used for login.
	 * @param string  $password Password used for login.
	 * @param boolean $force    Force a new login even if we are already logged in.
	 * @return boolean Did the login succeed?
	 */
	public function login( $username, $password, $force = false ) {
		// Are we trying to log in as a different user?
		if ( $username !== $this->_username || $password !== $this->_password ) {
			$this->_is_logged_in = false;
		}

		// If we are already logged in and not forcing a relogin, return.
		if ( $this->is_logged_in() && ! (bool) $force ) {
			return true;
		}

		// Set the uername and password.
		$this->_username = ( isset( $username ) && '' !== $username ) ? $username : null;
		$this->_password = ( isset( $password ) && '' !== $password ) ? $password : null;

		// Do we have the necessary credentials?
		if ( ! isset( $this->_username, $this->_password ) ) {
			$this->_is_logged_in = false;
			return false;
		}

		// Set up the login parameters.
		$params = array(
			'user[username]'     => $this->_username,
			'user[password]'     => $this->_password,
			'authenticity_token' => $this->_fetch_token(),
		);

		// Try to sign in.
		$this->_http_request( '/users/sign_in', $params );

		// Can we load the bookmarklet to make sure we're logged in?
		$req = $this->_http_request( '/bookmarklet' );

		// If the request isn't successful, we are not logged in correctly.
		if ( 200 !== $req->info['http_code'] ) {
			// Login failed.
			$this->last_error = __( 'Login failed. Check your login details.', 'wp-to-diaspora' );
			return false;
		}

		// Login succeeded.
		$this->_is_logged_in = true;
		return true;
	}

	/**
	 * Post to diaspora*.
	 *
	 * @param string       $text       The text to post.
	 * @param array|string $aspects    The aspects to post to. Array or comma seperated ids.
	 * @param array        $extra_data Any extra data to be added to the post call.
	 * @return boolean|object Return the response data of the new diaspora* post if successfully posted, else false.
	 */
	public function post( $text, $aspects = 'public', $extra_data = array() ) {
		// Are we logged in?
		if ( ! $this->_check_login() ) {
			return false;
		}

		// Put the aspects into a clean array.
		$aspects = array_filter( WP2D_Helpers::str_to_arr( $aspects ) );

		// If no aspects have been selected or the public one is also included, choose public only.
		if ( empty( $aspects ) || in_array( 'public', $aspects ) ) {
			$aspects = 'public';
		}

		// Prepare post data.
		$post_data = array(
			'aspect_ids'     => $aspects,
			'status_message' => array(
				'text' => $text,
				'provider_display_name' => $this->provider,
			),
		);

		// Add any extra data to the post.
		if ( ! empty( $extra_data ) ) {
				$post_data += $extra_data;
		}

		// Prepare headers.
		// MUST fetch new token for this to work!
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'X-CSRF-Token: ' . $this->_fetch_token( true ),
		);

		// Submit the post.
		$req = $this->_http_request( '/status_messages', wp_json_encode( $post_data ), $headers );
		$response = json_decode( $req->response );
		if ( 201 !== $req->info['http_code'] ) {
			$this->last_error = ( isset( $response->error ) ) ? $response->error : _x( 'Unknown error occurred.', 'When an unknown error occurred in the WP2D_API object.', 'wp-to-diaspora' );
			return false;
		}

		// Add additional info to our response.
		$response->permalink = $this->get_pod_url( '/posts/' . $response->guid );

		return $response;
	}


	/**
	 * Get the list of aspects.
	 *
	 * @param boolean $force Force to fetch new aspects.
	 * @return array Array of aspect objects.
	 */
	public function get_aspects( $force = false ) {
		return ( $this->_get_aspects_services( 'aspects', $this->_aspects, $force ) ) ? $this->_aspects : false;
	}

	/**
	 * Get the list of connected services.
	 *
	 * @param boolean $force Force to fetch new connected services.
	 * @return array Array of service objects.
	 */
	public function get_services( $force = false ) {
		return ( $this->_get_aspects_services( 'services', $this->_services, $force ) ) ? $this->_services : false;
	}

	/**
	 * Get the list of aspects or connected services.
	 *
	 * @todo  No need for the switch case, just make a simple if when last_error gets set.
	 *
	 * @param string  $type  Type of list to get.
	 * @param array   $list  The current list of items.
	 * @param boolean $force Force to fetch new list.
	 * @return boolean Was the list fetched successfully?
	 */
	private function _get_aspects_services( $type, $list, $force ) {
		$error_message = '';
		switch ( $type ) {
			case 'aspects':
				$error_message = __( 'Error loading aspects.', 'wp-to-diaspora' );
				break;
			case 'services':
				$error_message = __( 'Error loading services.', 'wp-to-diaspora' );
				break;
		}

		if ( ! $this->_check_login() ) {
			return false;
		}

		// Fetch the new list if the current list is empty or a reload is forced.
		if ( empty( $list ) || (bool) $force ) {
			$req = $this->_http_request( '/bookmarklet' );
			if ( 200 !== $req->info['http_code'] ) {
				$this->last_error = $error_message;
				return false;
			}
			// No need to parse the list, as it get's done for each http request anyway.
		}

		return true;
	}

	/**
	 * Send an http(s) request via cURL.
	 *
	 * @param string       $url     The URL to request.
	 * @param array|string $data    Data to be posted with the request.
	 * @param array        $headers Headers to assign to the request.
	 * @return object An object containing details about this request.
	 */
	private function _http_request( $url, $data = array(), $headers = array() ) {
		// Prefix the full pod URL if necessary.
		if ( 0 === strpos( $url, '/' ) ) {
			$url = $this->get_pod_url( $url );
		}

		// Call address via cURL.
		$ch = curl_init( $url );

		// Set up cURL options.
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		if ( file_exists( WP2D_DIR . '/cacert.pem' ) ) {
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
			curl_setopt( $ch, CURLOPT_CAINFO, WP2D_DIR . '/cacert.pem' );
		}

		if ( ! empty( $this->_cookie ) ) {
			curl_setopt( $ch, CURLOPT_COOKIE, $this->_cookie );
		}

		// Add the passed headers.
		if ( ! empty( $headers ) ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		}

		// Add the passed post data.
		if ( ! empty( $data ) ) {
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		}

		// Get the response from the cURL call.
		$response = curl_exec( $ch );

		// Get the headers and the html response.
		$header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		$headers  = substr( $response, 0, $header_size );
		$response = substr( $response, $header_size );

		// Remember this request.
		$this->_last_request = new stdClass();
		$this->_last_request->headers  = $headers;
		$this->_last_request->response = $response;
		$this->_last_request->error    = curl_error( $ch );
		$this->_last_request->errno    = curl_errno( $ch );
		$this->_last_request->info     = curl_getinfo( $ch );
		curl_close( $ch );

		// Save the new token.
		if ( $token = $this->_parse_regex( 'token', $response ) ) {
			$this->_token = $token;
		}

		// Save the latest cookie.
		if ( $cookie = $this->_parse_regex( 'cookie', $headers ) ) {
			$this->_cookie = $cookie;
		}

		// Once we're logged in, can we load new aspects and services while we're at it?
		if ( $this->is_logged_in() ) {
			// Load the aspects.
			if ( empty( $this->_aspects ) && $aspects_raw = json_decode( $this->_parse_regex( 'aspects', $response ) ) ) {
				// Add the 'public' aspect, as it's global and not user specific.
				$aspects = array( 'public' => __( 'Public' ) );

				// Create an array of all the aspects and save them to the settings.
				foreach ( $aspects_raw as $aspect ) {
					$aspects[ $aspect->id ] = $aspect->name;
				}
				$this->_aspects = $aspects;
			}

			// Load the services.
			if ( empty( $this->_services ) && $services_raw = json_decode( $this->_parse_regex( 'services', $response ) ) ) {
				$services = array();
				foreach ( $services_raw as $service ) {
					$services[ $service ] = ucfirst( $service );
				}
				$this->_services = $services;
			}
		}

		// Add debug info.
		WP2D_Helpers::add_debugging( sprintf( "code %s on %s\n", $this->_last_request->info['http_code'], $this->_last_request->info['url'] ) );

		// Return the last request details.
		return $this->_last_request;
	}

	/**
	 * Parse the regex and return the found string.
	 *
	 * @param string $regex   Shorthand of a saved regex or a custom regex.
	 * @param string $content Text to parse the regex with.
	 * @return string The found string, or an empty string.
	 */
	private function _parse_regex( $regex, $content ) {
		// Use a shorthand regex if available.
		if ( array_key_exists( $regex, $this->_regexes ) ) {
			$regex = $this->_regexes[ $regex ];
		}

		preg_match( $regex, $content, $matches );
		return trim( array_pop( $matches ) );
	}
}
