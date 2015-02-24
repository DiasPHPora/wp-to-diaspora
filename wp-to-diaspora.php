<?php
/**
 * Plugin Name: WP to Diaspora*
 * Description: Automatically shares WordPress posts on Diaspora*
 * Version: 1.2.7
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

define( 'WP_TO_DIASPORA_VERSION', '1.2.7' );

// Load necessary classes.
if ( ! class_exists( 'Diaspora_API' ) )     require_once dirname( __FILE__ ) . '/lib/class-diaspora-api.php';
if ( ! class_exists( 'HTML_To_Markdown' ) ) require_once dirname( __FILE__ ) . '/lib/class-html-to-markdown.php';

/**
 * Initialise upgrade sequence.
 */
function wp_to_diaspora_upgrade() {
  // Define the default options, for new installs.
  $defaults = array(
    'pod_list'           => array(),
    'aspects_list'       => array(),
    'post_to_diaspora'   => true,
    'enabled_post_types' => array( 'post' ),
    'fullentrylink'      => true,
    'display'            => 'full',
    'version'            => WP_TO_DIASPORA_VERSION
  );

  // Get the current options, or assign defaults.
  $options = get_option( 'wp_to_diaspora_settings', $defaults );

  // If the versions differ, this is probably an update. Need to save updated options.
  if ( WP_TO_DIASPORA_VERSION != $options['version'] ) {

    // Password is stored encrypted since version 1.2.7.
    // When upgrading to it, the plain text password is encrypted and saved again.
    if ( version_compare( $options['version'], '1.2.7', '<' ) ) {
      $options['password'] = wp_to_diaspora_encrypt( $options['password'] );
    }

    // Update version.
    $options['version'] = WP_TO_DIASPORA_VERSION;
  }

  // Add or update options.
  update_option( 'wp_to_diaspora_settings', $options );
}
add_action( 'admin_init', 'wp_to_diaspora_upgrade' );

/**
 * Post to Diaspora when saving a post.
 *
 * @param integer $post_id ID of the post being saved.
 * @param WP_Post $post    Post object being saved.
 */
function wp_to_diaspora_post( $post_id, $post ) {
  // Get the post's meta data.
  $wp_to_diaspora_meta = get_post_meta( $post_id, '_wp_to_diaspora', true );
  $post_to_diaspora = ! empty( $wp_to_diaspora_meta['post_to_diaspora'] );

  // Make sure we're posting to Diaspora* and the post isn't password protected.
  // TODO: Maybe somebody wants to share a password protected post to a closed aspect.
  if ( $post_to_diaspora && 'publish' === $post->post_status && '' === $post->post_password ) {

    // Get the rest of the meta data.
    $fullentrylink = ! empty( $wp_to_diaspora_meta['fullentrylink'] );
    $display       = $wp_to_diaspora_meta['display'];
    $tags_to_post  = $wp_to_diaspora_meta['tags_to_post'];
    $custom_tags   = $wp_to_diaspora_meta['custom_tags'];
    $aspects       = $wp_to_diaspora_meta['aspects'];

    $options = get_option( 'wp_to_diaspora_settings' );
    $status_message = sprintf( '<p><b><a href="%1$s" title="permalink to %2$s">%2$s</a></b></p>',
      get_permalink( $post_id ),
      $post->post_title
    );

    // Post the full post text or just the excerpt?
    if ( 'full' === $display ) {
      // Disable all filters and then enable only defaults. This prevents additional filters from being posted to Diaspora*.
      remove_all_filters( 'the_content' );
      foreach ( array( 'wptexturize', 'convert_smilies', 'convert_chars', 'wpautop', 'shortcode_unautop', 'prepend_attachment', 'wp_to_diaspora_embed_remove' ) as $filter ) {
        add_filter( 'the_content', $filter );
      }
      // Extract URLs from [embed] shortcodes.
      add_filter( 'embed_oembed_html', 'wp_to_diaspora_embed_url' , 10, 4 );

      $status_message .= apply_filters( 'the_content', $post->post_content );
    } else {
      $excerpt = ( '' != $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 42, '[...]' );
      $status_message .= '<p>' . $excerpt . '</p>';
    }

    // Add any Diaspora* tags?
    if ( false === strpos( $tags_to_post, 'n' ) ) {
      // The Diaspora* tags to add to the post.
      $diaspora_tags = array();
      // Add global tags?
      if ( false !== strpos( $tags_to_post, 'g' ) ) {
        $diaspora_tags += array_flip( explode( ',', $options['global_tags'] ) );
      }
      // Add custom tags?
      if ( false !== strpos( $tags_to_post, 'c' ) ) {
        $diaspora_tags += array_flip( explode( ',', $custom_tags ) );
      }
      // Add post tags?
      if ( false !== strpos( $tags_to_post, 'p' ) ) {
        // Clean up the post tags.
        $diaspora_tags += array_flip( wp_get_post_tags( $post_id, array( 'fields' => 'slugs' ) ) );
      }

      // Get all the tags and list them all nicely in a row.
      $diaspora_tags_clean = array();
      foreach ( array_map( 'wp_to_diaspora_clean_tag', array_keys( array_filter( $diaspora_tags ) ) ) as $tag ) {
        $diaspora_tags_clean[] = '#' . $tag;
      }

      // Add all the found tags.
      $status_message .= implode( ' ', $diaspora_tags_clean ) . '<br />';
    }

    // Add the original entry link to the post?
    if ( $fullentrylink ) {
      $status_message .= sprintf( '%1$s [%2$s](%2$s "%3$s")',
        __( 'Originally posted at:', 'wp_to_diaspora' ),
        get_permalink( $post_id ),
        $post->post_title
      );
    }

    $status_markdown = new HTML_To_Markdown( $status_message );
    $status_message  = $status_markdown->output();

    // Set up the connection to Diaspora.
    $conn = new Diaspora_API( $options['pod'] );
    if ( $conn->init()
      && $conn->login( $options['user'], wp_to_diaspora_decrypt( $options['password'] ) )
      && $response = $conn->post( $status_message, $aspects ) ) {

      // Save certain Diaspora* post data as meta data for future reference.

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
        'aspects'    => $aspects,
        'nsfw'       => $response->nsfw,
        'post_url'   => $conn->get_pod_url( '/posts/' . $response->guid )
      );
      update_post_meta( $post_id, '_wp_to_diaspora_post_history', $diaspora_post_history );

      // If there is still a previous post error around, remove it.
      delete_post_meta( $post_id, '_wp_to_diaspora_post_error' );
    } else {
      // Save the post error as post meta data, so we can display it to the user.
      update_post_meta( $post_id, '_wp_to_diaspora_post_error', $conn->last_error );
    }
    // Remember to clean up after ourselves.
    $conn->cleanup();
  }
}
add_action( 'save_post', 'wp_to_diaspora_post', 20, 2 );

