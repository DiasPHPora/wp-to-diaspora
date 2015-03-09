<?php
/**
 * Plugin Options.
 *
 * @package WP_To_Diaspora
 * @subpackage Options
 * @since 1.3.0
 */

class WP2D_Options {

  /**
   * Have the options already been set up?
   *
   * @var boolean
   */
  private static $_is_set_up = false;

  /**
   * Only instance of this class.
   *
   * @var WP2D_Options
   */
  private static $_instance = null;

  /**
   * All default plugin options.
   *
   * @var array
   */
  private static $_default_options = array(
    'pod_list'           => array(),
    'aspects_list'       => array(),
    'post_to_diaspora'   => true,
    'enabled_post_types' => array( 'post' ),
    'fullentrylink'      => true,
    'display'            => 'full',
    'version'            => WP_TO_DIASPORA_VERSION
  );

  /**
   * Valid values for select fields.
   *
   * @var array
   */
  private static $_valid_values = array(
    'display'      => array( 'full', 'excerpt' ),
    'tags_to_post' => array( 'gcp', 'gc', 'gp', 'c', 'p', 'n' )
  );

  /**
   * All plugin options.
   *
   * @var array
   */
  private static $_options = null;

  /** Singleton, keep private. */
  final private function __clone() { }

  /** Singleton, keep private. */
  final private function __construct() { }

  /** Singleton, keep private. */
  final private function __wakeup() { }

  /**
   * Create / Get the instance of this class.
   *
   * @return WP2D_Options Instance of this class.
   */
  public static function get_instance() {
    if ( ! isset( self::$_instance ) ) {
      self::$_instance = new self();
    }
    return self::$_instance;
  }

  /**
   * Set up the options menu.
   */
  public static function setup() {
    // Get the unique instance.
    $instance = self::get_instance();

    // If the instance is already set up, just return it.
    if ( self::$_is_set_up ) {
      return $instance;
    }

    // Populate options array.
    $instance->get_option();

    // Add options page.
    add_options_page( 'WP to diaspora*', 'WP to diaspora*', 'manage_options', 'wp_to_diaspora', array( $instance, 'admin_options_page' ) );

    // Register all settings.
    add_action( 'admin_init', array( $instance, 'register_settings' ) );

    // The instance has been set up.
    self::$_is_set_up = true;

    return $instance;
  }

