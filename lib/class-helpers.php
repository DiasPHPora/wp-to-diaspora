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
   * Clean up the passed tags. Keep only alphanumeric, hyphen and underscore characters.
   *
   * @param  array|string $tag Tags to be cleaned as array or comma seperated values.
   * @return array             The cleaned tags.
   */
  public static function get_clean_tags( $tags ) {
    // Make sure we have an array of tags.
    if ( ! is_array( $tags ) ) {
      $tags = explode( ',', $tags );
    }

    return array_map( array( 'WP2D_Helpers', 'get_clean_tag' ),
      array_unique(
        array_filter( $tags, 'trim' )
      )
    );
  }

  /**
   * Clean up the passed tag. Keep only alphanumeric, hyphen and underscore characters.
   *
   * @todo   What about eastern characters? (chinese, indian, etc.)
   *
   * @param  string $tag Tag to be cleaned.
   * @return string      The clean tag.
   */
  public static function get_clean_tag( $tag ) {
    return preg_replace( '/[^\w $\-]/u', '', str_replace( ' ', '-', trim( $tag ) ) );
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
    return base64_encode( $wpdb->get_var( $wpdb->prepare( "SELECT AES_ENCRYPT(%s,%s)", $input, $key ) ) );
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
    return $wpdb->get_var( $wpdb->prepare( "SELECT AES_DECRYPT(%s,%s)", base64_decode( $input ), $key ) );
  }
}

?>