// Return URL from [embed] shortcode instead of generated iframe.
function wp_to_diaspora_embed_url( $html, $url, $attr, $post_ID ) {
  return $url;
}

// Removes '[embed]' and '[/embed]' left by wp_to_diaspora_embed_url.
// TODO: It would be great to fix it using only one filter.
//       It's happening because embed filter is being removed by remove_all_filters('the_content') on wp_to_diaspora_post().
function wp_to_diaspora_embed_remove( $content ) {
  return str_replace( array( '[embed]', '[/embed]' ), array( '<p>', '</p>' ), $content );
}

/**
 * Set up i18n.
 */
function wp_to_diaspora_plugins_loaded() {
  load_plugin_textdomain( 'wp_to_diaspora', false, 'wp-to-diaspora/languages' );
}
add_action( 'plugins_loaded', 'wp_to_diaspora_plugins_loaded' );

/**
 * Load scripts and styles for Settings and Post pages of allowed post types.
 */
function wp_to_diaspora_admin_loadscripts() {
  $options = get_option( 'wp_to_diaspora_settings' );
  $valid_post_types = ( isset( $options['enabled_post_types'] ) ) ? $options['enabled_post_types'] : array();

  // Get the screen to find out where we are.
  $screen = get_current_screen();

  // Only load the styles and scripts on the settings page and the allowed post types.
  if ( 'settings_page_wp_to_diaspora' === $screen->id || ( in_array( $screen->post_type, $valid_post_types ) && 'post' === $screen->base ) ) {
    wp_enqueue_style(  'wp-to-diaspora-admin', plugins_url( '/css/wp-to-diaspora.css', __FILE__ ) );
    wp_enqueue_script( 'wp-to-diaspora-admin', plugins_url( '/js/wp-to-diaspora.js', __FILE__ ), array( 'jquery' ), false, true );
  }
}
add_action( 'admin_enqueue_scripts', 'wp_to_diaspora_admin_loadscripts' );

/**
 * Add the "Settings" link to the plugins page.
 *
 * @param  array $links Links to display for plugin on plugins page.
 * @return array        Links to display for plugin on plugins page.
 */
