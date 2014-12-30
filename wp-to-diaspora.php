<?php
/**
 * Plugin Name: WP to Diaspora*
 * Description: Post WordPress posts on Diaspora*
 * Version: 1.1
 * Author: Augusto Bennemann
 * Plugin URI: https://github.com/gutobenn/wp-to-diaspora
 * License: GPL2
 */

/*  Copyright 2014 Augusto Bennemann (email: gutobenn at gmail.com)

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

require_once dirname (__FILE__) . '/class-diaspora.php';


function wp_to_diaspora_post($post_id) {
    $post = get_post($post_id);
    $value = get_post_meta( $post_id, '_wp_to_diaspora_checked', true );


    if($value == 'yes' && get_post_status($post_id) == "publish" && empty($post->post_password)) {
        $options = get_option( 'wp_to_diaspora_settings' );

        $status_message = "<b><a href='{get_permalink($post_id)}'>{$post->post_title}</a></b>";
        $status_message .= apply_filters ("the_content", $post->post_content);
        $status_message .= 'Full entry on [' . get_permalink($post_id) . '](' . get_permalink($post_id) . '"' . $post->post_title . '")';

        try {
            $conn = new Diasphp( 'https://' . $options['pod'] );
            $conn->login( $options['user'], $options['password'] );
            $conn->post($status_message, 'wp-to-diaspora');
        } catch (Exception $e) {
            echo '<div class="error">WP to Diaspora*: Send ' . $post->post_title . ' failed: ' . $e->getMessage() . '</div>';
        }

    }
}
add_action('save_post', 'wp_to_diaspora_post', 10, 2);


// i18n
function wp_to_diaspora_plugins_loaded() {
    load_plugin_textdomain( 'wp_to_diaspora', false, 'wp-to-diaspora/languages' );
}
add_action( 'plugins_loaded', 'wp_to_diaspora_plugins_loaded' );


/* 'Settings' link on plugins page */
function wp_to_diaspora_settings_link ( $links ) {
    $mylinks = array(
        '<a href="' . admin_url( 'options-general.php?page=wp_to_diaspora' ) . '">' . __('Settings', 'wp_to_diaspora') . '</a>',
    );
    return array_merge( $links, $mylinks );
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wp_to_diaspora_settings_link' );



/* OPTIONS PAGE */

function wp_to_diaspora_add_admin_menu(  ) {
    add_options_page( 'WP to Diaspora*', 'WP to Diaspora*', 'manage_options', 'wp_to_diaspora', 'wp_to_diaspora_options_page' );
}


function wp_to_diaspora_settings_init(  ) {
    register_setting( 'pluginPage', 'wp_to_diaspora_settings' );

    add_settings_section(
        'wp_to_diaspora_pluginPage_section',
        __( '', 'wp_to_diaspora' ),
        'wp_to_diaspora_settings_section_callback',
        'pluginPage'
    );

    add_settings_field(
        'pod',
        __( 'Diaspora* Pod', 'wp_to_diaspora' ),
        'wp_to_diaspora_pod_render',
        'pluginPage',
        'wp_to_diaspora_pluginPage_section'
    );

    add_settings_field(
        'user',
        __( 'Username', 'wp_to_diaspora' ),
        'wp_to_diaspora_user_render',
        'pluginPage',
        'wp_to_diaspora_pluginPage_section'
    );

    add_settings_field(
        'password',
        __( 'Password', 'wp_to_diaspora' ),
        'wp_to_diaspora_password_render',
        'pluginPage',
        'wp_to_diaspora_pluginPage_section'
    );
}


function wp_to_diaspora_pod_render(  ) {
    $options = get_option( 'wp_to_diaspora_settings' );
    ?>

    <input type='text' name='wp_to_diaspora_settings[pod]' value='<?php echo $options['pod']; ?>' placeholder="e.g. joindiaspora.com" required>
    
    <?php
}


function wp_to_diaspora_user_render(  ) {
    $options = get_option( 'wp_to_diaspora_settings' );
    ?>
    <input type='text' name='wp_to_diaspora_settings[user]' value='<?php echo $options['user']; ?>' placeholder="username" required>
    <?php
}


function wp_to_diaspora_password_render(  ) {
    $options = get_option( 'wp_to_diaspora_settings' ); ?>

    <input type='password' name='wp_to_diaspora_settings[password]' value='<?php echo $options['password']; ?>' placeholder="password" required>

    <?php
}


function wp_to_diaspora_settings_section_callback(  ) {
    echo __( '', 'wp_to_diaspora' );
}


function wp_to_diaspora_options_page(  ) { ?>
    <form action='options.php' method='post'>

    <h2>WP to Diaspora*</h2>

    <?php
        settings_fields( 'pluginPage' );
        do_settings_sections( 'pluginPage' );
        submit_button();
    ?>

    </form>
    <?php
}
add_action( 'admin_menu', 'wp_to_diaspora_add_admin_menu' );
add_action( 'admin_init', 'wp_to_diaspora_settings_init' );



/* META BOX */

/**
 * Adds a box to the main column on the Post and Page edit screens.
 */
function wp_to_diaspora_add_meta_box() {

    add_meta_box(
        'wp_to_diaspora_sectionid',
        __( 'Post to Diaspora*', 'wp_to_diaspora' ),
        'wp_to_diaspora_meta_box_callback',
        'post',
        'side',
        'high'
    );

}
add_action( 'add_meta_boxes', 'wp_to_diaspora_add_meta_box' );

/**
 * Prints the box content.
 *
 * @param WP_Post $post The object for the current post/page.
 */
function wp_to_diaspora_meta_box_callback( $post ) {

    // Add an nonce field so we can check for it later.
    wp_nonce_field( 'wp_to_diaspora_meta_box', 'wp_to_diaspora_meta_box_nonce' );

    /*
     * Use get_post_meta() to retrieve an existing value
     * from the database and use the value for the form.
     */
    $value = get_post_meta( $post->ID, '_wp_to_diaspora_checked', true );

    if (get_post_status($post->ID) == "publish" || $value == 'no')
        $value = 'no'; // if already posted, do not post again
    else
        $value = 'yes';
    ?>

    <input type="radio" name="wp_to_diaspora_check" value="yes" <?php checked( $value, 'yes' );?> ><?php _e( 'Yes', 'wp_to_diaspora' );?><br>
    <input type="radio" name="wp_to_diaspora_check" value="no" <?php checked( $value, 'no' ); ?> ><?php _e( 'No', 'wp_to_diaspora' ); ?><br>

    <?php
}

/**
 * When the post is saved, saves our custom data.
 *
 * @param int $post_id The ID of the post being saved.
 */
function wp_to_diaspora_save_meta_box_data( $post_id ) {

    /*
     * We need to verify this came from our screen and with proper authorization,
     * because the save_post action can be triggered at other times.
     */

    // Check if our nonce is set.
    if ( ! isset( $_POST['wp_to_diaspora_meta_box_nonce'] ) ) {
        return;
    }

    // Verify that the nonce is valid.
    if ( ! wp_verify_nonce( $_POST['wp_to_diaspora_meta_box_nonce'], 'wp_to_diaspora_meta_box' ) ) {
        return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check the user's permissions.
    if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

        if ( ! current_user_can( 'edit_page', $post_id ) ) {
            return;
        }

    } else {

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
    }

    /* OK, it's safe for us to save the data now. */

    // Make sure that it is set.
    if ( ! isset( $_POST['wp_to_diaspora_check'] ) ) {
        return;
    }

    // Sanitize user input.
    $my_data = sanitize_text_field( $_POST['wp_to_diaspora_check'] );

    // Update the meta field in the database.
    update_post_meta( $post_id, '_wp_to_diaspora_checked', $my_data );
}
add_action( 'save_post', 'wp_to_diaspora_save_meta_box_data' );

?>