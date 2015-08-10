<?php
/**
 * Plugin Helpers.
 *
 * @package WP_To_Diaspora
 * @subpackage Helpers
 * @since 1.3.0
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
   */
  public static function add_debugging( $text ) {
    // Make sure we're in debug mode.
    if ( defined( 'WP2D_DEBUGGING' ) && true === WP2D_DEBUGGING ) {
      $d = '';
      foreach ( debug_backtrace() as $dbt ) {
        extract( $dbt );
        // Only trace back as far as the plugin goes.
        if ( strstr( $file, plugin_dir_path( dirname( __FILE__ ) ) ) ) {
          $d = sprintf( "%s%s%s[%s:%s]\n", $class, $type, $function, basename( $file ), $line ) . $d;
        }
      }

      self::$_debugging .= sprintf( "%s - %s\n", date( 'YmdHis' ), $d . $text );
    }
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
  }

  /**
   * Convert a string with comma seperated values to an array.
   *
   * @param  string|array &$input The string to be converted.
   * @return array                The converted array.
   */
  public static function str_to_arr( &$input ) {
    if ( ! is_array( $input ) ) {
      $input = explode( ',', $input );
    }
    return $input;
  }

  /**
   * Encrypt the passed string with the passed key.
   *
   * @param  string $input String to be encrypted.
   * @param  string $key   The key used for the encryption.
   * @return string        The encrypted string.
   */
  public static function encrypt( $input, $key = AUTH_KEY ) {
    global $wpdb;
    return $wpdb->get_var( $wpdb->prepare( "SELECT HEX(AES_ENCRYPT(%s,%s))", $input, $key ) );
  }

  /**
   * Decrypt the passed string with the passed key.
   *
   * @param  string $input String to be decrypted.
   * @param  string $key   The key used for the decryption.
   * @return string        The decrypted string.
   */
  public static function decrypt( $input, $key = AUTH_KEY ) {
    global $wpdb;
    return $wpdb->get_var( $wpdb->prepare( "SELECT AES_DECRYPT(UNHEX(%s),%s)", $input, $key ) );
  }
}