function wp_to_diaspora_settings_link ( $links ) {
  $links[] = '<a href="' . admin_url( 'options-general.php?page=wp_to_diaspora' ) . '">' . __( 'Settings' ) . '</a>';
  return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wp_to_diaspora_settings_link' );


/* HELPERS */

/**
 * Clean up the passed tag. Keep only alphanumeric, hyphen and underscore characters.
 *
 * @todo   What about eastern characters? (chinese, indian, etc.)
 *
 * @param  string $tag Tag to be cleaned.
 * @return string      The clean tag.
 */
function wp_to_diaspora_clean_tag( $tag ) {
  return preg_replace( '/[^\w $\-]/u', '', str_replace( ' ', '-', trim( $tag ) ) );
}

/**
 * Encrypt the passed string with the passed key.
 *
 * @param  string $input_string String to be encrypted.
 * @param  string $key          The key used for the encryption.
 * @return string               The encrypted string.
 */
function wp_to_diaspora_encrypt( $input_string, $key = AUTH_KEY ) {
  global $wpdb;
  return base64_encode( $wpdb->get_var( $wpdb->prepare( "SELECT AES_ENCRYPT(%s,%s)", $input_string, $key ) ) );
}

/**
 * Decrypt the passed string with the passed key.
 *
 * @param  string $input_string String to be decrypted.
 * @param  string $key          The key used for the decryption.
 * @return string               The decrypted string.
 */
function wp_to_diaspora_decrypt( $encrypted_string, $key = AUTH_KEY ) {
  global $wpdb;
  return $wpdb->get_var( $wpdb->prepare( "SELECT AES_DECRYPT(%s,%s)", base64_decode( $encrypted_string ), $key ) );
}


/* OPTIONS PAGE */

/**
 * Add menu link for the options page.
 */
function wp_to_diaspora_add_admin_menu() {
  add_options_page( 'WP to Diaspora*', 'WP to Diaspora*', 'manage_options', 'wp_to_diaspora', 'wp_to_diaspora_options_page' );
}
add_action( 'admin_menu', 'wp_to_diaspora_add_admin_menu' );

/**
 * Initialise the settings sections and fields.
 */
function wp_to_diaspora_settings_init() {
  register_setting( 'wp_to_diaspora_settings', 'wp_to_diaspora_settings', 'wp_to_diaspora_settings_validate' );

  // Add a "Setup" section that contains the Pod domain, Username and Password.
  add_settings_section(
    'wp_to_diaspora_setup_section',
    __( 'Diaspora* Setup', 'wp_to_diaspora' ),
    'wp_to_diaspora_setup_section_cb',
    'wp_to_diaspora_settings'
  );

  // Add a "Defaults" section that contains all posting settings to be used by default.
  add_settings_section(
    'wp_to_diaspora_defaults_section',
    __( 'Posting Defaults', 'wp_to_diaspora' ),
    'wp_to_diaspora_defaults_section_cb',
    'wp_to_diaspora_settings'
  );
}
add_action( 'admin_init', 'wp_to_diaspora_settings_init' );

/**
 * Callback for the "Setup" section.
 */
function wp_to_diaspora_setup_section_cb() {
  _e( 'Set up the connection to your Diaspora* account.', 'wp_to_diaspora' );

  // Pod entry field.
  add_settings_field(
    'pod',
    __( 'Diaspora* Pod', 'wp_to_diaspora' ),
    'wp_to_diaspora_pod_render',
    'wp_to_diaspora_settings',
    'wp_to_diaspora_setup_section'
  );

  // Username entry field.
  add_settings_field(
    'user',
    __( 'Username' ),
    'wp_to_diaspora_user_render',
    'wp_to_diaspora_settings',
    'wp_to_diaspora_setup_section'
  );

  // Password entry field.
  add_settings_field(
    'password',
    __( 'Password' ),
    'wp_to_diaspora_password_render',
    'wp_to_diaspora_settings',
    'wp_to_diaspora_setup_section'
  );
}

/**
 * Render the "Pod" field.
 */
function wp_to_diaspora_pod_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  ?>
  https://<input type="text" name="wp_to_diaspora_settings[pod]" value="<?php echo $options['pod']; ?>" placeholder="e.g. joindiaspora.com" autocomplete="on" list="pod-list" required> <a id="refresh-pod-list" class="button"><?php _e( 'Refresh pod list', 'wp_to_diaspora' ); ?></a><span class="spinner" style="display: none;"></span>
  <datalist id="pod-list">
  <?php foreach ( $options['pod_list'] as $pod ) : ?>
    <option data-secure="<?php echo $pod['secure']; ?>" value="<?php echo $pod['domain']; ?>"></option>
  <?php endforeach; ?>
  </datalist>
  <?php
}

/**
 * Render the "Username" field.
 */
function wp_to_diaspora_user_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  ?>
  <input type="text" name="wp_to_diaspora_settings[user]" value="<?php echo $options['user']; ?>" placeholder="<?php _e( 'Username' ); ?>" required>
  <?php
}

/**
 * Render the "Password" field.
 */
