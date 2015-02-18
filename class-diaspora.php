<?php
/**
* Based on class from https://github.com/Faldrian/WP-diaspora-postwidget/blob/master/wp-diaspora-postwidget/diasphp.php  -- Thanks, Faldrian!
*
* Ein fies zusammengehackter PHP-Diaspory-Client, der direkt von diesem abgeschaut ist:
* https://github.com/Javafant/diaspy/blob/master/client.py
*/

class Diasphp {
  function __construct( $pod ) {
    $this->token_regex = '/content="(.*?)" name="csrf-token/';
    $this->aspects_regex = '/"aspects"\:(\[.+?\])/';
    $this->pod = $pod;
    $this->cookiejar = tempnam( sys_get_temp_dir(), 'cookies' );
  }

  /**
   * Fetch the secure token from Diaspora.
   * @return string The fetched token.
   */
  function _fetch_token() {
    // Define maximum redirects.
    $max_redirects = 10;

    // Call address via cURL.
    $ch = curl_init();

    // Set up cURL options.
    curl_setopt( $ch, CURLOPT_URL, $this->pod . '/stream' );
    curl_setopt( $ch, CURLOPT_COOKIEFILE, $this->cookiejar );
    curl_setopt( $ch, CURLOPT_COOKIEJAR, $this->cookiejar );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

    // Check if the call can safely be made.
    if ( '' === ini_get( 'open_basedir' ) && 'off' === strtolower( ini_get( 'safe_mode' ) ) ) {
      // Set up cURL options.
      curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
      curl_setopt( $ch, CURLOPT_MAXREDIRS, $max_redirects );
      $output = curl_exec( $ch );
    } else {
      curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
      $mr = $max_redirects;
      if ( $mr > 0 ) {
        $newurl = curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );

        $rcurl = curl_copy_handle( $ch );

        // Set up cURL options.
        curl_setopt( $rcurl, CURLOPT_HEADER, true );
        curl_setopt( $rcurl, CURLOPT_NOBODY, true );
        curl_setopt( $rcurl, CURLOPT_FORBID_REUSE, false );

        do {
          curl_setopt( $rcurl, CURLOPT_URL, $newurl );
          $header = curl_exec( $rcurl );
          if ( curl_errno( $rcurl ) ) {
            $code = 0;
          } else {
            $code = curl_getinfo( $rcurl, CURLINFO_HTTP_CODE );
            if ( 301 == $code || 302 == $code ) {
              preg_match( '/Location:(.*?)\n/', $header, $matches );
              $newurl = trim( array_pop( $matches ) );
            } else {
              $code = 0;
            }
          }
        } while ( $code && --$mr );

        curl_close( $rcurl );
        if ( $mr > 0 ) {
          curl_setopt( $ch, CURLOPT_URL, $newurl );
        }
      }

      $output = ( 0 == $mr && $max_redirects > 0 ) ? false : curl_exec( $ch );
    }
    curl_close( $ch );

