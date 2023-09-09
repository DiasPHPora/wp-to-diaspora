<?php
/**
 * Main plugin initialisation class.
 *
 * @package WP_To_Diaspora\Core
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WP to diaspora* main plugin class.
 */
class WP2D {

	/**
	 * Only instance of this class.
	 *
	 * @var WP2D|null
	 */
	private static ?WP2D $instance = null;

	/**
	 * The minimum required WordPress version.
	 *
	 * @since 1.5.4
	 *
	 * @var string
	 */
	private string $min_wp = '4.6-src';

	/**
	 * The minimum required PHP version.
	 *
	 * @since 1.5.4
	 *
	 * @var string
	 */
	private string $min_php = '8.0';

	/**
	 * Instance of the API class.
	 *
	 * @var WP2D_API|null
	 */
	private ?WP2D_API $api = null;

	/**
	 * Create / Get the instance of this class.
	 *
	 * @return WP2D|null Instance of this class.
	 */
	public static function instance(): ?WP2D {
		if ( null === self::$instance ) {
			self::$instance = new self();
			if ( self::$instance->version_check() ) {
				self::$instance->constants();
				self::$instance->setup();
			} else {
				self::$instance = null;
			}
		}

		return self::$instance;
	}

	/**
	 * Define all the required constants.
	 *
	 * @since 1.5.0
	 */
	private function constants(): void {
		// Are we in debugging mode?
		if ( isset( $_GET['debugging'] ) ) { // phpcs:ignore
			define( 'WP2D_DEBUGGING', true );
		}

		define( 'WP2D_DIR', dirname( __DIR__ ) );
		define( 'WP2D_LIB_DIR', WP2D_DIR . '/lib' );

		// Fall back to WordPress AUTH_KEY for password encryption.
		defined( 'WP2D_ENC_KEY' ) || define( 'WP2D_ENC_KEY', AUTH_KEY );
	}

	/**
	 * Check the minimum WordPress and PHP requirements.
	 *
	 * @since 1.5.4
	 *
	 * @return bool If version requirements are met.
	 */
	private function version_check(): bool {
		// Check for version requirements.
		if ( version_compare( PHP_VERSION, $this->min_php, '<' ) || version_compare( $GLOBALS['wp_version'], $this->min_wp, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'deactivate' ] );

			return false;
		}