function wp_to_diaspora_password_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  // Special case if we already have a password.
  $has_password = ( isset( $options['password'] ) && '' !== $options['password'] );
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
function wp_to_diaspora_defaults_section_cb() {
  _e( 'Define the default posting behaviour for all posts here. These settings can be modified for each post individually, by changing the values in the "WP to Diaspora*" meta box, which gets displayed in your post edit screen.', 'wp_to_diaspora' );

  // Post types field.
  add_settings_field(
    'enabled_post_types',
    __( 'Post types', 'wp_to_diaspora' ),
    'wp_to_diaspora_post_types_render',
    'wp_to_diaspora_settings',
    'wp_to_diaspora_defaults_section'
  );

   // Post to Diaspora* checkbox.
  add_settings_field(
    'post_to_diaspora',
    __( 'Post to Diaspora*', 'wp_to_diaspora' ),
    'wp_to_diaspora_post_to_diaspora_render',
    'wp_to_diaspora_settings',
    'wp_to_diaspora_defaults_section'
  );

  // Full entry link checkbox.
  add_settings_field(
    'fullentrylink',
    __( 'Show "Posted at" link?', 'wp_to_diaspora' ),
    'wp_to_diaspora_fullentrylink_render',
    'wp_to_diaspora_settings',
    'wp_to_diaspora_defaults_section'
  );

  // Full text or excerpt radio buttons.
  add_settings_field(
    'display',
    __( 'Display', 'wp_to_diaspora' ),
    'wp_to_diaspora_display_render',
    'wp_to_diaspora_settings',
    'wp_to_diaspora_defaults_section'
  );

  // Tags to post dropdown.
  add_settings_field(
    'tags_to_post',
    __( 'Tags to post', 'wp_to_diaspora' ),
    'wp_to_diaspora_tags_to_post_render',
    'wp_to_diaspora_settings',
    'wp_to_diaspora_defaults_section'
  );

  // Global tags field.
  add_settings_field(
    'global_tags',
    __( 'Global tags', 'wp_to_diaspora' ),
    'wp_to_diaspora_global_tags_render',
    'wp_to_diaspora_settings',
    'wp_to_diaspora_defaults_section'
  );

  // Aspects checkboxes.
  add_settings_field(
    'aspects',
    __( 'Aspects', 'wp_to_diaspora' ),
    'wp_to_diaspora_aspects_render',
    'wp_to_diaspora_settings',
    'wp_to_diaspora_defaults_section'
  );
}

/**
 * Render the "Post types" checkboxes.
 */
function wp_to_diaspora_post_types_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
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
      checked( in_array( $slug, $options['enabled_post_types'] ), true, false ),
      sprintf( __( 'Enable WP to Diaspora* on %s', 'wp_to_diaspora' ), $name )
    );
  }

  echo '<div class="post-types-container"><ul class="tabs">' . $tabs . '</ul>' . $contents . '</div>';
}

/**
* Render the "Post to Diaspora*" checkbox.
*/
function wp_to_diaspora_post_to_diaspora_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  ?>
  <label><input type="checkbox" id="post_to_diaspora" name="wp_to_diaspora_settings[post_to_diaspora]" value="1" <?php checked( $options['post_to_diaspora'] ); ?>><?php _e( 'Yes' ); ?></label>
  <?php
}

/**
 * Render the "Show 'Posted at' link" checkbox.
 */
function wp_to_diaspora_fullentrylink_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  ?>
  <label><input type="checkbox" id="fullentrylink" name="wp_to_diaspora_settings[fullentrylink]" value="1" <?php checked( $options['fullentrylink'] ); ?>><?php _e( 'Yes' ); ?></label>
  <p class="description"><?php _e( 'Include a link back to your original post.', 'wp_to_diaspora' ); ?></p>
  <?php
}

/**
 * Render the "Display" radio buttons.
 */
function wp_to_diaspora_display_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  ?>
  <label><input type="radio" name="wp_to_diaspora_settings[display]" value="full" <?php checked( $options['display'], 'full' ); ?>><?php _e( 'Full Post', 'wp_to_diaspora' ); ?></label><br />
  <label><input type="radio" name="wp_to_diaspora_settings[display]" value="excerpt" <?php checked( $options['display'], 'excerpt' ); ?>><?php _e( 'Excerpt' ); ?></label>
  <?php
}

/**
 * Render the "Tags to post" field.
 */