  /**
   * Set up admin options page.
   */
  public function admin_options_page() {
    ?>
    <div class="wrap">
      <h2>WP to diaspora*</h2>

      <?php
        // Set up the connection to Diaspora.
        $conn = new WP2D_API( $this->get_option( 'pod' ) );
        if ( $conn->init() && $conn->login( $this->get_option( 'username' ), WP2D_Helpers::decrypt( $this->get_option( 'password' ) ) ) ) {
          // Show success message if connected successfully.
          add_settings_error(
            'wp_to_diaspora_settings',
            'wp_to_diaspora_connected',
            sprintf( __( 'Connected to %s', 'wp_to_diaspora' ), $conn->get_pod_url() ),
            'updated'
          );
        } else {
          // Show error message if connection failed.
          add_settings_error(
            'wp_to_diaspora_settings',
            'wp_to_diaspora_connected',
            sprintf( __( 'Couldn\'t connect to "%1$s": %2$s', 'wp_to_diaspora' ), $conn->get_pod_url(), $conn->last_error ),
            'error'
          );
        }

        // Output success or error message.
        settings_errors( 'wp_to_diaspora_settings' );
      ?>

      <?php if ( defined( 'WP2D_DEBUGGING' ) && WP2D_DEBUGGING ) : ?>
        <h3>Debug Info</h3>
        <textarea rows="5" cols="50"><?php echo WP2D_Helpers::get_debugging(); ?></textarea>
      <?php endif; ?>

      <form action="options.php" method="post">
        <?php settings_fields( 'wp_to_diaspora_settings' ); ?>
        <?php do_settings_sections( 'wp_to_diaspora_settings' ); ?>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  /**
   * Initialise the settings sections and fields.
   */
  public function register_settings() {
      // Register the settings with validation callback.
    register_setting( 'wp_to_diaspora_settings', 'wp_to_diaspora_settings', array( $this, 'validate_settings' ) );

    // Add a "Setup" section that contains the Pod domain, Username and Password.
    add_settings_section( 'wp_to_diaspora_setup_section', __( 'diaspora* Setup', 'wp_to_diaspora' ), array( $this, 'setup_section' ), 'wp_to_diaspora_settings' );

    // Add a "Defaults" section that contains all posting settings to be used by default.
    add_settings_section( 'wp_to_diaspora_defaults_section', __( 'Posting Defaults', 'wp_to_diaspora' ), array( $this, 'defaults_section' ), 'wp_to_diaspora_settings' );
  }

  /**
   * Callback for the "Setup" section.
   */
  public function setup_section() {
    _e( 'Set up the connection to your diaspora* account.', 'wp_to_diaspora' );

    // Pod entry field.
    add_settings_field( 'pod', __( 'diaspora* Pod', 'wp_to_diaspora' ), array( $this, 'pod_render' ), 'wp_to_diaspora_settings', 'wp_to_diaspora_setup_section' );

    // Username entry field.
    add_settings_field( 'username', __( 'Username' ), array( $this, 'username_render' ), 'wp_to_diaspora_settings', 'wp_to_diaspora_setup_section' );

    // Password entry field.
    add_settings_field( 'password', __( 'Password' ), array( $this, 'password_render' ), 'wp_to_diaspora_settings', 'wp_to_diaspora_setup_section' );
  }

  /**
   * Render the "Pod" field.
   */
  public function pod_render() {
    ?>
    https://<input type="text" name="wp_to_diaspora_settings[pod]" value="<?php echo $this->get_option( 'pod' ); ?>" placeholder="e.g. joindiaspora.com" autocomplete="on" list="pod-list" required> <a id="refresh-pod-list" class="button"><?php _e( 'Refresh pod list', 'wp_to_diaspora' ); ?></a><span class="spinner" style="display: none;"></span>
    <datalist id="pod-list">
    <?php foreach ( $this->get_option( 'pod_list' ) as $pod ) : ?>
      <option data-secure="<?php echo $pod['secure']; ?>" value="<?php echo $pod['domain']; ?>"></option>
    <?php endforeach; ?>
    </datalist>
    <?php
  }

  /**
   * Render the "Username" field.
   */
  public function username_render() {
    ?>
    <input type="text" name="wp_to_diaspora_settings[username]" value="<?php echo $this->get_option( 'username' ); ?>" placeholder="<?php _e( 'Username' ); ?>" required>
    <?php
  }

  /**
   * Render the "Password" field.
   */
  public function password_render() {
    // Special case if we already have a password.
    $has_password = ( '' !== $this->get_option( 'password', '' ) );
    $placeholder  = ( $has_password ) ? __( 'Password already set', 'wp_to_diaspora' ) : __( 'Password' );
    $required     = ( $has_password ) ? '' : ' required';
    ?>
    <input type="password" name="wp_to_diaspora_settings[password]" value="" placeholder="<?php echo $placeholder; ?>"<?php echo $required; ?>>
    <?php if ( $has_password ) : ?>
      <p class="description"><?php _e( 'If you would like to change the password type a new one. Otherwise leave this blank.', 'wp_to_diaspora' ); ?></p>
    <?php endif;
  }


  /**
   * Callback for the "Defaults" section.
   */
  public function defaults_section() {
    _e( 'Define the default posting behaviour for all posts here. These settings can be modified for each post individually, by changing the values in the "WP to diaspora*" meta box, which gets displayed in your post edit screen.', 'wp_to_diaspora' );

    // Post types field.
    add_settings_field( 'enabled_post_types', __( 'Post types', 'wp_to_diaspora' ), array( $this, 'post_types_render' ), 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section' );

     // Post to diaspora* checkbox.
    add_settings_field( 'post_to_diaspora', __( 'Post to diaspora*', 'wp_to_diaspora' ), array( $this, 'post_to_diaspora_render' ), 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'post_to_diaspora' ) );

    // Full entry link checkbox.
    add_settings_field( 'fullentrylink', __( 'Show "Posted at" link?', 'wp_to_diaspora' ), array( $this, 'fullentrylink_render' ), 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'fullentrylink' ) );

    // Full text or excerpt radio buttons.
    add_settings_field( 'display', __( 'Display', 'wp_to_diaspora' ), array( $this, 'display_render' ), 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'display' ) );

