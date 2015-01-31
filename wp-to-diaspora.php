<?php
/**
 * Plugin Name: WP to Diaspora*
 * Description: Automatically shares WordPress posts on Diaspora*
 * Version: 1.2.5.2
 * Author: Augusto Bennemann (gutobenn), Armando Lüscher (noplanman)
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

define( 'WP_TO_DIASPORA_VERSION', '1.2.5.2' );

// Include necessary classes.
if ( ! class_exists( 'Diasphp' ) )          require_once dirname( __FILE__ ) . '/class-diaspora.php';
if ( ! class_exists( 'HTML_To_Markdown' ) ) require_once dirname( __FILE__ ) . '/HTML_To_Markdown.php';


/**
 * Initialise upgrade sequence.
 */
function wp_to_diaspora_upgrade(){
  // Define the default options.
  $defaults = array(
    'post_to_diaspora' => true,
    'fullentrylink'    => true,
    'display'          => 'full',
    'version'          => WP_TO_DIASPORA_VERSION
  );

  // Get the current options.
  $options = get_option( 'wp_to_diaspora_settings' );

  if ( ! $options ) {
    // No saved options. Probably a fresh install.
    add_option( 'wp_to_diaspora_settings', $defaults );
  } elseif ( WP_TO_DIASPORA_VERSION != $options['version'] ) {
    // Saved options exist, but versions differ. Probably a fresh update. Need to save updated options.
    unset( $options['version'] );
    $options = array_merge( $defaults, $options );
    update_option( 'wp_to_diaspora_settings', $options );
  }
}
add_action( 'admin_init', 'wp_to_diaspora_upgrade' );

/**
 * Post to Diaspora when saving a post.
 *
 * @param  integer $post_id ID of the post being saved.
 */
function wp_to_diaspora_post( $post_id, $post ) {
  // Get the post's meta data.
  $post_to_diaspora = ! empty( get_post_meta( $post_id, '_wp_to_diaspora_post_to_diaspora', true ) );
  $fullentrylink    = ! empty( get_post_meta( $post_id, '_wp_to_diaspora_fullentrylink', true ) );
  $display          = get_post_meta( $post_id, '_wp_to_diaspora_display', true );

  // Make sure we're posting to Diaspora* and the post isn't password protected.
  // TODO: Maybe somebody wants to share a password protected post to a closed aspect.
  if ( $post_to_diaspora && 'publish' === $post->post_status && '' === $post->post_password ) {

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
      // Extract URLs from [embed] shortcodes
      add_filter( 'embed_oembed_html', 'wp_to_diaspora_embed_url' , 10, 4 );

      $status_message .= apply_filters( 'the_content', $post->post_content );
    } else {
      $excerpt = ( '' != $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 42, '[...]' );
      $status_message .= '<p>' . $excerpt . '</p>';
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

    try {
      // Initialise a new connection to post to Diaspora*.
      $conn = new Diasphp( 'https://' . $options['pod'] );
      $conn->login( $options['user'], $options['password'] );

      // NOTE: Leave "via" as a static value, to promote plugin!
      $conn->post( $status_message, 'WP to Diaspora*' );
    } catch ( Exception $e ) {
      printf( '<div class="error">' . __( 'WP to Diaspora*: Sending "%1$s" failed with error: %2$s' ) . '</div>',
        $post->post_title,
        $e->getMessage()
      );
    }
  }
}
add_action( 'save_post', 'wp_to_diaspora_post', 20, 2 );

// Return URL from [embed] shortcode instead of generated iframe
function wp_to_diaspora_embed_url( $html, $url, $attr, $post_ID ) {
  return $url;
}

// Removes '[embed]' and '[/embed]' left by wp_to_diaspora_embed_url
// TODO: it would be great to fix it using only one filter. It's happening because embed filter is being removed by remove_all_filters('the_content') on wp_to_diaspora_post().
function wp_to_diaspora_embed_remove( $content ){
  $content = str_replace( '[embed]', '<p>', $content );
  return str_replace( '[/embed]', '</p>', $content );
}

/**
 * Set up i18n.
 */
