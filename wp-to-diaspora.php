<?php
/**
 * Plugin Name: WP to diaspora*
 * Plugin URI:  https://github.com/DiasPHPora/wp-to-diaspora
 * Description: Automatically shares WordPress posts on diaspora*
 * Version:     1.9.1
 * Author:      Augusto Bennemann
 * Author URI:  https://github.com/gutobenn
 * Text Domain: wp-to-diaspora
 * Domain Path: /languages
 *
 * Copyright 2014-2015 Augusto Bennemann (email: gutobenn at gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License as published by the Free Software Foundation; either version 2 of the License,
 * or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write
 * to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @package   WP_To_Diaspora
 * @version   1.9.1
 * @author    Augusto Bennemann <gutobenn@gmail.com>
 * @copyright Copyright (c) 2015, Augusto Bennemann
 * @link      https://github.com/DiasPHPora/wp-to-diaspora
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Set the current version.
define( 'WP2D_VERSION', '1.9.1' );

/**
 * WP to diaspora* main plugin class.
 */
class WP_To_Diaspora {

	/**
	 * Only instance of this class.
	 *
	 * @var WP_To_Diaspora
	 */
	private static $_instance;

	/**
	 * The minimum required WordPress version.
	 *
	 * @since 1.5.4
	 *
	 * @var string
	 */
	private $_min_wp = '3.9.2-src';

	/**
	 * The minimum required PHP version.
	 *
	 * @since 1.5.4
	 *
	 * @var string
	 */
	private $_min_php = '5.3';

	/**
	 * Instance of the API class.
	 *
	 * @var WP2D_API
	 */
	private $_api;