    // Tags to post dropdown.
    add_settings_field( 'tags_to_post', __( 'Tags to post', 'wp_to_diaspora' ), array( $this, 'tags_to_post_render' ), 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'tags_to_post', 'gc' ) );

    // Global tags field.
    add_settings_field( 'global_tags', __( 'Global tags', 'wp_to_diaspora' ), array( $this, 'global_tags_render' ), 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'global_tags' ) );

    // Aspects checkboxes.
    add_settings_field( 'aspects', __( 'Aspects', 'wp_to_diaspora' ), array( $this, 'aspects_render' ), 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'aspects' ) );

    // Services checkboxes.
    add_settings_field( 'services', __( 'Services', 'wp_to_diaspora' ), array( $this, 'services_render' ), 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'services' ) );
  }

  /**
   * Render the "Post types" checkboxes.
   */
  public function post_types_render() {
    $post_types = get_post_types( array( 'public' => true ), 'objects' );

    // Remove excluded post types from the list.
    $excluded_post_types = array( 'attachment', 'nav_menu_item', 'revision' );
    foreach ( $excluded_post_types as $excluded ) {
      unset( $post_types[ $excluded ] );
    }

    // Set up the tabs and content boxes.
    $tabs     = '';
    $contents = '';
    foreach ( $post_types as $type ) {
      $name = $type->label;
      $slug = $type->name;

      $tabs .= '<li class="tab-link" data-tab="tab-' . $slug . '">' . $name . '</li>';
      $contents .= sprintf('
        <div id="tab-%1$s" class="tab-content">
          <label><input type="checkbox" name="wp_to_diaspora_settings[enabled_post_types][]" value="%1$s" %2$s>%3$s</label>
        </div>',
        $slug,
        checked( in_array( $slug, $this->get_option( 'enabled_post_types' ) ), true, false ),
        sprintf( __( 'Enable WP to diaspora* on %s', 'wp_to_diaspora' ), $name )
      );
    }

    echo '<div class="post-types-container"><ul class="tabs">' . $tabs . '</ul>' . $contents . '</div>';
  }

  /**
   * Render the "Post to diaspora*" checkbox.
   */
  public function post_to_diaspora_render( $post_to_diaspora ) {
    $label = ( 'settings_page_wp_to_diaspora' === get_current_screen()->id ) ? __( 'Yes' ) : __( 'Post to diaspora*', 'wp_to_diaspora' );
    ?>
    <label><input type="checkbox" id="post-to-diaspora" name="wp_to_diaspora_settings[post_to_diaspora]" value="1" <?php checked( $post_to_diaspora ); ?>><?php echo $label; ?></label>
    <?php
  }

  /**
   * Render the "Show 'Posted at' link" checkbox.
   */
  public function fullentrylink_render( $show_link ) {
    $description = __( 'Include a link back to your original post.', 'wp_to_diaspora' );
    $checkbox = '<input type="checkbox" id="fullentrylink" name="wp_to_diaspora_settings[fullentrylink]" value="1"' . checked( $show_link, true, false ) . '>';

    if ( 'settings_page_wp_to_diaspora' === get_current_screen()->id ) : ?>
      <label><?php echo $checkbox; ?><?php _e( 'Yes' ); ?></label>
      <p class="description"><?php echo $description; ?></p>
    <?php else : ?>
      <label title="<?php echo $description; ?>"><?php echo $checkbox; _e( 'Show "Posted at" link?', 'wp_to_diaspora' ); ?></label>
    <?php endif;
  }

  /**
   * Render the "Display" radio buttons.
   */
  public function display_render( $display ) {
    ?>
    <label><input type="radio" name="wp_to_diaspora_settings[display]" value="full" <?php checked( $display, 'full' ); ?>><?php _e( 'Full Post', 'wp_to_diaspora' ); ?></label><br />
    <label><input type="radio" name="wp_to_diaspora_settings[display]" value="excerpt" <?php checked( $display, 'excerpt' ); ?>><?php _e( 'Excerpt' ); ?></label>
    <?php
  }

  /**
   * Render the "Tags to post" field.
   */
  public function tags_to_post_render( $tags_to_post ) {
    $on_settings_page = ( 'settings_page_wp_to_diaspora' === get_current_screen()->id );
    $description = __( 'Choose which tags should be posted to diaspora*.', 'wp_to_diaspora' );

    if ( ! $on_settings_page ) {
      echo '<label>' . $description;
    }

    ?>
    <select id="tags-to-post" name="wp_to_diaspora_settings[tags_to_post]">
      <option value="gcp" <?php selected( $tags_to_post, 'gcp' ); ?>><?php _e( 'All tags (global, custom and post tags)', 'wp_to_diaspora' ); ?></option>
      <option value="gc" <?php  selected( $tags_to_post, 'gc' );  ?>><?php _e( 'Global and custom tags', 'wp_to_diaspora' ); ?></option>
      <option value="gp" <?php  selected( $tags_to_post, 'gp' );  ?>><?php _e( 'Global and post tags', 'wp_to_diaspora' ); ?></option>
      <option value="c" <?php   selected( $tags_to_post, 'c' );   ?>><?php _e( 'Only custom tags', 'wp_to_diaspora' ); ?></option>
      <option value="p" <?php   selected( $tags_to_post, 'p' );   ?>><?php _e( 'Only post tags', 'wp_to_diaspora' ); ?></option>
      <option value="n" <?php   selected( $tags_to_post, 'n' );   ?>><?php _e( 'No tags', 'wp_to_diaspora' ); ?></option>
    </select>
    <?php if ( $on_settings_page ) : ?>
      <p class="description"><?php echo $description; ?></p>
    <?php else : ?>
      </label>
    <?php endif;
  }

  /**
   * Render the "Global tags" field.
   */
  public function global_tags_render( $tags ) {
    if ( is_array( $tags ) ) {
      $tags = implode( ', ', $tags );
    }
    ?>
    <input type="text" class="wp2dtags" name="wp_to_diaspora_settings[global_tags]" value="<?php echo $tags; ?>" placeholder="<?php _e( 'Global tags', 'wp_to_diaspora' ); ?>" class="regular-text">
    <p class="description"><?php _e( 'Custom tags to add to all posts being posted to diaspora*.', 'wp_to_diaspora' ); ?></p>
    <?php
  }

  /**
   * Render the "Custom tags" field.
   */
  public function custom_tags_render( $tags ) {
    if ( is_array( $tags ) ) {
      $tags = implode( ', ', $tags );
    }
    ?>
    <label title="<?php _e( 'Custom tags to add to this post when it\'s posted to diaspora*.', 'wp_to_diaspora' ); ?>">
      <?php _e( 'Custom tags', 'wp_to_diaspora' ); ?>
      <input type="text" class="wp2dtags" name="wp_to_diaspora_settings[custom_tags]" value="<?php echo $tags; ?>" class="widefat">
    </label>
    <p class="description"><?php _e( 'Separate tags with commas' ); ?></p>
    <?php
  }

  /**
   * Render the "Aspects" checkboxes.
   */
  public function aspects_render( $aspects ) {
    // Special case for this field if it's displayed on the settings page.
    $on_settings_page = ( 'settings_page_wp_to_diaspora' === get_current_screen()->id );
    $aspects          = ( ! empty( $aspects ) && is_array( $aspects ) ) ? $aspects : array();
    $description      = __( 'Choose which aspects to share to.', 'wp_to_diaspora' );

    if ( ! $on_settings_page ) {
      echo $description;
    }
    ?>
    <div id="aspects-container" data-aspects-selected="<?php echo implode( ',', $aspects ); ?>">
      <?php
      if ( $aspects_list = $this->get_option( 'aspects_list' ) ) {
        foreach ( $aspects_list as $aspect_id => $aspect_name ) {
          ?>
          <label><input type="checkbox" name="wp_to_diaspora_settings[aspects][]" value="<?php echo $aspect_id; ?>" <?php checked( in_array( $aspect_id, $aspects ) ); ?>><?php echo $aspect_name; ?></label>
          <?php
        }
      } else {
        // Just add the default "Public" aspect.
        ?>
        <label><input type="checkbox" name="wp_to_diaspora_settings[aspects][]" value="public" <?php checked( true ); ?>><?php _e( 'Public' ); ?></label>
        <?php
      }
      ?>
    </div>
    <p class="description"><?php if ( $on_settings_page ) { echo $description; } ?> <a id="refresh-aspects-list" class="button"><?php _e( 'Refresh Aspects', 'wp_to_diaspora' ); ?></a><span class="spinner" style="display: none;"></span></p>
    <?php
  }

  /**
   * Render the "Services" checkboxes.
   */
  public function services_render( $services ) {
    // Special case for this field if it's displayed on the settings page.
    $on_settings_page = ( 'settings_page_wp_to_diaspora' === get_current_screen()->id );
    $services         = ( ! empty( $services ) && is_array( $services ) ) ? $services : array();
    $description      = __( 'Choose which services to share to.', 'wp_to_diaspora' );

    if ( ! $on_settings_page ) {
      echo $description;
    }
    ?>
    <div id="services-container" data-services-selected="<?php echo implode( ',', $services ); ?>">
      <?php
      if ( $services_list = $this->get_option( 'services_list' ) ) {
        foreach ( $services_list as $service_id => $service_name ) {
          ?>
          <label><input type="checkbox" name="wp_to_diaspora_settings[services][]" value="<?php echo $service_id; ?>" <?php checked( in_array( $service_id, $services ) ); ?>><?php echo $service_name; ?></label>
          <?php
        }
      } else {
        // No services loaded yet.
        ?>
        <label><?php _e( 'No connected services loaded yet.', 'wp_to_diaspora' ); ?></label>
        <?php
      }
      ?>
    </div>
    <p class="description"><?php if ( $on_settings_page ) { echo $description; } ?> <a id="refresh-services-list" class="button"><?php _e( 'Refresh Services', 'wp_to_diaspora' ); ?></a><span class="spinner" style="display: none;"></span></p>
    <?php
  }


  /**
   * Get a specific option.
   *
   * @param  string $option  ID of option to get.
   * @param  string $default Override default value if option not found.
   * @return object          Requested option value.
   */
  public function get_option( $option = null, $default = null ) {
    if ( ! isset( self::$_options ) ) {
      self::$_options = get_option( 'wp_to_diaspora_settings', self::$_default_options );
    }
    if ( isset( $option ) ) {
      if ( isset( self::$_options[ $option ] ) ) {
        // Return found option value.
        return self::$_options[ $option ];
      } elseif ( isset( $default ) ) {
        // Return overridden default value.
        return $default;
      } elseif ( isset( self::$_default_options[ $option ] ) ) {
        // Return default option value.
        return self::$_default_options[ $option ];
      }
    }
  }

  /**
   * Get all options.
   *
   * @return array All the options.
   */
  public function get_options() {
    return self::$_options;
  }

  /**
   * Set a certain option.
   *
   * @param string  $option ID of option to get.
   * @param string  $value  Value to be set for the passed option.
   * @param boolean $save   Save the options immediately after setting them?
   */
  public function set_option( $option, $value, $save = false ) {
    if ( isset( $option ) ) {
      if ( isset( $value ) ) {
        self::$_options[ $option ] = $value;
      } else {
        unset( self::$_options[ $option ] );
      }
    }
    if ( $save ) {
      self::save();
    }
  }

  /**
   * Save the options.
   */
  public function save() {
    update_option( 'wp_to_diaspora_settings', self::$_options );
  }


  /**
   * Get all valid input values for the passed field.
   *
   * @param  string $field Field to get the valid values for.
   * @return array         List of valid values.
   */
  public function get_valid_values( $field ) {
    if ( array_key_exists( $field, self::$_valid_values ) ) {
      return self::$_valid_values[ $field ];
    }
  }

  /**
   * Check if a value is valid for the passed field.
   *
   * @param  string  $field Field to check the valid value for.
   * @param  object  $value Value to check validity.
   * @return boolean        If the passed value is valid.
   */
  public function is_valid_value( $field, $value ) {
    if ( $valids = self::get_valid_values( $field ) ) {
      return ( in_array( $value, $valids ) );
    }
    return false;
  }

  /**
   * Validate all settings.
   *
   * @param  array $input RAW input values.
   * @return array        Validated input values.
   */
  public function validate_settings( $input ) {
    // Validate all settings before saving to the database.
    $input['pod']      = trim( sanitize_text_field( $input['pod'] ), ' /' );
    $input['username'] = sanitize_text_field( $input['username'] );
    $input['password'] = sanitize_text_field( $input['password'] );

    // If password is blank, it hasn't been changed.
    // If new password is equal to the encrypted password already saved, it was just passed again. It happens everytime update_option('wp_to_diaspora_settings') is called.
    if ( '' === $input['password'] || $this->get_option( 'password' ) === $input['password'] ) {
      unset( $input['password'] );
    } else {
      $input['password'] = WP2D_Helpers::encrypt( $input['password'] );
    }

    if ( ! isset( $input['enabled_post_types'] ) ) {
      $input['enabled_post_types'] = array();
    }

    // Checkboxes.
    foreach ( array( 'post_to_diaspora', 'fullentrylink' ) as $option ) {
      $input[ $option ] = isset( $input[ $option ] );
    }

    // Selects.
    foreach ( array( 'display', 'tags_to_post' ) as $option ) {
      if ( isset( $input[ $option ] ) && ! $this->is_valid_value( $option, $input[ $option ] ) ) {
        unset( $input[ $option ] );
      }
    }

    // Get unique, non-empty, trimmed tags and clean them up.
    $input['global_tags'] = WP2D_Helpers::get_clean_tags( $input['global_tags'] );

    // Clean up the list of aspects. If the list is empty, only use the 'Public' aspect.
    if ( empty( $input['aspects'] ) || ! is_array( $input['aspects'] ) ) {
      $input['aspects'] = array( 'public' );
    } else {
      array_walk( $input['aspects'], 'sanitize_text_field' );
    }

    // Clean up the list of services.
    if ( empty( $input['services'] ) || ! is_array( $input['services'] ) ) {
      $input['services'] = array();
    } else {
      array_walk( $input['services'], 'sanitize_text_field' );
    }

    // Parse inputs with default options and return.
    return wp_parse_args( $input, array_merge( self::$_default_options, self::$_options ) );
  }

}



?>