    // Fetch and return the found token.
    preg_match( $this->token_regex, $output, $matches );
    return $matches[1];
  }

  /**
   * Log in to Diaspora.
   * @param  string  $username Username used for login.
   * @param  string  $password Password used for login.
   * @return Diasphp           Return this object for method chaining if successfully logged in, else throw an exception.
   */
  function login( $username, $password ) {
    // Set up the login parameters passed to cURL.
    $datatopost = array(
      'user[username]' => $username,
      'user[password]' => $password,
      'authenticity_token' => $this->_fetch_token()
    );
    $poststr = http_build_query( $datatopost );

    // Call address via cURL.
    $ch = curl_init();

    // Set up cURL options.
    curl_setopt( $ch, CURLOPT_URL, $this->pod . '/users/sign_in' );
    curl_setopt( $ch, CURLOPT_COOKIEFILE, $this->cookiejar );
    curl_setopt( $ch, CURLOPT_COOKIEJAR, $this->cookiejar );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $poststr );

    curl_exec( $ch );
    $info = curl_getinfo( $ch );
    curl_close( $ch );

    if ( 302 != $info['http_code'] ) {
      throw new Exception( 'Login error.' );
    }

    // Return the object to provide method chaining.
    return $this;
  }

  /**
   * Post to Diaspora.
   * @param  string       $text     The text to post.
   * @param  string       $provider The provider name to display.
   * @param  string|array $aspects  The aspects to post to. (Array or comma seperated ids)
   * @return object                 Return the response data of the new Diaspora* post if successfully posted, else throw an exception.
   */
  function post( $text, $provider = 'diasphp', $aspects = 'public' ) {
    // Put the aspects into an array.
    if ( isset( $aspects ) && ! is_array( $aspects ) ) {
      $aspects = array_filter( explode( ',', $aspects ) );
    }
    // If no aspects have been selected or the public one is also included, choose public only.
    if ( empty( $aspects ) || in_array( 'public', $aspects ) ) {
      $aspects = 'public';
    }

    // Prepare post data.
    $datatopost = json_encode( array(
      'aspect_ids'     => $aspects,
      'status_message' => array(
        'text' => $text,
        'provider_display_name' => $provider
      )
    ));

    // Prepare headers.
    $headers = array(
      'Content-Type: application/json',
      'accept: application/json',
      'x-csrf-token: ' . $this->_fetch_token()
    );

    // Call address via cURL.
    $ch = curl_init();

    // Set up cURL options.
    curl_setopt( $ch, CURLOPT_URL, $this->pod . '/status_messages' );
    curl_setopt( $ch, CURLOPT_COOKIEFILE, $this->cookiejar );
    curl_setopt( $ch, CURLOPT_COOKIEJAR, $this->cookiejar );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $datatopost );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

    $response = curl_exec( $ch );
    $info = curl_getinfo( $ch );
    curl_close( $ch );


    if ( 201 != $info['http_code'] ) {
      throw new Exception( 'Post error.' );
    }

    // End of chaining, return response data as an object.
    return json_decode( $response );
  }

  /**
   * Get the list of aspects.
   * @return array Array of aspect objects.
   */
  function get_aspects() {
    // Define maximum redirects.
    $max_redirects = 10;

    // Call address via cURL.
    $ch = curl_init();

    // Set up cURL options.
    curl_setopt( $ch, CURLOPT_URL, $this->pod . '/bookmarklet' );
    curl_setopt( $ch, CURLOPT_COOKIEFILE, $this->cookiejar );
    curl_setopt( $ch, CURLOPT_COOKIEJAR, $this->cookiejar );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

    // Check if the call can safely be made.
    if ( '' === ini_get( 'open_basedir' ) && 'off' === strtolower( ini_get( 'safe_mode' ) ) ) {
      // Set up cURL options.
      curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
      curl_setopt( $ch, CURLOPT_MAXREDIRS, $max_redirects );
      $response = curl_exec( $ch );
    } else {
      curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
      $mr = $max_redirects;
      if ( $mr > 0 ) {
        $newurl = curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );

        $rcurl = curl_copy_handle( $ch );

        // Set up cURL options.
        curl_setopt( $rcurl, CURLOPT_HEADER, true );
        curl_setopt( $rcurl, CURLOPT_NOBODY, true );
        curl_setopt( $rcurl, CURLOPT_FORBID_REUSE, false );

        do {
          curl_setopt( $rcurl, CURLOPT_URL, $newurl );
          $header = curl_exec( $rcurl );
          if ( curl_errno( $rcurl ) ) {
            $code = 0;
          } else {
            $code = curl_getinfo( $rcurl, CURLINFO_HTTP_CODE );
            if ( 301 == $code || 302 == $code ) {
              preg_match( '/Location:(.*?)\n/', $header, $matches );
              $newurl = trim( array_pop( $matches ) );
            } else {
              $code = 0;
            }
          }
        } while ( $code && --$mr );

        curl_close( $rcurl );
        if ( $mr > 0 ) {
          curl_setopt( $ch, CURLOPT_URL, $newurl );
        }
      }

      $response = ( 0 == $mr && $max_redirects > 0 ) ? false : curl_exec( $ch );
    }
    curl_close( $ch );

    // Fetch and return the found aspects.
    preg_match( $this->aspects_regex, $response, $matches );
    return json_decode( $matches[1] );
  }
}

?>