	/**
	 * Create / Get the instance of this class.
	 *
	 * @return WP_To_Diaspora Instance of this class.
	 */
	public static function instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
			if ( self::$_instance->_version_check() ) {
				self::$_instance->_constants();
				self::$_instance->_includes();
				self::$_instance->_setup();
			} else {
				self::$_instance = null;
			}
		}

		return self::$_instance;
	}

	/**
	 * Define all the required constants.
	 *
	 * @since 1.5.0
	 */
	private function _constants() {
		// Are we in debugging mode?
		if ( isset( $_GET['debugging'] ) ) {
			define( 'WP2D_DEBUGGING', true );
		}

		define( 'WP2D_DIR', dirname( __FILE__ ) );
		define( 'WP2D_LIB_DIR', WP2D_DIR . '/lib' );
		define( 'WP2D_VENDOR_DIR', WP2D_DIR . '/vendor' );
	}

	/**
	 * Check the minimum WordPress and PHP requirements.
	 *
	 * @since 1.5.4
	 *
	 * @return bool If version requirements are met.
	 */
	private function _version_check() {
		// Check for version requirements.
		if ( version_compare( $GLOBALS['wp_version'], $this->_min_wp, '<' )
			|| version_compare( PHP_VERSION, $this->_min_php, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'deactivate' ) );
			return false;
		}

		return true;
	}

	/**
	 * Callback to deactivate plugin and display admin notice.
	 *
	 * @since 1.5.4
	 */
	public function deactivate() {
		// First of all, deactivate the plugin.
		deactivate_plugins( plugin_basename( __FILE__ ) );

		// Get rid of the "Plugin activated" message.
		unset( $_GET['activate'] );

		// Then display the admin notice.
		?>
		<div class="error">
			<p><?php echo esc_html( sprintf( 'WP to diaspora* requires at least WordPress %1$s (you have %2$s) and PHP %3$s (you have %4$s)!', $this->_min_wp, $GLOBALS['wp_version'], $this->_min_php, PHP_VERSION ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Include all the required files.
	 *
	 * @since 1.5.0
	 */
	private function _includes() {
		require_once WP2D_VENDOR_DIR . '/autoload.php';
		require_once WP2D_LIB_DIR . '/class-api.php';
		require_once WP2D_LIB_DIR . '/class-contextual-help.php';
		require_once WP2D_LIB_DIR . '/class-helpers.php';
		require_once WP2D_LIB_DIR . '/class-options.php';
		require_once WP2D_LIB_DIR . '/class-post.php';
	}

	/**
	 * Set up the plugin.
	 */
	private function _setup() {

		// Load languages.
		add_action( 'plugins_loaded', array( $this, 'l10n' ) );

		// Add "Settings" link to plugin page.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'settings_link' ) );

		// Perform any necessary data upgrades.
		add_action( 'admin_init', array( $this, 'upgrade' ) );

		// Enqueue CSS and JS scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_load_scripts' ) );

		// Set up the options.
		add_action( 'init', array( 'WP2D_Options', 'instance' ) );

		// WP2D Post.
		add_action( 'init', array( 'WP2D_Post', 'setup' ) );

		// AJAX actions for loading aspects and services.
		add_action( 'wp_ajax_wp_to_diaspora_update_aspects_list', array( $this, 'update_aspects_list_callback' ) );
		add_action( 'wp_ajax_wp_to_diaspora_update_services_list', array( $this, 'update_services_list_callback' ) );

		// Check the pod connection status on the options page.
		add_action( 'wp_ajax_wp_to_diaspora_check_pod_connection_status', array( $this, 'check_pod_connection_status_callback' ) );
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
		$options = WP2D_Options::instance();
		$version = $options->get_option( 'version' );

		// If the versions differ, this is probably an update. Need to save updated options.
		if ( WP2D_VERSION !== $version ) {

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
					( ( false !== strpos( $tags_to_post_old, 'p' ) ) ? 'post'   : null ),
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
		load_plugin_textdomain( 'wp-to-diaspora', false, 'wp-to-diaspora/languages' );
	}

	/**
	 * Load scripts and styles for Settings and Post pages of allowed post types.
	 */
	public function admin_load_scripts() {
		// Get the enabled post types to load the script for.
		$enabled_post_types = WP2D_Options::instance()->get_option( 'enabled_post_types', array() );

		// Get the screen to find out where we are.
		$screen = get_current_screen();

		// Only load the styles and scripts on the settings page and the allowed post types.
		if ( 'settings_page_wp_to_diaspora' === $screen->id || ( in_array( $screen->post_type, $enabled_post_types ) && 'post' === $screen->base ) ) {
			wp_enqueue_style( 'tag-it', plugins_url( '/css/jquery.tagit.css', __FILE__ ) );
			wp_enqueue_style( 'chosen', plugins_url( '/css/chosen.min.css', __FILE__ ) );
			wp_enqueue_style( 'wp-to-diaspora-admin', plugins_url( '/css/wp-to-diaspora.css', __FILE__ ) );
			wp_enqueue_script( 'chosen', plugins_url( '/js/chosen.jquery.min.js', __FILE__ ), array( 'jquery' ), false, true );
			wp_enqueue_script( 'tag-it', plugins_url( '/js/tag-it.min.js', __FILE__ ), array( 'jquery', 'jquery-ui-autocomplete' ), false, true );
			wp_enqueue_script( 'wp-to-diaspora-admin', plugins_url( '/js/wp-to-diaspora.js', __FILE__ ), array( 'jquery' ), false, true );
			// Javascript-specific l10n.
			wp_localize_script( 'wp-to-diaspora-admin', 'WP2DL10n', array(
				'no_services_connected' => __( 'No services connected yet.', 'wp-to-diaspora' ),
				'sure_reset_defaults'   => __( 'Are you sure you want to reset to default values?', 'wp-to-diaspora' ),
				'conn_testing'          => __( 'Testing connection...', 'wp-to-diaspora' ),
				'conn_successful'       => __( 'Connection successful.', 'wp-to-diaspora' ),
				'conn_failed'           => __( 'Connection failed.', 'wp-to-diaspora' ),
			) );
		}
	}

	/**
	 * Add the "Settings" link to the plugins page.
	 *
	 * @param array $links Links to display for plugin on plugins page.
	 * @return array Links to display for plugin on plugins page.
	 */
	public function settings_link( $links ) {
		$links[] = '<a href="' . admin_url( 'options-general.php?page=wp_to_diaspora' ) . '">' . __( 'Settings' ) . '</a>';
		return $links;
	}

	/**
	 * Fetch the list of aspects or services and save them to the settings.
	 *
	 * NOTE: When updating the lists, always force a fresh fetch.
	 *
	 * @param string $type Type of list to update.
	 * @return array|boolean The list of aspects or services, false if an illegal parameter is passed.
	 */
	private function _update_aspects_services_list( $type ) {
		// Check for correct argument value.
		if ( ! in_array( $type, array( 'aspects', 'services' ) ) ) {
			return false;
		}

		$options = WP2D_Options::instance();
		$list    = $options->get_option( $type . '_list' );

		// Make sure that we have at least the 'Public' aspect.
		if ( 'aspects' === $type && empty( $list ) ) {
			$list = array( 'public' => __( 'Public' ) );
		}

		// Set up the connection to diaspora*.
		$api = $this->_load_api();

		// If there was a problem loading the API, return false.
		if ( $api->has_last_error() ) {
			return false;
		}

		if ( 'aspects' === $type ) {
			$list_new = $api->get_aspects( true );
		} elseif ( 'services' === $type ) {
			$list_new = $api->get_services( true );
		}
		// If the new list couldn't be fetched successfully, return false.
		if ( $api->has_last_error() ) {
			return false;
		}

		// We have a new list to save and return!
		$options->set_option( $type . '_list', $list_new );
		$options->save();

		return $list_new;
	}

	/**
	 * Update the list of aspects and return them for use with AJAX.
	 */
	public function update_aspects_list_callback() {
		wp_send_json( $this->_update_aspects_services_list( 'aspects' ) );
	}

	/**
	 * Update the list of services and return them for use with AJAX.
	 */
	public function update_services_list_callback() {
		wp_send_json( $this->_update_aspects_services_list( 'services' ) );
	}

	/**
	 * Check the pod connection status.
	 *
	 * @return string The status of the connection.
	 */
	private function _check_pod_connection_status() {
		$options = WP2D_Options::instance();

		$status = null;

		if ( $options->is_pod_set_up() ) {
			$status = ! $this->_load_api()->has_last_error();
		}

		return $status;
	}

	/**
	 * Check the connection to the pod and return the status for use with AJAX.
	 *
	 * @todo esc_html
	 */
	public function check_pod_connection_status_callback() {
		if ( isset( $_REQUEST['debugging'] ) && ! defined( 'WP2D_DEBUGGING' ) ) {
			define( 'WP2D_DEBUGGING', true );
		}

		$status = $this->_check_pod_connection_status();

		$data = array(
			'debug'   => esc_textarea( WP2D_Helpers::get_debugging() ),
			'message' => __( 'Connection successful.', 'wp-to-diaspora' ),
		);

		if ( true === $status ) {
			wp_send_json_success( $data );
		} elseif ( false === $status && $this->_load_api()->has_last_error() ) {
			$data['message'] = $this->_load_api()->get_last_error() . ' ' . WP2D_Contextual_Help::get_help_tab_quick_link( $this->_load_api()->get_last_error_object() );
			wp_send_json_error( $data );
		}
		// If $status === null, do nothing.
	}
}

// Get the party started!
WP_To_Diaspora::instance();
