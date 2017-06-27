<?php
/**
 * Plugin Helpers.
 *
 * @package WP_To_Diaspora\Helpers
 * @since   1.3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Various helper methods.
 */
class WP2D_Helpers {

	/**
	 * Debug text that get's accumulated before output.
	 *
	 * @var string
	 */
	private static $_debugging = '';

	/**
	 * Add a line to the debug output. Include the stack trace to see where it's coming from.
	 *
	 * @param string $text Text to add.
	 *
	 * @return bool
	 */
	public static function add_debugging( $text ) {
		// Make sure we're in debug mode.
		if ( defined( 'WP2D_DEBUGGING' ) && true === WP2D_DEBUGGING ) {
			$d = '';
			foreach ( debug_backtrace() as $dbt ) {
				extract( $dbt );
				// Only trace back as far as the plugin goes.
				if ( strstr( $file, plugin_dir_path( __DIR__ ) ) ) {
					$d = sprintf( "%s%s%s [%s:%s]\n", $class, $type, $function, basename( $file ), $line ) . $d;
				}
			}

			self::$_debugging .= sprintf( "%s\n%s\n", date( 'Y.m.d H:i:s' ), $d . $text );

			return true;
		}

		return false;
	}

	/**
	 * Return the debug output.
	 *
	 * @return string The debug output.
	 */
	public static function get_debugging() {
		if ( defined( 'WP2D_DEBUGGING' ) && true === WP2D_DEBUGGING ) {
			return self::$_debugging;
		}

		return false;
	}

	/**
	 * Convert a string with comma seperated values to an array.
	 *
	 * @todo Make $input by value.
	 *
	 * @param array|string $input The string to be converted.
	 *
	 * @return array The converted array.
	 */
	public static function str_to_arr( &$input ) {
		if ( ! is_array( $input ) ) {
			// Explode string > Trim each entry > Remove blanks > Re-index array.
			$input = array_values( array_filter( array_map( 'trim', explode( ',', $input ) ) ) );
		} else {
			// If we're already an array, make sure we return it clean.
			self::arr_to_str( $input );
			self::str_to_arr( $input );
		}

		return $input;
	}

	/**
	 * Convert an array to a string with comma seperated values.
	 *
	 * @todo Make $input by value.
	 *
	 * @param array|string $input The array to be converted.
	 *
	 * @return string The converted string.
	 */
	public static function arr_to_str( &$input ) {
		if ( is_array( $input ) ) {
			// Trim each entry > Remove blanks > Implode them together.
			$input = implode( ',', array_filter( array_map( 'trim', $input ) ) );
		} else {
			// If we're already a string, make sure we return it clean.
			self::str_to_arr( $input );
			self::arr_to_str( $input );
		}

		return $input;
	}

	/**
	 * Encrypt the passed string with the passed key.
	 *
	 * @param string $input String to be encrypted.
	 * @param string $key   The key used for the encryption.
	 *
	 * @return string The encrypted string.
	 */
	public static function encrypt( $input, $key = AUTH_KEY ) {
		if ( null === $input || '' === $input ) {
			return false;
		}
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SELECT HEX(AES_ENCRYPT(%s,%s))', $input, $key ) );
	}

	/**
	 * Decrypt the passed string with the passed key.
	 *
	 * @param string $input String to be decrypted.
	 * @param string $key   The key used for the decryption.
	 *
	 * @return string The decrypted string.
	 */
	public static function decrypt( $input, $key = AUTH_KEY ) {
		if ( null === $input || '' === $input ) {
			return false;
		}
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SELECT AES_DECRYPT(UNHEX(%s),%s)', $input, $key ) );
	}

	/**
	 * Set up and return an API connection using the currently saved options..
	 *
	 * @return WP2D_API The API object.
	 */
	public static function api_quick_connect() {
		$options   = WP2D_Options::instance();
		$pod       = (string) $options->get_option( 'pod' );
		$is_secure = true;
		$username  = (string) $options->get_option( 'username' );
		$password  = WP2D_Helpers::decrypt( (string) $options->get_option( 'password' ) );

		$api = new WP2D_API( $pod, $is_secure );

		// This is necessary for correct error handling!
		if ( $api->init() ) {
			$api->login( $username, $password );
		}

		if ( $api->has_last_error() ) {
			self::add_debugging( $api->get_last_error() );
		}

		return $api;
	}
}