function wp_to_diaspora_plugins_loaded() {
  load_plugin_textdomain( 'wp_to_diaspora', false, 'wp-to-diaspora/languages' );
}
add_action( 'plugins_loaded', 'wp_to_diaspora_plugins_loaded' );


/**
 * Add the "Settings" link to the plugins page.
 *
 * @param  array $links Links to display for plugin on plugins page.
 * @return array        Links to display for plugin on plugins page.
 */
function wp_to_diaspora_settings_link ( $links ) {
  $mylinks = array(
    '<a href="' . admin_url( 'options-general.php?page=wp_to_diaspora' ) . '">' . __( 'Settings', 'wp_to_diaspora' ) . '</a>',
  );
  return array_merge( $links, $mylinks );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wp_to_diaspora_settings_link' );


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

  // Add a "Defaults" section that contains the Pod domain, Username and Password.
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
    __( 'Username', 'wp_to_diaspora' ),
    'wp_to_diaspora_user_render',
    'wp_to_diaspora_settings',
    'wp_to_diaspora_setup_section'
  );

  // Password entry field.
  add_settings_field(
    'password',
    __( 'Password', 'wp_to_diaspora' ),
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
  https://<input type="text" name="wp_to_diaspora_settings[pod]" value="<?php echo $options['pod']; ?>" placeholder="e.g. joindiaspora.com" required>
  <?php
}

/**
 * Render the "Username" field.
 */
function wp_to_diaspora_user_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  ?>
  <input type="text" name="wp_to_diaspora_settings[user]" value="<?php echo $options['user']; ?>" placeholder="<?php _e( 'Username', 'wp_to_diaspora' ); ?>" required>
  <?php
}

/**
 * Render the "Password" field.
 */
function wp_to_diaspora_password_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  // Special case if we already have a password.
  $has_password = ( isset( $options['password'] ) && '' !== $options['password'] );
  $placeholder  = ( $has_password ) ? __( 'Password already set', 'wp_to_diaspora' ) : __( 'Password', 'wp_to_diaspora' );
  $required     = ( $has_password ) ? '' : 'required';
  ?>
  <input type="password" name="wp_to_diaspora_settings[password]" value="" placeholder="<?php echo $placeholder; ?>" <?php echo $required; ?>>
  <?php if ( $has_password ) : ?>
    <p class="description"><?php _e( 'If you would like to change the password type a new one. Otherwise leave this blank.', 'wp_to_diaspora' ); ?></p>
  <?php endif;
}

/**
 * Callback for the "Defaults" section.
 */
function wp_to_diaspora_defaults_section_cb() {
  _e( 'Define the default posting behaviour for all posts here. These settings can be modified for each post individually, by changing the values in the "WP to Diaspora*" meta box, which gets displayed in your post edit screen.', 'wp_to_diaspora' );

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
}

/**
 * Render the "Post to Diaspora*" checkbox.
 */
function wp_to_diaspora_post_to_diaspora_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  ?>
  <label><input type="checkbox" id="post_to_diaspora" name="wp_to_diaspora_settings[post_to_diaspora]" value="1" <?php checked( $options['post_to_diaspora'] ); ?>><?php _e( 'Yes', 'wp_to_diaspora' ); ?></label>
  <?php
}

/**
 * Render the "Show 'Posted at' link" checkbox.
 */