function wp_to_diaspora_tags_to_post_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  $tags_to_post = isset( $options['tags_to_post'] ) ? $options['tags_to_post'] : 'gc';
  ?>
  <select id="tags-to-post" name="wp_to_diaspora_settings[tags_to_post]">
    <option value="gcp" <?php selected( $tags_to_post, 'gcp' ); ?>><?php _e( 'All tags (global, custom and post tags)', 'wp_to_diaspora' ); ?></option>
    <option value="gc" <?php  selected( $tags_to_post, 'gc' );  ?>><?php _e( 'Global and custom tags', 'wp_to_diaspora' ); ?></option>
    <option value="gp" <?php  selected( $tags_to_post, 'gp' );  ?>><?php _e( 'Global and post tags', 'wp_to_diaspora' ); ?></option>
    <option value="c" <?php   selected( $tags_to_post, 'c' );   ?>><?php _e( 'Only custom tags', 'wp_to_diaspora' ); ?></option>
    <option value="p" <?php   selected( $tags_to_post, 'p' );   ?>><?php _e( 'Only post tags', 'wp_to_diaspora' ); ?></option>
    <option value="n" <?php   selected( $tags_to_post, 'n' );   ?>><?php _e( 'No tags', 'wp_to_diaspora' ); ?></option>
  </select>
  <p class="description"><?php _e( 'Choose which tags should be posted to Diaspora*.', 'wp_to_diaspora' ); ?></p>
  <?php
}

/**
 * Render the "Global tags" field.
 */
function wp_to_diaspora_global_tags_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  ?>
  <input type="text" name="wp_to_diaspora_settings[global_tags]" value="<?php echo $options['global_tags']; ?>" placeholder="<?php _e( 'Global tags', 'wp_to_diaspora' ); ?>" class="regular-text">
  <p class="description"><?php _e( 'Custom tags to add to all posts being posted to Diaspora*.', 'wp_to_diaspora' ); ?> <?php _e( 'Separate tags with commas' ); ?></p>
  <?php
}

/**
 * Render the "Aspects" checkboxes.
 */
