<?php
/**
 * Plugin Name: WP to Diaspora
 * Description: Post WordPress posts on Diaspora*
 * Version: 1.0
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


function wp_to_diaspora_post($post_id){
    $post = get_post($post_id);

    if(get_post_status($post_id) == "publish" && empty($post->post_password)){
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
add_action('publish_post', 'wp_to_diaspora_post', 10, 2);


// i18n
function wp_to_diaspora_plugins_loaded() {
    load_plugin_textdomain( 'wp_to_diaspora', false, 'wp-to-diaspora/languages' );
}
add_action( 'plugins_loaded', 'wp_to_diaspora_plugins_loaded' );


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
    $options = get_option( 'wp_to_diaspora_settings' );
    ?>
    <input type='password' name='wp_to_diaspora_settings[password]' value='<?php echo $options['password']; ?>' placeholder="password" required>
    <?php
}


function wp_to_diaspora_settings_section_callback(  ) { 
    echo __( '', 'wp_to_diaspora' );
}


function wp_to_diaspora_options_page(  ) { 
    ?>
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



?>