function wp_to_diaspora_fullentrylink_render() {
  $options = get_option( 'wp_to_diaspora_settings' );
  ?>
  <label><input type="checkbox" id="fullentrylink" name="wp_to_diaspora_settings[fullentrylink]" value="1" <?php checked( $options['fullentrylink'], 1 ); ?>><?php _e( 'Yes', 'wp_to_diaspora' ); ?></label>
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
  <label><input type="radio" name="wp_to_diaspora_settings[display]" value="excerpt" <?php checked( $options['display'], 'excerpt' ); ?>><?php _e( 'Excerpt', 'wp_to_diaspora' ); ?></label>
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

      try {
        $conn = new Diasphp( 'https://' . $options['pod'] );
        $conn->login( $options['user'], $options['password'] );

        // Show success message if connected successfully.
        add_settings_error(
          'wp_to_diaspora_settings',
          'wp_to_diaspora_connected',
          sprintf( __( 'Connected to %s', 'wp_to_diaspora' ), $options['pod'] ),
          'updated'
        );
      } catch ( Exception $e ) {
        // Show error message if connection failed.
        add_settings_error(
          'wp_to_diaspora_settings',
          'wp_to_diaspora_connected',
          sprintf( __( 'Couldn\'t connect to %s: Invalid pod, username or password.', 'wp_to_diaspora' ), $options['pod'] ),
          'error'
        );
      }

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

  $new_values['post_to_diaspora'] = isset( $new_values['post_to_diaspora'] );
  $new_values['fullentrylink']    = isset( $new_values['fullentrylink'] );

  if ( ! in_array( $new_values['display'], array( 'full', 'excerpt' ) ) ) {
    $new_values['display'] = $options['display'];
  }

  // If password is blank, it hasn't been changed.
  if ( '' === $new_values['password'] ) {
    $new_values['password'] = $options['password'];
  }

  // TODO: What for? The version will never be set, as $new_values['version'] is always null.
  if ( ! isset( $new_values['version'] ) ) {
    $new_values['version'] = $options['version'];
  }

  return $new_values;
}


/* META BOX */

/**
 * Adds a box to the main column on the Post and Page edit screens.
 */
function wp_to_diaspora_add_meta_box() {
  add_meta_box(
    'wp_to_diaspora_sectionid',
    __( 'WP to Diaspora*', 'wp_to_diaspora' ),
    'wp_to_diaspora_meta_box_callback',
    'post',
    'side',
    'high'
  );
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
  $defaults = get_option( 'wp_to_diaspora_settings' );
  $post_meta = get_post_meta( $post->ID );

  // Go through all default values and use the default if no meta data is set yet.
  foreach ( $defaults as $key => $default ) {
    $key = '_wp_to_diaspora_' . $key;
    $post_meta[ $key ] = ( isset( $post_meta[ $key ] ) && is_array( $post_meta[ $key ] ) ) ? array_shift( $post_meta[ $key ] ) : $default;
  }

  // If this post is already published, don't post again to Diaspora*.
  $post_to_diaspora = ( 'publish' === get_post_status( $post->ID ) ) ? false : $post_meta['_wp_to_diaspora_post_to_diaspora'];
  ?>
  <label><input type="checkbox" id="post_to_diaspora" name="wp_to_diaspora_post_to_diaspora" value="1" <?php checked( $post_to_diaspora ); ?>><?php _e( 'Post to Diaspora*', 'wp_to_diaspora' ); ?></label><br />

  <label title="<?php _e( 'Include a link back to your original post.', 'wp_to_diaspora' ); ?>"><input type="checkbox" id="fullentrylink" name="wp_to_diaspora_fullentrylink" value="1" <?php checked( $post_meta['_wp_to_diaspora_fullentrylink'] ); ?>><?php _e( 'Show "Posted at" link?', 'wp_to_diaspora' ); ?></label><br />

  <label><input type="radio" name="wp_to_diaspora_display" value="full" <?php checked( $post_meta['_wp_to_diaspora_display'], 'full' ); ?>><?php _e( 'Full Post', 'wp_to_diaspora' ); ?></label>&nbsp;
  <label><input type="radio" name="wp_to_diaspora_display" value="excerpt" <?php checked( $post_meta['_wp_to_diaspora_display'], 'excerpt' ); ?>><?php _e( 'Excerpt', 'wp_to_diaspora' ); ?></label>
  <?php
}

/**
 * When the post is saved, save our custom data.
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
  if ( ! current_user_can( 'edit_post', $post_id ) ) {
    return;
  }

  // OK, it's safe for us to save the data now.

  // Update the meta fields in the database.
  update_post_meta( $post_id, '_wp_to_diaspora_post_to_diaspora', isset( $_POST['wp_to_diaspora_post_to_diaspora'] ) );
  update_post_meta( $post_id, '_wp_to_diaspora_fullentrylink',    isset( $_POST['wp_to_diaspora_fullentrylink'] ) );
  update_post_meta( $post_id, '_wp_to_diaspora_display',          sanitize_text_field( $_POST['wp_to_diaspora_display'] ) );
}
add_action( 'save_post', 'wp_to_diaspora_save_meta_box_data', 10 );