function wp_to_diaspora_aspects_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  $aspects_selected = ( ! empty( $options['aspects'] ) && is_array( $options['aspects'] ) ) ? $options['aspects'] : array();
  ?>
  <div id="aspects-container" data-aspects-selected="<?php echo implode( ',', $aspects_selected ); ?>">
    <?php
    if ( $aspects_list = $options['aspects_list'] ) {
      foreach ( $aspects_list as $aspect_id => $aspect_name ) {
        ?>
        <label><input type="checkbox" name="wp_to_diaspora_settings[aspects][]" value="<?php echo $aspect_id; ?>" <?php checked( in_array( $aspect_id, $aspects_selected ) ); ?>><?php echo $aspect_name; ?></label>
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
  <p class="description"><?php _e( 'Choose which aspects to share to.', 'wp_to_diaspora' ); ?> <a id="refresh-aspects-list" class="button"><?php _e( 'Refresh Aspects', 'wp_to_diaspora' ); ?></a><span class="spinner" style="display: none;"></span></p>
  <?php
}

/**
 * Output the options page.
 */
function wp_to_diaspora_options_page() {
  ?>
  <div class="wrap">
    <h2>WP to Diaspora*</h2>

    <?php
      $options = get_option( 'wp_to_diaspora_settings' );

      // Set up the connection to Diaspora.
      $conn = new Diaspora_API( $options['pod'] );
      if ( $conn->init() && $conn->login( $options['user'], wp_to_diaspora_decrypt( $options['password'] ) ) ) {
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
      // Remember to clean up after ourselves.
      $conn->cleanup();

      // Output success or error message.
      settings_errors( 'wp_to_diaspora_settings' );
    ?>

    <form action="options.php" method="post">
    <?php
      settings_fields( 'wp_to_diaspora_settings' );
      do_settings_sections( 'wp_to_diaspora_settings' );
      submit_button();
    ?>
    </form>
  </div>
  <?php
}

/**
 * Validate all settings before saving.
 *
 * @param  array $new_values Settings being saved that need validation.
 * @return array             The validated settings.
 */
function wp_to_diaspora_settings_validate( $new_values ) {
  $options = get_option( 'wp_to_diaspora_settings' );

  // Validate all settings before saving to the database.
  $new_values['pod']      = sanitize_text_field( $new_values['pod'] );
  $new_values['user']     = sanitize_text_field( $new_values['user'] );
  $new_values['password'] = sanitize_text_field( $new_values['password'] );

  // If we are saving the settings via the form, the pod and aspects list won't be set, so get those from the options.
  if ( ! isset( $new_values['pod_list'] ) ) {
    $new_values['pod_list'] = $options['pod_list'];
  }
  if ( ! isset( $new_values['aspects_list'] ) ) {
    $new_values['aspects_list'] = $options['aspects_list'];
  }

  // If password is blank, it hasn't been changed.
  // If new password is equal to the encrypted password already saved, it was just passed again. It happens everytime update_option('wp_to_diaspora_settings') is called.
  if ( '' === $new_values['password'] || $options['password'] === $new_values['password'] ) {
    $new_values['password'] = $options['password'];
  } else {
    $new_values['password'] = wp_to_diaspora_encrypt( $new_values['password'] );
  }

  if ( ! isset( $new_values['enabled_post_types'] ) ) {
    $new_values['enabled_post_types'] = array();
  }

  $new_values['post_to_diaspora'] = isset( $new_values['post_to_diaspora'] );
  $new_values['fullentrylink']    = isset( $new_values['fullentrylink'] );

  if ( ! in_array( $new_values['display'], array( 'full', 'excerpt' ) ) ) {
    $new_values['display'] = $options['display'];
  }

  if ( ! in_array( $new_values['tags_to_post'], array( 'gcp', 'gc', 'gp', 'c', 'p', 'n' ) ) ) {
    $new_values['tags_to_post'] = $options['tags_to_post'];
  }

  $global_tags_clean = array();
  foreach ( array_filter( explode( ',', $new_values['global_tags'] ) ) as $tag ) {
    $global_tags_clean[] = wp_to_diaspora_clean_tag( $tag );
  }
  $new_values['global_tags'] = implode( ', ', array_unique( $global_tags_clean ) );

  // Clean up the list of aspects. If the list is empty, only use the 'Public' aspect.
  if ( empty( $new_values['aspects'] ) ) {
    $new_values['aspects'] = array( 'public' );
  }
  $new_values['aspects'] = array_map( 'sanitize_text_field', $new_values['aspects'] );

  // TODO: What for? The version will never be set, as $new_values['version'] is always null.
  if ( ! isset( $new_values['version'] ) ) {
    $new_values['version'] = $options['version'];
  }

  return $new_values;
}


/* META BOX */

/**
 * Adds a meta box to the main column on the enabled Post Types' edit screens.
 */
function wp_to_diaspora_add_meta_box() {
  $options = get_option( 'wp_to_diaspora_settings' );

  foreach ( $options['enabled_post_types'] as $post_type ) {
    add_meta_box(
      'wp_to_diaspora_meta_box',
      'WP to Diaspora*',
      'wp_to_diaspora_meta_box_callback',
      $post_type,
      'side',
      'high'
    );
  }
}
add_action( 'add_meta_boxes', 'wp_to_diaspora_add_meta_box' );

/**
 * Prints the meta box content.
 *
 * @param WP_Post $post The object for the current post.
 */
function wp_to_diaspora_meta_box_callback( $post ) {
  // Add an nonce field so we can check for it later.
  wp_nonce_field( 'wp_to_diaspora_meta_box', 'wp_to_diaspora_meta_box_nonce' );

  // Get the default values to use, but give priority to the meta data already set.
  $defaults  = get_option( 'wp_to_diaspora_settings' );
  $post_meta = get_post_meta( $post->ID, '_wp_to_diaspora', true );

  // Go through all default values and use the default if no meta data is set yet.
  foreach ( $defaults as $key => $default ) {
    $post_meta[ $key ] = ( isset( $post_meta[ $key ] ) ) ? $post_meta[ $key ] : $default;
  }

  // If this post is already published, don't post again to Diaspora*.
  $post_to_diaspora = ( 'publish' === get_post_status( $post->ID ) ) ? false : $post_meta['post_to_diaspora'];
  $display          = $post_meta['display'];
  $tags_to_post     = $post_meta['tags_to_post'];
  $custom_tags      = $post_meta['custom_tags'];
  $aspects_selected = ( ! empty( $post_meta['aspects'] ) && is_array( $post_meta['aspects'] ) ) ? $post_meta['aspects'] : array();

  // Have we already posted on Diaspora*?
  if ( ( $diaspora_post_history = get_post_meta( $post->ID, '_wp_to_diaspora_post_history', true ) ) && is_array( $diaspora_post_history ) ) {
    $latest_post = end( $diaspora_post_history );
    ?>
    <p><a href="<?php echo $latest_post['post_url']; ?>" target="_blank"><?php _e( 'Already posted to Diaspora*', 'wp_to_diaspora' ); ?></a></p>
    <?php
  }
  ?>
  <p><label><input type="checkbox" id="post_to_diaspora" name="wp_to_diaspora_post_to_diaspora" value="1" <?php checked( $post_to_diaspora ); ?>><?php _e( 'Post to Diaspora*', 'wp_to_diaspora' ); ?></label></p>
  <p><label title="<?php _e( 'Include a link back to your original post.', 'wp_to_diaspora' ); ?>"><input type="checkbox" id="fullentrylink" name="wp_to_diaspora_fullentrylink" value="1" <?php checked( $post_meta['fullentrylink'] ); ?>><?php _e( 'Show "Posted at" link?', 'wp_to_diaspora' ); ?></label></p>

  <p>
    <label><input type="radio" name="wp_to_diaspora_display" value="full" <?php checked( $display, 'full' ); ?>><?php _e( 'Full Post', 'wp_to_diaspora' ); ?></label>&nbsp;
    <label><input type="radio" name="wp_to_diaspora_display" value="excerpt" <?php checked( $display, 'excerpt' ); ?>><?php _e( 'Excerpt' ); ?></label>
  </p>

  <p>
    <label title="<?php _e( 'Choose which tags should be posted to Diaspora*.', 'wp_to_diaspora' ); ?>">
      <?php _e( 'Tags to post', 'wp_to_diaspora' ); ?>
      <select id="tags-to-post" name="wp_to_diaspora_tags_to_post">
        <option value="gcp" <?php selected( $tags_to_post, 'gcp' ); ?>><?php _e( 'All tags (global, custom and post tags)', 'wp_to_diaspora' ); ?></option>
        <option value="gc" <?php  selected( $tags_to_post, 'gc' );  ?>><?php _e( 'Global and custom tags', 'wp_to_diaspora' ); ?></option>
        <option value="gp" <?php  selected( $tags_to_post, 'gp' );  ?>><?php _e( 'Global and post tags', 'wp_to_diaspora' ); ?></option>
        <option value="c" <?php   selected( $tags_to_post, 'c' );   ?>><?php _e( 'Only custom tags', 'wp_to_diaspora' ); ?></option>
        <option value="p" <?php   selected( $tags_to_post, 'p' );   ?>><?php _e( 'Only post tags', 'wp_to_diaspora' ); ?></option>
        <option value="n" <?php   selected( $tags_to_post, 'n' );   ?>><?php _e( 'No tags', 'wp_to_diaspora' ); ?></option>
      </select>
    </label>
  </p>

  <p>
    <label title="<?php _e( 'Custom tags to add to this post when it\'s posted to Diaspora*.', 'wp_to_diaspora' ); ?>">
      <?php _e( 'Custom tags', 'wp_to_diaspora' ); ?>
      <input type="text" name="wp_to_diaspora_custom_tags" value="<?php echo $custom_tags; ?>" class="widefat">
    </label>
    <p class="howto"><?php _e( 'Separate tags with commas' ); ?></p>
  </p>

  <p>
    <?php _e( 'Choose which aspects to share to.', 'wp_to_diaspora' ); ?>
    <div id="aspects-container" data-aspects-selected="<?php echo implode( ',', $aspects_selected ); ?>">
      <?php
      if ( $aspects_list = $post_meta['aspects_list'] ) {
        foreach ( $aspects_list as $aspect_id => $aspect_name ) {
          ?>
          <label><input type="checkbox" name="wp_to_diaspora_settings[aspects][]" value="<?php echo $aspect_id; ?>" <?php checked( in_array( $aspect_id, $aspects_selected ) ); ?>><?php echo $aspect_name; ?></label>
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
    <p class="howto"><a id="refresh-aspects-list" class="button"><?php _e( 'Refresh Aspects', 'wp_to_diaspora' ); ?></a><span class="spinner" style="display: none;"></span></p>
  </p>

  <?php
}

/**
 * When the post is saved, save our meta data.
 *
 * @param int $post_id The ID of the post being saved.
 */
function wp_to_diaspora_save_meta_box_data( $post_id ) {
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

  // Meta data to save.
  $wp_to_diaspora_meta = array(
    'post_to_diaspora' => isset( $_POST['wp_to_diaspora_post_to_diaspora'] ),
    'fullentrylink'    => isset( $_POST['wp_to_diaspora_fullentrylink'] ),
    'display'          => sanitize_text_field( $_POST['wp_to_diaspora_display'] )
  );

  if ( in_array( $_POST['wp_to_diaspora_tags_to_post'], array( 'gcp', 'gc', 'gp', 'c', 'p', 'n' ) ) ) {
    $wp_to_diaspora_meta['tags_to_post'] = $_POST['wp_to_diaspora_tags_to_post'];
  }

  $custom_tags_clean = array();
  foreach ( array_filter ( explode( ',', $_POST['wp_to_diaspora_custom_tags'] ) ) as $tag ) {
    $custom_tags_clean[] = wp_to_diaspora_clean_tag( $tag );
  }
  $wp_to_diaspora_meta['custom_tags'] = implode( ', ', array_unique( $custom_tags_clean ) );

  // Clean up the list of aspects. If the list is empty, only use the 'Public' aspect.
  if ( ! isset( $_POST['wp_to_diaspora_settings']['aspects'] ) || empty( $_POST['wp_to_diaspora_settings']['aspects'] ) ) {
    $wp_to_diaspora_meta['aspects'] = array( 'public' );
  }
  $wp_to_diaspora_meta['aspects'] = array_map( 'sanitize_text_field', $_POST['wp_to_diaspora_settings']['aspects'] );

  // Update the meta data in the database.
  update_post_meta( $post_id, '_wp_to_diaspora', $wp_to_diaspora_meta );
}
add_action( 'save_post', 'wp_to_diaspora_save_meta_box_data', 10 );


/**
 * Add admin notices when a post gets displayed.
 */
function wp_to_diaspora_admin_notices() {
  global $post, $pagenow;
  if ( ! $post || 'post.php' !== $pagenow ) {
    return;
  }

  if ( $error = get_post_meta( $post->ID, '_wp_to_diaspora_post_error', true ) ) {
    // This notice will only be shown if posting to Diaspora* has failed.
    printf( '<div class="error"><p>%1$s: %2$s <a href="%3$s">%4$s</a></p></div>',
      __( 'Failed to post to Diaspora*', 'wp_to_diaspora' ),
      $error,
      add_query_arg( 'wp_to_diaspora_ignore_post_error', 'yes' ),
      __( 'Ignore', 'wp_to_diaspora' )
    );
  } elseif ( ( $diaspora_post_history = get_post_meta( $post->ID, '_wp_to_diaspora_post_history', true ) ) && is_array( $diaspora_post_history ) ) {
    $latest_post = end( $diaspora_post_history );

    // Only show if this post is showing a message and the post is a fresh share.
    if ( isset( $_GET['message'] ) && $post->post_modified == $latest_post['created_at'] ) {
      printf( '<div class="updated"><p>%1$s <a href="%2$s" target="_blank">%3$s</a></p></div>',
        __( 'Successfully posted to Diaspora*.', 'wp_to_diaspora' ),
        $latest_post['post_url'],
        __( 'View Post' )
      );
    }
  }
}
add_action( 'admin_notices', 'wp_to_diaspora_admin_notices' );

/**
 * Delete the error post meta data if it gets ignored.
 */
function wp_to_diaspora_ignore_post_error() {
  // If "Ignore" link has been clicked, delete the post error meta data.
  if ( isset( $_GET['wp_to_diaspora_ignore_post_error'], $_GET['post'] ) && 'yes' == $_GET['wp_to_diaspora_ignore_post_error'] ) {
    delete_post_meta( $_GET['post'], '_wp_to_diaspora_post_error' );
  }
}
add_action( 'admin_init', 'wp_to_diaspora_ignore_post_error' );


/**
 * Fetch the updated list of pods from podupti.me and save it to the settings.
 *
 * @return array The list of pods.
 */
function wp_to_diaspora_update_pod_list() {
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
      $options = get_option( 'wp_to_diaspora_settings' );
      $options['pod_list'] = $pods;
      update_option( 'wp_to_diaspora_settings', $options );
    }
  }
  return $pods;
}

/**
 * Update the list of pods and return them for use with AJAX.
 */
function wp_to_diaspora_update_pod_list_callback() {
  wp_send_json( wp_to_diaspora_update_pod_list() );
}
add_action( 'wp_ajax_wp_to_diaspora_update_pod_list', 'wp_to_diaspora_update_pod_list_callback' );


/**
 * Fetch the list of aspects and save them to the settings.
 *
 * @return array The list of aspects. (id => name pairs)
 */
function wp_to_diaspora_update_aspects_list() {
  $options = get_option( 'wp_to_diaspora_settings' );
  $aspects = ( isset( $options['aspects_list'] ) ) ? $options['aspects_list'] : array( 'public' => __( 'Public' ) );

  // Set up the connection to Diaspora.
  $conn = new Diaspora_API( $options['pod'] );
  if ( $conn->init()
    && $conn->login( $options['user'], wp_to_diaspora_decrypt( $options['password'] ) )
    && $aspects_raw = $conn->get_aspects() ) {

    // So we have a new list of aspects.

    // Add the 'public' aspect, as it's global and not user specific.
    $aspects = array( 'public' => __( 'Public' ) );

    // Create an array of all the aspects and save them to the settings.
    foreach ( $aspects_raw as $aspect ) {
      $aspects[ $aspect->id ] = $aspect->name;
    }
    $options = get_option( 'wp_to_diaspora_settings' );
    $options['aspects_list'] = $aspects;
    update_option( 'wp_to_diaspora_settings', $options );
  }

  // Remember to clean up after ourselves.
  $conn->cleanup();

  return $aspects;
}

/**
 * Update the list of aspects and return them for use with AJAX.
 */
function wp_to_diaspora_update_aspects_list_callback() {
  wp_send_json( wp_to_diaspora_update_aspects_list() );
}
add_action( 'wp_ajax_wp_to_diaspora_update_aspects_list', 'wp_to_diaspora_update_aspects_list_callback' );
