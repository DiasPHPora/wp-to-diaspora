<?php

/**
 * Plugin Contextual Help.
 *
 * @package WP_To_Diaspora
 * @subpackage Help
 * @since 1.4.0
 */

// Define external link constants.
define( 'WP2D_EXT_WPORG',         'https://wordpress.org/plugins/wp-to-diaspora' );
define( 'WP2D_EXT_I18N',          'https://poeditor.com/join/project?hash=c085b3654a5e04c69ec942e0f136716a' );
define( 'WP2D_EXT_GH',            'https://github.com/gutobenn/wp-to-diaspora' );
define( 'WP2D_EXT_DONATE',        'https://github.com/gutobenn/wp-to-diaspora#donate' );
define( 'WP2D_EXT_GH_ISSUES',     'https://github.com/gutobenn/wp-to-diaspora/issues' );
define( 'WP2D_EXT_GH_ISSUES_NEW', 'https://github.com/gutobenn/wp-to-diaspora/issues/new' );

class WP2D_Contextual_Help {

 /**
   * Has the contextual help already been set up?
   *
   * @var boolean
   */
  private static $_is_set_up = false;

  /**
   * Only instance of this class.
   *
   * @var WP2D_Contextual_Help
   */
  private static $_instance = null;

  /**
   * The tab information for the settings page including the content.
   *
   * @var array
   */
  private $_settings_tabs = array();

  /** Singleton, keep private. */
  final private function __clone() { }

  /** Singleton, keep private. */
  final private function __wakeup() { }