		return true;
	}

	/**
	 * Callback to deactivate plugin and display admin notice.
	 *
	 * @since 1.5.4
	 */
	public function deactivate(): void {
		// Deactivate this plugin.
		deactivate_plugins( WP2D_BASENAME );

		// Get rid of the "Plugin activated" message.
		unset( $_GET['activate'] ); // phpcs:ignore

		// Display the admin notice.
		?>
		<div class="error">
			<p><?php echo esc_html( sprintf( 'WP to diaspora* requires at least WordPress %1$s (you have %2$s) and PHP %3$s (you have %4$s)!', $this->min_wp, $GLOBALS['wp_version'], $this->min_php, PHP_VERSION ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Set up the plugin.
	 */
	private function setup(): void {
		// Add "Settings" link to plugin page.
		add_filter( 'plugin_action_links_' . WP2D_BASENAME, [ $this, 'settings_link' ] );

		// Perform any necessary data upgrades.
		add_action( 'admin_init', [ $this, 'upgrade' ] );

		// Admin notice when the AUTH_KEY has changed and credentials need to be re-saved.
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );

		// Enqueue CSS and JS scripts.
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_load_scripts' ] );

		// Set up the options.
		add_action( 'init', [ 'WP2D_Options', 'instance' ] );

		// WP2D Post.
		add_action( 'init', [ 'WP2D_Post', 'setup' ] );

		// AJAX actions for loading aspects and services.
		add_action( 'wp_ajax_wp_to_diaspora_update_aspects_list', [ $this, 'update_aspects_list_callback' ] );
		add_action( 'wp_ajax_wp_to_diaspora_update_services_list', [ $this, 'update_services_list_callback' ] );

		// Check the pod connection status on the options page.
		add_action( 'wp_ajax_wp_to_diaspora_check_pod_connection_status', [ $this, 'check_pod_connection_status_callback' ] );
	}

	/**
	 * Load the diaspora* API for ease of use.
	 *
	 * @return WP2D_API The API object.
	 */
	private function load_api(): WP2D_API {
		if ( null === $this->api ) {
			$this->api = WP2D_Helpers::api_quick_connect();
		}

		return $this->api;
	}

	/**
	 * Initialise upgrade sequence.
	 */
	public function upgrade(): void {
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
				$tags_to_post     = array_filter( [
					str_contains( $tags_to_post_old, 'g' ) ? 'global' : null,
					str_contains( $tags_to_post_old, 'c' ) ? 'custom' : null,
					str_contains( $tags_to_post_old, 'p' ) ? 'post' : null,
				] );
				$options->set_option( 'tags_to_post', $tags_to_post );
			}

			// Encryption key is set in WP2D_ENC_KEY since version 2.2.0.
			if ( version_compare( $version, '2.2.0', '<' ) ) {
				// Remember AUTH_KEY hash to notice a change.
				$options->set_option( 'auth_key_hash', md5( AUTH_KEY ) );

				// Upgrade encrypted password if new WP2D_ENC_KEY is used.
				$options->attempt_password_upgrade();
			}

			// Update version.
			$options->set_option( 'version', WP2D_VERSION );
			$options->save();
		}
	}

	/**
	 * Load scripts and styles for Settings and Post pages of allowed post types.
	 */
	public function admin_load_scripts(): void {
		// Get the enabled post types to load the script for.
		$enabled_post_types = WP2D_Options::instance()->get_option( 'enabled_post_types', [] );

		// Get the screen to find out where we are.
		$screen = get_current_screen();

		// Only load the styles and scripts on the settings page and the allowed post types.
		if ( 'settings_page_wp_to_diaspora' === $screen?->id || ( 'post' === $screen?->base && in_array( $screen?->post_type, $enabled_post_types, true ) ) ) {
			wp_enqueue_style( 'tag-it', plugins_url( '/css/tag-it.min.css', WP2D_BASENAME ), [], WP2D_VERSION );
			wp_enqueue_style( 'chosen', plugins_url( '/css/chosen.min.css', WP2D_BASENAME ), [], WP2D_VERSION );
			wp_enqueue_style( 'wp-to-diaspora-admin', plugins_url( '/css/wp-to-diaspora.css', WP2D_BASENAME ), [], WP2D_VERSION );
			wp_enqueue_script( 'chosen', plugins_url( '/js/chosen.jquery.min.js', WP2D_BASENAME ), [ 'jquery' ], WP2D_VERSION, true );
			wp_enqueue_script( 'tag-it', plugins_url( '/js/tag-it.jquery.min.js', WP2D_BASENAME ), [ 'jquery', 'jquery-ui-autocomplete' ], WP2D_VERSION, true );
			wp_enqueue_script( 'wp-to-diaspora-admin', plugins_url( '/js/wp-to-diaspora.js', WP2D_BASENAME ), [ 'jquery' ], WP2D_VERSION, true );
			// Javascript-specific l10n.
			wp_localize_script( 'wp-to-diaspora-admin', 'WP2D', [
				'_nonce'                => wp_create_nonce( 'wp2d' ),
				'nonce_failure'         => __( 'AJAX Nonce failure.', 'wp-to-diaspora' ),
				'resave_credentials'    => __( 'Resave your credentials and try again.', 'wp-to-diaspora' ),
				'no_services_connected' => __( 'No services connected yet.', 'wp-to-diaspora' ),
				'sure_reset_defaults'   => __( 'Are you sure you want to reset to default values?', 'wp-to-diaspora' ),
				'conn_testing'          => __( 'Testing connection...', 'wp-to-diaspora' ),
				'conn_successful'       => __( 'Connection successful.', 'wp-to-diaspora' ),
				'conn_failed'           => __( 'Connection failed.', 'wp-to-diaspora' ),
			] );
		}
	}

	/**
	 * Add "AUTH_KEY" changed admin notice.
	 *
	 * @since 2.2.0
	 */
	public function admin_notices(): void {
		// If a custom WP2D_ENC_KEY is set, it doesn't matter if the AUTH_KEY has changed.
		if ( AUTH_KEY !== WP2D_ENC_KEY ) {
			return;
		}

		$options = WP2D_Options::instance();

		$auth_hash_key = $options->get_option( 'auth_key_hash' );
		if ( $auth_hash_key && md5( AUTH_KEY ) !== $auth_hash_key ) {
			printf( '<div class="error notice is-dismissible"><p>%1$s</p></div>',
				sprintf(
					esc_html_x( 'Looks like your WordPress secret keys have changed! Please %1$sre-save your login info%2$s.', 'placeholders are link tags to the settings page.', 'wp-to-diaspora' ),
					'<a href="' . esc_url( admin_url( 'options-general.php?page=wp_to_diaspora' ) ) . '&amp;tab=setup" target="_blank">',
					'</a>'
				)
			);
		}
	}

	/**
	 * Add the "Settings" link to the plugins page.
	 *
	 * @param array $links Links to display for plugin on plugins page.
	 *
	 * @return array Links to display for plugin on plugins page.
	 */
	public function settings_link( array $links ): array {
		$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=wp_to_diaspora' ) ) . '">' . __( 'Settings', 'wp-to-diaspora' ) . '</a>';

		return $links;
	}

	/**
	 * Fetch the list of aspects or services and save them to the settings.
	 *
	 * NOTE: When updating the lists, always force a fresh fetch.
	 *
	 * @param string $type Type of list to update.
	 *
	 * @return array|bool The list of aspects or services, false if an illegal parameter is passed.
	 */
	private function update_aspects_services_list( string $type ): bool|array {
		// Check for correct argument value.
		if ( ! in_array( $type, [ 'aspects', 'services' ], true ) ) {
			return false;
		}

		$options = WP2D_Options::instance();
		$list    = $options->get_option( $type . '_list' );

		// Make sure that we have at least the 'Public' aspect.
		if ( 'aspects' === $type && empty( $list ) ) {
			$list = [ 'public' => __( 'Public', 'wp-to-diaspora' ) ];
		}

		// Set up the connection to diaspora*.
		$api = $this->load_api();

		// If there was a problem loading the API, return false.
		if ( $api->has_last_error() ) {
			return false;
		}

		$list_new = $list;
		if ( 'aspects' === $type ) {
			$list_new = $api->get_aspects( true );
		}
		if ( 'services' === $type ) {
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
	public function update_aspects_list_callback(): void {
		check_ajax_referer( 'wp2d', 'nonce' );
		wp_send_json( $this->update_aspects_services_list( 'aspects' ) );
	}

	/**
	 * Update the list of services and return them for use with AJAX.
	 */
	public function update_services_list_callback(): void {
		check_ajax_referer( 'wp2d', 'nonce' );
		wp_send_json( $this->update_aspects_services_list( 'services' ) );
	}

	/**
	 * Check the pod connection status.
	 *
	 * @return bool|null The status of the connection.
	 */
	private function check_pod_connection_status(): ?bool {
		$options = WP2D_Options::instance();

		$status = null;

		if ( $options->is_pod_set_up() ) {
			$status = ! $this->load_api()->has_last_error();
		}

		return $status;
	}

	/**
	 * Check the connection to the pod and return the status for use with AJAX.
	 *
	 * @todo esc_html
	 */
	public function check_pod_connection_status_callback(): void {
		if ( ! defined( 'WP2D_DEBUGGING' ) && isset( $_REQUEST['debugging'] ) ) { // phpcs:ignore
			define( 'WP2D_DEBUGGING', true );
		}

		if ( ! check_ajax_referer( 'wp2d', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid AJAX nonce', 'wp-to-diaspora' ) ] );
		}

		$status = $this->check_pod_connection_status();

		$data = [
			'debug'   => esc_textarea( WP2D_Helpers::get_debugging() ),
			'message' => __( 'Connection successful.', 'wp-to-diaspora' ),
		];

		if ( true === $status ) {
			wp_send_json_success( $data );
		} elseif ( false === $status && $this->load_api()->has_last_error() ) {
			$data['message'] = $this->load_api()->get_last_error() . ' ' . WP2D_Contextual_Help::get_help_tab_quick_link( $this->load_api()->get_last_error_object() );
			wp_send_json_error( $data );
		}
		// If $status === null, do nothing.
	}
}
