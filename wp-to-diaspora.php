<?php
/**
 * Plugin Name: WP to diaspora*
 * Description: Automatically shares WordPress posts on diaspora*
 * Version: 1.3.1
 * Author: Augusto Bennemann
 * Author URI: https://github.com/gutobenn
 * Plugin URI: https://github.com/gutobenn/wp-to-diaspora
 * License: GPL2
 */

/*  Copyright 2014-2015 Augusto Bennemann (email: gutobenn at gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

define( 'WP2D_VERSION', '1.3.1' );

class WP_To_Diaspora {

  /**
   * Has the plugin already been set up?
   *
   * @var boolean
   */
  private static $_is_set_up = false;

  /**
   * Only instance of this class.
   *
   * @var WP_To_Diaspora
   */
  private static $_instance = null;

  /**
   * Instance of the API class.
   *
   * @var WP2D_API
   */
  private $_api = null;

  /**
   * Create / Get the instance of this class.
   *
   * @return WP_To_Diaspora Instance of this class.
   */
  public static function get_instance() {
    if ( ! isset( self::$_instance ) ) {
      self::$_instance = new self();
    }
    return self::$_instance;
  }

  /**
   * Set up the plugin.
   */
  public static function setup() {
    // If the instance is already set up, just return.
    if ( self::$_is_set_up ) {
      return;
    }

    // Get the unique instance.
    $instance = self::get_instance();

    // Are we in debugging mode?
    define( 'WP2D_DEBUGGING', isset( $_GET['debugging'] ) );

    // Load necessary classes.
    if ( ! class_exists( 'HTML_To_Markdown' ) ) require_once dirname( __FILE__ ) . '/lib/class-html-to-markdown.php';
    require_once dirname( __FILE__ ) . '/lib/class-helpers.php';
    require_once dirname( __FILE__ ) . '/lib/class-api.php';

    // Load languages.
    add_action( 'plugins_loaded', array( $instance, 'l10n' ) );

    // Add "Settings" link to plugin page.
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $instance, 'settings_link' ) );

    // Perform any necessary data upgrades.
    add_action( 'admin_init', array( $instance, 'upgrade' ) );

    // Enqueue CSS and JS scripts.
    add_action( 'admin_enqueue_scripts', array( $instance, 'admin_load_scripts' ) );

    // Set up the options.
    require_once dirname( __FILE__ ) . '/lib/class-options.php';
    add_action( 'admin_menu', array( 'WP2D_Options', 'setup' ) );

    // Notices when a post has been shared or if it has failed.
    add_action( 'admin_notices', array( $instance, 'admin_notices' ) );
    add_action( 'admin_init', array( $instance, 'ignore_post_error' ) );


    // Handle diaspora* posting when saving the post.
    add_action( 'save_post', array( $instance, 'post' ), 20, 2 );
    add_action( 'save_post', array( $instance, 'save_meta_box_data' ), 10 );

    // Add meta boxes.
    add_action( 'add_meta_boxes', array( $instance, 'add_meta_boxes' ) );

    // AJAX actions for loading pods, aspects and services.
    add_action( 'wp_ajax_wp_to_diaspora_update_pod_list', array( $instance, 'update_pod_list_callback' ) );
    add_action( 'wp_ajax_wp_to_diaspora_update_aspects_list', array( $instance, 'update_aspects_list_callback' ) );
    add_action( 'wp_ajax_wp_to_diaspora_update_services_list', array( $instance, 'update_services_list_callback' ) );

    // The instance has been set up.
    self::$_is_set_up = true;
  }

  /**
   * Load the diaspora* API for ease of use.
   *
   * @return WP2D_API The API object.
   */
  private function _load_api() {
    $options = WP2D_Options::get_instance();
    if ( ! isset( $this->_api ) ) {
      $this->_api = new WP2D_API( $options->get_option( 'pod' ) );
    }
    if ( $this->_api->init() ) {
      $this->_api->login( $options->get_option( 'username' ), WP2D_Helpers::decrypt( $options->get_option( 'password' ) ) );
    }
    return $this->_api;
  }


  /**
   * Initialise upgrade sequence.
   */
  public function upgrade() {
    // Get the current options, or assign defaults.
    $options = WP2D_Options::get_instance();
    $version = $options->get_option( 'version' );

    // If the versions differ, this is probably an update. Need to save updated options.
    if ( WP2D_VERSION != $version ) {

      // Password is stored encrypted since version 1.2.7.
      // When upgrading to it, the plain text password is encrypted and saved again.
      if ( version_compare( $version, '1.2.7', '<' ) ) {
        $options->set_option( 'password', WP2D_Helpers::encrypt( $options->get_option( 'password' ) ) );
      }

      if ( version_compare( $version, '1.3.0', '<' ) ) {
        // The 'user' setting is renamed to 'username'.
        $options->set_option( 'username', $options->get_option( 'user' ) );
        $options->set_option( 'user', null );

        // Save tags as arrays instead of comma seperated values.
        $options->set_option( 'global_tags', WP2D_Helpers::get_clean_tags( $options->get_option( 'global_tags' ) ) );
      }

      // Update version.
      $options->set_option( 'version', WP2D_VERSION );
      $options->save();
    }
  }

  /**
   * Post to Diaspora when saving a post.
   *
   * @param integer $post_id ID of the post being saved.
   * @param WP_Post $post    Post object being saved.
   */
  public function post( $post_id, $post ) {
    $options = WP2D_Options::get_instance();

    // Is this post type enabled for posting?
    if ( ! in_array( $post->post_type, $options->get_option( 'enabled_post_types' ) ) ) {
      return;
    }

    // Get the post's meta data.
    $post_meta = get_post_meta( $post_id, '_wp_to_diaspora', true );
    if ( empty( $post_meta ) ) {
      return;
    }

    // Facilitate access to meta data.
    extract( $post_meta, EXTR_PREFIX_ALL, 'meta' );

    // Make sure we're posting to diaspora* and the post isn't password protected.
    // TODO: Maybe somebody wants to share a password protected post to a closed aspect.
    if ( $meta_post_to_diaspora && 'publish' === $post->post_status && '' === $post->post_password ) {

      $status_message = sprintf( '<p><b><a href="%1$s" title="permalink to %2$s">%2$s</a></b></p>',
        get_permalink( $post_id ),
        $post->post_title
      );

      // Post the full post text or just the excerpt?
      if ( 'full' === $meta_display ) {
        // Disable all filters and then enable only defaults. This prevents additional filters from being posted to diaspora*.
        remove_all_filters( 'the_content' );
        foreach ( array( 'wptexturize', 'convert_smilies', 'convert_chars', 'wpautop', 'shortcode_unautop', 'prepend_attachment', array( $this, 'embed_remove' ) ) as $filter ) {
          add_filter( 'the_content', $filter );
        }
        // Extract URLs from [embed] shortcodes.
        add_filter( 'embed_oembed_html', array( $this, 'embed_url' ), 10, 4 );

        $status_message .= apply_filters( 'the_content', $post->post_content );
      } else {
        $excerpt = ( '' != $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 42, '[...]' );
        $status_message .= '<p>' . $excerpt . '</p>';
      }

      // Add any diaspora* tags?
      if ( false === strpos( $meta_tags_to_post, 'n' ) ) {
        // The diaspora* tags to add to the post.
        $diaspora_tags = array();

        // Add global tags?
        if ( false !== strpos( $meta_tags_to_post, 'g' ) ) {
          $diaspora_tags += array_flip( $options->get_option( 'global_tags' ) );
        }

        // Add custom tags?
        if ( false !== strpos( $meta_tags_to_post, 'c' ) ) {
          $diaspora_tags += array_flip( $meta_custom_tags );
        }

        // Add post tags?
        if ( false !== strpos( $meta_tags_to_post, 'p' ) ) {
          // Clean up the post tags.
          $diaspora_tags += array_flip( wp_get_post_tags( $post_id, array( 'fields' => 'slugs' ) ) );
        }

        // Get an array of cleaned up tags.
        $diaspora_tags = WP2D_Helpers::get_clean_tags( array_keys( $diaspora_tags ) );

        // Get all the tags and list them all nicely in a row.
        $diaspora_tags_clean = array();
        foreach ( $diaspora_tags as $tag ) {
          $diaspora_tags_clean[] = '#' . $tag;
        }

        // Add all the found tags.
        if ( ! empty( $diaspora_tags_clean ) ) {
          $status_message .= implode( ' ', $diaspora_tags_clean ) . '<br />';
        }
      }

      // Add the original entry link to the post?
      if ( $meta_fullentrylink ) {
        $status_message .= sprintf( '%1$s [%2$s](%2$s "%3$s")',
          __( 'Originally posted at:', 'wp_to_diaspora' ),
          get_permalink( $post_id ),
          $post->post_title
        );
      }

      $status_markdown = new HTML_To_Markdown( $status_message );
      $status_message  = $status_markdown->output();

      // Add services to share to via diaspora*.
      $extra_data = array(
        'services' => $meta_services
      );

      // Set up the connection to diaspora*.
      $api = $this->_load_api();
      if ( ! $api->last_error && $response = $api->post( $status_message, $meta_aspects, $extra_data ) ) {
        // Save certain diaspora* post data as meta data for future reference.

        // Get the existing post history.
        $diaspora_post_history = get_post_meta( $post_id, '_wp_to_diaspora_post_history', true );
        if ( empty( $diaspora_post_history ) ) {
          $diaspora_post_history = array();
        }

        // Add a new entry to the history.
        $diaspora_post_history[] = array(
          'id'         => $response->id,
          'guid'       => $response->guid,
          'created_at' => $post->post_modified,
          'aspects'    => $meta_aspects,
          'nsfw'       => $response->nsfw,
          'post_url'   => $api->get_pod_url( '/posts/' . $response->guid )
        );
        update_post_meta( $post_id, '_wp_to_diaspora_post_history', $diaspora_post_history );

        // If there is still a previous post error around, remove it.
        delete_post_meta( $post_id, '_wp_to_diaspora_post_error' );
      } else {
        // Save the post error as post meta data, so we can display it to the user.
        update_post_meta( $post_id, '_wp_to_diaspora_post_error', $api->last_error );
      }
    }
  }

  /**
   * Return URL from [embed] shortcode instead of generated iframe.
   */
  public function embed_url( $html, $url, $attr, $post_ID ) {
    return $url;
  }

  /**
   * Removes '[embed]' and '[/embed]' left by embed_url.
   *
   *  TODO: It would be great to fix it using only one filter.
   *        It's happening because embed filter is being removed by remove_all_filters('the_content') on wp_to_diaspora_post().
   */
  public function embed_remove( $content ) {
    return str_replace( array( '[embed]', '[/embed]' ), array( '<p>', '</p>' ), $content );
  }

  /**
   * Set up i18n.
   */
  public function l10n() {
    load_plugin_textdomain( 'wp_to_diaspora', false, 'wp-to-diaspora/languages' );
  }

  /**
   * Load scripts and styles for Settings and Post pages of allowed post types.
   */
  public function admin_load_scripts() {
    // Get the enabled post types to load the script for.
    $enabled_post_types = WP2D_Options::get_instance()->get_option( 'enabled_post_types', array() );

    // Get the screen to find out where we are.
    $screen = get_current_screen();

    // Only load the styles and scripts on the settings page and the allowed post types.
    if ( 'settings_page_wp_to_diaspora' === $screen->id || ( in_array( $screen->post_type, $enabled_post_types ) && 'post' === $screen->base ) ) {
      wp_enqueue_style(  'tag-it', plugins_url( '/css/jquery.tagit.css', __FILE__ ) );
      wp_enqueue_style(  'chosen', plugins_url( '/css/chosen.min.css', __FILE__ ) );
      wp_enqueue_style(  'wp-to-diaspora-admin', plugins_url( '/css/wp-to-diaspora.css', __FILE__ ) );
      wp_enqueue_script( 'chosen', plugins_url( '/js/chosen.jquery.js', __FILE__ ), array( 'jquery' ), false, true );
      wp_enqueue_script( 'tag-it', plugins_url( '/js/tag-it.min.js', __FILE__ ), array( 'jquery', 'jquery-ui-autocomplete' ), false, true );
      wp_enqueue_script( 'wp-to-diaspora-admin', plugins_url( '/js/wp-to-diaspora.js', __FILE__ ), array( 'jquery' ), false, true );
    }
  }

  /**
   * Add the "Settings" link to the plugins page.
   *
   * @param  array $links Links to display for plugin on plugins page.
   * @return array        Links to display for plugin on plugins page.
   */
  public function settings_link( $links ) {
    $links[] = '<a href="' . admin_url( 'options-general.php?page=wp_to_diaspora' ) . '">' . __( 'Settings' ) . '</a>';
    return $links;
  }


  /* META BOX */

  /**
   * Adds a meta box to the main column on the enabled Post Types' edit screens.
   */
  public function add_meta_boxes() {
    foreach ( WP2D_Options::get_instance()->get_option( 'enabled_post_types' ) as $post_type ) {
      add_meta_box(
        'wp_to_diaspora_meta_box',
        'WP to diaspora*',
        array( $this, 'meta_box_render' ),
        $post_type,
        'side',
        'high'
      );
    }
  }

  /**
   * Prints the meta box content.
   *
   * @param WP_Post $post The object for the current post.
   */
  public function meta_box_render( $post ) {
    // Add an nonce field so we can check for it later.
    wp_nonce_field( 'wp_to_diaspora_meta_box', 'wp_to_diaspora_meta_box_nonce' );

    // Get the default values to use, but give priority to the meta data already set.
    $options = WP2D_Options::get_instance();

    // Use the default if no meta data is set yet.
    $post_meta = wp_parse_args(
      get_post_meta( $post->ID, '_wp_to_diaspora', true ),
      $options->get_options()
    );

    // Facilitate access to meta data.
    extract( $post_meta, EXTR_PREFIX_ALL, 'meta' );

    // If this post is already published, don't post again to diaspora*.
    $meta_post_to_diaspora = ( 'publish' === get_post_status( $post->ID ) ) ? false : $meta_post_to_diaspora;
    $meta_aspects          = ( ! empty( $meta_aspects )  && is_array( $meta_aspects ) )  ? $meta_aspects  : array();
    $meta_services         = ( ! empty( $meta_services ) && is_array( $meta_services ) ) ? $meta_services : array();

    // Have we already posted on diaspora*?
    if ( ( $diaspora_post_history = get_post_meta( $post->ID, '_wp_to_diaspora_post_history', true ) ) && is_array( $diaspora_post_history ) ) {
      $latest_post = end( $diaspora_post_history );
      ?>
      <p><a href="<?php echo $latest_post['post_url']; ?>" target="_blank"><?php _e( 'Already posted to diaspora*', 'wp_to_diaspora' ); ?></a></p>
      <?php
    }
    ?>

    <p><?php $options->post_to_diaspora_render( $meta_post_to_diaspora ); ?></p>
    <p><?php $options->fullentrylink_render( $meta_fullentrylink ); ?></p>
    <p><?php $options->display_render( $meta_display ); ?></p>
    <p><?php $options->tags_to_post_render( $meta_tags_to_post ); ?></p>
    <p><?php $options->custom_tags_render( $meta_custom_tags ); ?></p>
    <p><?php $options->aspects_render( $meta_aspects ); ?></p>
    <p><?php $options->services_render( $meta_services ); ?></p>

    <?php
  }

  /**
   * When the post is saved, save our meta data.
   *
   * @param int $post_id The ID of the post being saved.
   */
  public function save_meta_box_data( $post_id ) {
    /*
     * We need to verify this came from our screen and with proper authorization,
     * because the save_post action can be triggered at other times.
     */
    // Verify that our nonce is set and  valid.
    if ( ! ( isset( $_POST['wp_to_diaspora_meta_box_nonce'] ) && wp_verify_nonce( $_POST['wp_to_diaspora_meta_box_nonce'], 'wp_to_diaspora_meta_box' ) ) ) {
      return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
      return;
    }

    // Check the user's permissions.
    if ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) {
      if ( ! current_user_can( 'edit_pages', $post_id ) ) {
        return;
      }
    } else {
      if ( ! current_user_can( 'edit_posts', $post_id ) ) {
        return;
      }
    }

    // OK, it's safe for us to save the data now.

    // Make real sure that we have some meta data to save.
    if ( ! isset( $_POST['wp_to_diaspora_settings'] ) ) {
      return;
    }

    // Meta data to save.
    $meta_to_save = $_POST['wp_to_diaspora_settings'];
    $options = WP2D_Options::get_instance();

    // Checkboxes.
    foreach ( array( 'post_to_diaspora', 'fullentrylink' ) as $option ) {
      $meta_to_save[ $option ] = isset( $meta_to_save[ $option ] );
    }

    // Selects.
    foreach ( array( 'display', 'tags_to_post' ) as $option ) {
      if ( isset( $meta_to_save[ $option ] ) && ! $options->is_valid_value(  $option , $meta_to_save[ $option ] ) ) {
        $meta_to_save[ $option ] = $options->get_option( $option );
      }
    }

    // Save custom tags as array.
    $meta_to_save['custom_tags'] = WP2D_Helpers::get_clean_tags( $meta_to_save['custom_tags'] );

    // Clean up the list of aspects. If the list is empty, only use the 'Public' aspect.
    if ( empty( $meta_to_save['aspects'] ) || ! is_array( $meta_to_save['aspects'] ) ) {
      $meta_to_save['aspects'] = array( 'public' );
    } else {
      array_walk( $meta_to_save['aspects'], 'sanitize_text_field' );
    }

    // Clean up the list of services.
    if ( empty( $meta_to_save['services'] ) || ! is_array( $meta_to_save['services'] ) ) {
      $meta_to_save['services'] = array();
    } else {
      array_walk( $meta_to_save['services'], 'sanitize_text_field' );
    }

    // Update the meta data for this post.
    update_post_meta( $post_id, '_wp_to_diaspora', $meta_to_save );
  }


  /**
   * Add admin notices when a post gets displayed.
   */
  public function admin_notices() {
    global $post, $pagenow;
    if ( ! $post || 'post.php' !== $pagenow ) {
      return;
    }

    if ( $error = get_post_meta( $post->ID, '_wp_to_diaspora_post_error', true ) ) {
      // This notice will only be shown if posting to diaspora* has failed.
      printf( '<div class="error"><p>%1$s: %2$s <a href="%3$s">%4$s</a></p></div>',
        __( 'Failed to post to diaspora*', 'wp_to_diaspora' ),
        $error,
        add_query_arg( 'wp_to_diaspora_ignore_post_error', 'yes' ),
        __( 'Ignore', 'wp_to_diaspora' )
      );
    } elseif ( ( $diaspora_post_history = get_post_meta( $post->ID, '_wp_to_diaspora_post_history', true ) ) && is_array( $diaspora_post_history ) ) {
      // Get the latest post from the history.
      $latest_post = end( $diaspora_post_history );

      // Only show if this post is showing a message and the post is a fresh share.
      if ( isset( $_GET['message'] ) && $post->post_modified == $latest_post['created_at'] ) {
        printf( '<div class="updated"><p>%1$s <a href="%2$s" target="_blank">%3$s</a></p></div>',
          __( 'Successfully posted to diaspora*.', 'wp_to_diaspora' ),
          $latest_post['post_url'],
          __( 'View Post' )
        );
      }
    }
  }

  /**
   * Delete the error post meta data if it gets ignored.
   */
  public function ignore_post_error() {
    // If "Ignore" link has been clicked, delete the post error meta data.
    if ( isset( $_GET['wp_to_diaspora_ignore_post_error'], $_GET['post'] ) && 'yes' == $_GET['wp_to_diaspora_ignore_post_error'] ) {
      delete_post_meta( $_GET['post'], '_wp_to_diaspora_post_error' );
    }
  }


  /**
   * Fetch the updated list of pods from podupti.me and save it to the settings.
   *
   * @return array The list of pods.
   */
  private function _update_pod_list() {
    // API url to fetch pods list from podupti.me.
    $pod_list_url = 'http://podupti.me/api.php?format=json&key=4r45tg';

    $pods = array();
    if ( $json = file_get_contents( $pod_list_url ) ) {
      $pod_list = json_decode( $json );
      if ( isset( $pod_list->pods ) ) {
        foreach ( $pod_list->pods as $pod ) {
          if ( 'no' === $pod->hidden ) {
            $pods[] = array(
              'secure' => $pod->secure,
              'domain' => $pod->domain
            );
          }
        }

        $options = WP2D_Options::get_instance();
        $options->set_option( 'pod_list', $pods );
        $options->save();
      }
    }
    return $pods;
  }

  /**
   * Update the list of pods and return them for use with AJAX.
   */
  public function update_pod_list_callback() {
    wp_send_json( $this->_update_pod_list() );
  }

  /**
   * Fetch the list of aspects and save them to the settings.
   *
   * @return array The list of aspects. (id => name pairs)
   */
  private function _update_aspects_list() {
    $options = WP2D_Options::get_instance();
    $aspects = $options->get_option( 'aspects_list', array( 'public' => __( 'Public' ) ) );

    // Set up the connection to diaspora*.
    $api = $this->_load_api();
    if ( ! $api->last_error && $aspects = $api->get_aspects() ) {
      // So we have a new list of aspects.
      $options = WP2D_Options::get_instance();
      $options->set_option( 'aspects_list', $aspects );
      $options->save();
    }

    return $aspects;
  }

  /**
   * Update the list of aspects and return them for use with AJAX.
   */
  public function update_aspects_list_callback() {
    wp_send_json( $this->_update_aspects_list() );
  }

  /**
   * Fetch the list of services and save them to the settings.
   *
   * @return array The list of services. (id => name pairs)
   */
  private function _update_services_list() {
    $options = WP2D_Options::get_instance();
    $services = $options->get_option( 'services_list', array() );

    // Set up the connection to diaspora*.
    $api = $this->_load_api();
    if ( ! $api->last_error && $services = $api->get_services() ) {
      // So we have a new list of services.
      $options = WP2D_Options::get_instance();
      $options->set_option( 'services_list', $services );
      $options->save();
    }

    return $services;
  }

  /**
   * Update the list of services and return them for use with AJAX.
   */
  public function update_services_list_callback() {
    wp_send_json( $this->_update_services_list() );
  }
}

// Get the party started!
WP_To_Diaspora::setup();

?>
