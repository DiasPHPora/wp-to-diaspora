<?php
/**
 * Plugin Name: WP to diaspora*
 * Description: Automatically shares WordPress posts on diaspora*
 * Version: 1.4.0
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

define( 'WP2D_VERSION', '1.4.0' );

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

    // Define simple constants.
    define( 'WP2D_LIB', dirname( __FILE__ ) . '/lib' );

    // Load necessary classes.
    if ( ! class_exists( 'HTML_To_Markdown' ) ) require_once WP2D_LIB . '/class-html-to-markdown.php';
    require_once WP2D_LIB . '/class-helpers.php';
    require_once WP2D_LIB . '/class-api.php';

    // Load languages.
    add_action( 'plugins_loaded', array( $instance, 'l10n' ) );

    // Add "Settings" link to plugin page.
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $instance, 'settings_link' ) );

    // Perform any necessary data upgrades.
    add_action( 'admin_init', array( $instance, 'upgrade' ) );

    // Enqueue CSS and JS scripts.
    add_action( 'admin_enqueue_scripts', array( $instance, 'admin_load_scripts' ) );

    // Set up the options.
    require_once WP2D_LIB . '/class-options.php';
    add_action( 'admin_menu', array( 'WP2D_Options', 'setup' ) );

    // WP2D Post.
    require_once WP2D_LIB . '/class-post.php';
    add_action( 'admin_init', array( 'WP2D_Post', 'setup' ) );


    // AJAX actions for loading pods, aspects and services.
    add_action( 'wp_ajax_wp_to_diaspora_update_pod_list', array( $instance, 'update_pod_list_callback' ) );
    add_action( 'wp_ajax_wp_to_diaspora_update_aspects_list', array( $instance, 'update_aspects_list_callback' ) );
    add_action( 'wp_ajax_wp_to_diaspora_update_services_list', array( $instance, 'update_services_list_callback' ) );

    // Check the pod connection status on the options page.
    add_action( 'wp_ajax_wp_to_diaspora_check_pod_connection_status', array( $instance, 'check_pod_connection_status_callback' ) );


    // The instance has been set up.
    self::$_is_set_up = true;
  }


  /**
   * Load the diaspora* API for ease of use.
   *
   * @return WP2D_API|boolean The API object, or false.
   */
  private function _load_api() {
    if ( ! isset( $this->_api ) ) {
      $this->_api = WP2D_Helpers::api_quick_connect();
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
        $options->set_option( 'password', WP2D_Helpers::encrypt( (string) $options->get_option( 'password' ) ) );
      }

      if ( version_compare( $version, '1.3.0', '<' ) ) {
        // The 'user' setting is renamed to 'username'.
        $options->set_option( 'username', $options->get_option( 'user' ) );
        $options->set_option( 'user', null );

        // Save tags as arrays instead of comma seperated values.
        $global_tags = $options->get_option( 'global_tags' );
        $options->set_option( 'global_tags', $options->validate_tags( $global_tags ) );
      }

      if ( version_compare( $version, '1.4.0', '<' ) ) {
        // Turn tags_to_post string into an array.
        $tags_to_post_old = $options->get_option( 'tags_to_post' );
        $tags_to_post = array_filter( array(
          ( ( false !== strpos( $tags_to_post_old, 'g' ) ) ? 'global' : null ),
          ( ( false !== strpos( $tags_to_post_old, 'c' ) ) ? 'custom' : null ),
          ( ( false !== strpos( $tags_to_post_old, 'p' ) ) ? 'post'   : null )
        ) );
        $options->set_option( 'tags_to_post', $tags_to_post );
      }

      // Update version.
      $options->set_option( 'version', WP2D_VERSION );
      $options->save();
    }
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
      wp_enqueue_script( 'chosen', plugins_url( '/js/chosen.jquery.min.js', __FILE__ ), array( 'jquery' ), false, true );
      wp_enqueue_script( 'tag-it', plugins_url( '/js/tag-it.min.js', __FILE__ ), array( 'jquery', 'jquery-ui-autocomplete' ), false, true );
      wp_enqueue_script( 'wp-to-diaspora-admin', plugins_url( '/js/wp-to-diaspora.js', __FILE__ ), array( 'jquery' ), false, true );
      // Javascript-specific l10n.
      wp_localize_script( 'wp-to-diaspora-admin', 'WP2DL10n', array(
        'no_services_connected' => __( 'No services connected yet.', 'wp_to_diaspora' ),
        'sure_reset_defaults'   => __( 'Are you sure you want to reset to default values?', 'wp_to_diaspora' ),
        'conn_testing'          => __( 'Testing connection...', 'wp_to_diaspora' ),
        'conn_successful'       => __( 'Connection successful.', 'wp_to_diaspora' ),
        'conn_failed'           => __( 'Connection failed.', 'wp_to_diaspora' )
      ) );
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


  /**
   * Fetch the updated list of pods from podupti.me and save it to the settings.
   *
   * @return array The list of pods.
   */
  private function _update_pod_list() {
    // API url to fetch pods list from podupti.me.
    $pod_list_url = 'http://podupti.me/api.php?format=json&key=4r45tg';

    $pods = array();
    if ( $json = @file_get_contents( $pod_list_url ) ) {
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
    $aspects = $options->get_option( 'aspects_list' );

    // Make sure that we have at least the 'Public' aspect.
    if ( empty( $aspects ) ) {
      $aspects = array( 'public' => __( 'Public' ) );
    }

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


  /**
   * Check the pod connection status.
   *
   * @return string The status of the connection.
   */
  private function _check_pod_connection_status() {
    $options = WP2D_Options::get_instance();

    $status = 'notset';

    if ( $options->is_pod_set_up() ) {
      $api = $this->_load_api();
      if ( ! $api || $api->last_error ) {
        $status = 'failed';
      } else {
        $status = 'success';
      }
    }

    return $status;
  }

  /**
   * Update the list of services and return them for use with AJAX.
   */
  public function check_pod_connection_status_callback() {
    wp_send_json( $this->_check_pod_connection_status() );
  }
}

// Get the party started!
WP_To_Diaspora::setup();