  /** Singleton, keep private. */
  final private function __construct() {
    $this->_settings_tabs = array(
      // A short overview of the plugin.
      'overview' => array(
        'title'   => esc_html__( 'Overview', 'wp_to_diaspora' ),
        'content' => '<p><strong>' . esc_html__( 'With WP to diaspora*, sharing your WordPress posts to diaspora* is as easy as ever.', 'wp_to_diaspora' ) . '</strong></p>
          <ol>
            <li>' . esc_html__( 'Enter your diaspora* login details on the "Setup" tab.', 'wp_to_diaspora' ) . '
            <li>' . esc_html__( 'Define the default posting behaviour on the "Defaults" tab.', 'wp_to_diaspora' ) . '
            <li>' . esc_html__( 'Automatically share your WordPress post on diaspora* when publishing it on your website.', 'wp_to_diaspora' ) . '
            <li>' . esc_html__( 'Check out your new post on diaspora*.', 'wp_to_diaspora' ) . '
          </ol>'
      ),
      // How to set up the connection to diaspora*.
      'setup' => array(
        'title'   => esc_html__( 'Setup', 'wp_to_diaspora' ),
        'content' => '<p><strong>' . esc_html__( 'Enter your diaspora* login details to connect your account.', 'wp_to_diaspora' ) . '</strong></p>
          <ul>
            <li><strong>' . esc_html__( 'diaspora* Pod', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'This is the domain name of the pod you are on (e.g. joindiaspora.com)', 'wp_to_diaspora' ) . '<br>
                <em>' . sprintf( esc_html__( 'Use the "%s" button to prepopulate the input field to help choose your pod.', 'wp_to_diaspora' ), esc_html__( 'Refresh pod list', 'wp_to_diaspora' ) ) . '</em>
            <li><strong>' . esc_html__( 'Username', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'Your diaspora* username (without the pod domain).', 'wp_to_diaspora' ) . '
            <li><strong>' . esc_html__( 'Password', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'Your diaspora* password.', 'wp_to_diaspora' ) . '
          </ul>'
      ),
      // Explain the default options and what they do.
      'defaults' => array(
        'title'   => esc_html__( 'Defaults', 'wp_to_diaspora' ),
        'content' => '<p><strong>' . esc_html__( 'Define the default posting behaviour.', 'wp_to_diaspora' ) . '</strong></p>
          <ul>
            <li><strong>' . esc_html__( 'Post types', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'Choose the post types that are allowed to be shared to diaspora*.', 'wp_to_diaspora' ) . '
            <li><strong>' . esc_html__( 'Post to diaspora*', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'Automatically share new posts to diaspora* when publishing them.', 'wp_to_diaspora' ) . '
            <li><strong>' . esc_html__( 'Show "Posted at" link?', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'Add a link back to your original post, at the bottom of the diaspora* post.', 'wp_to_diaspora' ) . '
            <li><strong>' . esc_html__( 'Display', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'Choose whether you would like to post the whole post or just the excerpt.', 'wp_to_diaspora' ) . '
            <li><strong>' . esc_html__( 'Tags to post', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'You can add tags to your post to make it easier to find on diaspora*.' ) . '<br>
                <ul>
                  <li><strong>' . esc_html__( 'Global tags', 'wp_to_diaspora' ) . '</strong>: ' . esc_html__( 'Tags that apply to all posts.', 'wp_to_diaspora' ) . '
                  <li><strong>' . esc_html__( 'Custom tags', 'wp_to_diaspora' ) . '</strong>: ' . esc_html__( 'Tags that apply to individual posts (can be set on each post).', 'wp_to_diaspora' ) . '
                  <li><strong>' . esc_html__( 'Post tags',   'wp_to_diaspora' ) . '</strong>: ' . esc_html__( 'Default WordPress Tags of individual posts.', 'wp_to_diaspora' ) . '
                </ul>
            <li><strong>' . esc_html__( 'Global tags', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'A list of tags that gets added to every post.', 'wp_to_diaspora' ) . '
            <li><strong>' . esc_html__( 'Aspects', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'Decide which of your diaspora* aspects can see your posts.', 'wp_to_diaspora' ) . '<br>
                <em>' . sprintf( esc_html__( 'Use the "%s" button to load your aspects from diaspora*.', 'wp_to_diaspora' ), esc_html__( 'Refresh Aspects', 'wp_to_diaspora' ) ) . '</em>
            <li><strong>' . esc_html__( 'Services', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'Choose the services your new diaspora* post gets shared to.', 'wp_to_diaspora' ) . '<br>
                <em>' . sprintf( esc_html__( 'Use the "%s" button to fetch the list of your connected services from diaspora*.', 'wp_to_diaspora' ), esc_html__( 'Refresh Services', 'wp_to_diaspora' ) ) . '</em>
          </ul>'
      ),
      // Explain the importance of SSL connections to the pod and the CA certificate bundle.
      'ssl' => array(
        'title'   => esc_html__( 'SSL', 'wp_to_diaspora' ),
        'content' => '<p><strong>' . esc_html__( 'WP to diaspora* makes sure the connection to your pod is secure!', 'wp_to_diaspora' ) . '</strong></p>
          <p>' . esc_html__( 'Most diaspora* pods are secured using SSL (Secure Sockets Layer), which makes your connection encrypted. For this connection to work, your server needs to know that those SSL certificates can be trusted.', 'wp_to_diaspora' ) . '</p>
          <p>' . esc_html__( 'Therefore, if your server does not have an up to date CA certificate bundle, WP to diaspora* may not work for you.', 'wp_to_diaspora' ) . '</p>
          <p>' . esc_html__( 'Lucky for you though, we have you covered if this is the case for you!', 'wp_to_diaspora' ) . '</p>
          <ul>
            <li><strong>' . esc_html__( 'Get in touch with your hosting provider', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'The best option is for you to get in touch with your hosting provider and ask them to update the bundle for you. They should know what you\'re talking about.', 'wp_to_diaspora' ) . '
            <li><strong>' . esc_html__( 'Install the CA bundle yourself', 'wp_to_diaspora' ) . '</strong>: ' .
              sprintf(
                esc_html_x( 'If you maintain your own server, it\'s your job to keep the bundle up to date. You can find a short and simple way on how to do this %shere%s.', 'Placeholders are HTML for a link.', 'wp_to_diaspora' ),
                '<a href="http://serverfault.com/a/394835" target="_blank">', '</a>'
              ) . '
            <li><strong>' . esc_html__( 'Quick "temporary" fix', 'wp_to_diaspora' ) . '</strong>: ' .
              sprintf(
                esc_html_x( 'As a temporary solution, you can download the up to date %sCA certificate bundle%s (Right-click &#8594; Save As...) and place the cacert.pem file at the top level of the WP to diaspora* plugin folder. This is a "last resort" option.', 'Placeholders are HTML for links.', 'wp_to_diaspora' ),
                '<a href="http://curl.haxx.se/ca/cacert.pem" download>', '</a>'
              )
              . '<br><p>' .
              // See if we can do this automatically.
              ( ( ! array_diff( array( 'fopen', 'fwrite', 'fclose', 'file_get_contents', 'file_put_contents' ), get_defined_functions()['internal'] ) )
                ? sprintf(
                    esc_html_x( 'Your server should allow us to %sdo this%s for you :-)', 'Placeholders are HTML for links.', 'wp_to_diaspora' ),
                    '<a href="' . add_query_arg( 'temp_ssl_fix', '' ) . '" class="button">', '</a>'
                  )
                : ''
              )
              . '</p>
          </ul>
          <p class="dashicons-before dashicons-info">' . esc_html__( 'NOTE: If you choose the temporary option, the copy procedure needs to be done every time the plugin is updated because all files get replaced!', 'wp_to_diaspora' ) . '</p>'
      ),
      // Explain the meta box and the differences to the global defaults.
      'meta-box' => array(
        'title'   => esc_html__( 'Meta Box', 'wp_to_diaspora' ),
        'content' => '<p><strong>' . esc_html__( 'The Meta Box is the new "WP to diaspora*" box you see when editing a post.', 'wp_to_diaspora' ) . '</strong></p>
          <p>' . esc_html__( 'When creating or editing a post, you will notice a new meta box called "WP to diaspora*" which has some options. These options are almost the same as the options you can find in the "Defaults" tab on the settings page. These options are post-specific though, meaning they override the global defaults for the post itself. You will see that the default values are filled in automatically, allowing you to change individual ones as you please.', 'wp_to_diaspora' ) . '</p>
          <p>' . esc_html__( 'There are a few important differences to the settings page:', 'wp_to_diaspora' ) . '</p>
          <ul>
            <li><strong>' . esc_html__( 'Already posted to diaspora*', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'If the post has already been posted to diaspora* a link to the diaspora* post will appear at the top.', 'wp_to_diaspora' ) . '
            <li><strong>' . esc_html__( 'Custom tags', 'wp_to_diaspora' ) . '</strong>: ' .
              esc_html__( 'A list of tags that gets added to this post. Note that they are seperate from the WordPress post tags!', 'wp_to_diaspora' ) . '
          </ul>
          <p class="dashicons-before dashicons-info">' . esc_html__( 'If you don\'t see the meta box, make sure the post type you\'re on has been added to the "Post types" list on the settings page. Also make sure it has been selected from the "Screen Options" at the top of the screen.', 'wp_to_diaspora' ) . '</p>'
      ),
      // Show different ways to contribute to the plugin.
      'contributing' => array(
        'title'   => esc_html__( 'Contributing', 'wp_to_diaspora' ),
        'content' => '<p><strong>' . esc_html__( 'So you feel like contributing to the WP to diaspora* plugin? Great!', 'wp_to_diaspora' ) . '</strong></p>
          <p>' . esc_html__( 'There are many different ways that you can help out with this plugin:', 'wp_to_diaspora' ) . '</p>
          <ul>
            <li><a href="' . WP2D_EXT_GH_ISSUES_NEW . '" target="_blank">' . esc_html__( 'Report a bug', 'wp_to_diaspora' )           . '</a>
            <li><a href="' . WP2D_EXT_GH_ISSUES_NEW . '" target="_blank">' . esc_html__( 'Suggest a new feature', 'wp_to_diaspora' )  . '</a>
            <li><a href="' . WP2D_EXT_I18N          . '" target="_blank">' . esc_html__( 'Help with translations', 'wp_to_diaspora' ) . '</a>
            <li><a href="' . WP2D_EXT_DONATE        . '" target="_blank">' . esc_html__( 'Make a donation', 'wp_to_diaspora' )        . '</a>
          </ul>'
      )
    );
  }

  /**
   * Create / Get the instance of this class.
   *
   * @return WP2D_Contextual_Help Instance of this class.
   */
  public static function get_instance() {
    if ( ! isset( self::$_instance ) ) {
      self::$_instance = new self();
    }
    return self::$_instance;
  }

  /**
   * Set up the contextual help menu.
   */
  public static function setup() {
    // Do we display the help tabs?
    $post_type = get_current_screen()->post_type;
    $enabled_post_types = WP2D_Options::get_instance()->get_option( 'enabled_post_types' );
    if ( '' !== $post_type && ! in_array( $post_type, $enabled_post_types ) ) {
      return;
    }

    // Get the unique instance.
    $instance = self::get_instance();

    // If the instance is already set up, just return it.
    if ( self::$_is_set_up ) {
      return $instance;
    }

    // If we don't have a post type, we're on the main settings page.
    if ( '' === $post_type ) {
      // Set the sidebar in the contextual help.
      $instance->_set_sidebar();

      // Add the main settings tabs and their content.
      $instance->_add_settings_tabs();
    } else {
      // Add the post type specific tabs and their content.
      $instance->_add_post_type_tabs();
    }

    // The instance has been set up.
    self::$_is_set_up = true;

    return $instance;
  }

  /**
   * Set the sidebar in the contextual help.
   */
  private function _set_sidebar() {
    get_current_screen()->set_help_sidebar( '<p><strong>' . esc_html__( 'WP to diaspora*', 'wp_to_diaspora' ) . '</strong></p>
      <ul>
        <li><a href="' . WP2D_EXT_GH     . '" target="_blank">GitHub</a>
        <li><a href="' . WP2D_EXT_WPORG  . '" target="_blank">WordPress.org</a>
        <li><a href="' . WP2D_EXT_I18N   . '" target="_blank">' . esc_html__( 'Help with translations', 'wp_to_diaspora' ) . '</a>
        <li><a href="' . WP2D_EXT_DONATE . '" target="_blank">' . esc_html__( 'Make a donation', 'wp_to_diaspora' )        . '</a>
      </ul>'
    );
  }

  /**
   * Render the output of the selected tab.
   */
  public function render_tab( $screen, $tab ) {
    echo $tab['callback'][0]->_settings_tabs[ $tab['id'] ]['content'];
  }

  /**
   * Add help tabs to the contextual help on the settings page.
   */
  private function _add_settings_tabs() {
    foreach ( $this->_settings_tabs as $id => $data ) {
      get_current_screen()->add_help_tab( array(
        'id'       => $id,
        'title'    => $data['title'],
        // Use the content only if you want to add something
        // static on every help tab. Example: Another title inside the tab
        'content'  => '',
        'callback' => array( $this, 'render_tab' )
      ) );
    }
  }

  /**
   * Add help tabs to the contextual help on the post pages.
   */
  private function _add_post_type_tabs() {
    get_current_screen()->add_help_tab( array(
      'id'       => 'wp-to-diaspora',
      'title'    => esc_html__( 'WP to diaspora*', 'wp_to_diaspora' ),
      'content'  => '<p>' . sprintf( esc_html__( 'For detailed information, refer to the contextual help on the %sWP to diaspora*%s settings page.', 'Placeholders represent the link.', 'wp_to_diaspora' ),
                              '<a href="options-general.php?page=wp_to_diaspora" target="_blank">', '</a>' ) . '</p>'
    ) );
  }

}
