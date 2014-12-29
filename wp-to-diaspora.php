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


function wp_to_diaspora_post($post_id){
    $post = get_post($post_id);

    if(get_post_status($post_id) == "publish" && empty($post->post_password)){
        $options = get_option( 'wp_to_diaspora_settings' );

        $status_message = "<b><a href='{get_permalink($post_id)}'>{$post->post_title}</a></b>";
        $status_message .= apply_filters ("the_content", $post->post_content);
        $status_message .= 'Full entry on [' . get_permalink($post_id) . '](' . get_permalink($post_id) . '"' . $post->post_title . '")';

        try {
            $conn = new Diasphp( 'https://' . $options['wp_to_diaspora_pod'] );
            $conn->login( $options['wp_to_diaspora_user'], $options['wp_to_diaspora_password'] );
            $conn->post($status_message, 'wp-to-diaspora');
        } catch (Exception $e) {
            echo '<div class="error">WP to Diaspora*: Send ' . $post->post_title . ' failed: ' . $e->getMessage() . '</div>';
        }

    }
}
add_action('publish_post', 'wp_to_diaspora_post', 10, 2);




/* OPTIONS PAGE */

add_action( 'admin_menu', 'wp_to_diaspora_add_admin_menu' );
add_action( 'admin_init', 'wp_to_diaspora_settings_init' );


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
        'wp_to_diaspora_pod', 
        __( 'Diaspora* Pod', 'wp_to_diaspora' ), 
        'wp_to_diaspora_pod_render', 
        'pluginPage', 
        'wp_to_diaspora_pluginPage_section' 
    );

    add_settings_field( 
        'wp_to_diaspora_user', 
        __( 'Username', 'wp_to_diaspora' ), 
        'wp_to_diaspora_user_render', 
        'pluginPage', 
        'wp_to_diaspora_pluginPage_section' 
    );

    add_settings_field( 
        'wp_to_diaspora_password', 
        __( 'Password', 'wp_to_diaspora' ), 
        'wp_to_diaspora_password_render', 
        'pluginPage', 
        'wp_to_diaspora_pluginPage_section' 
    );
}


function wp_to_diaspora_pod_render(  ) { 
    $options = get_option( 'wp_to_diaspora_settings' );
    ?>
    <input type='text' name='wp_to_diaspora_settings[wp_to_diaspora_pod]' value='<?php echo $options['wp_to_diaspora_pod']; ?>' placeholder="e.g. joindiaspora.com">
    <?php
}


function wp_to_diaspora_user_render(  ) { 
    $options = get_option( 'wp_to_diaspora_settings' );
    ?>
    <input type='text' name='wp_to_diaspora_settings[wp_to_diaspora_user]' value='<?php echo $options['wp_to_diaspora_user']; ?>' placeholder="username">
    <?php
}


function wp_to_diaspora_password_render(  ) { 
    $options = get_option( 'wp_to_diaspora_settings' );
    ?>
    <input type='password' name='wp_to_diaspora_settings[wp_to_diaspora_password]' value='<?php echo $options['wp_to_diaspora_password']; ?>' placeholder="password">
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



/**
* Class from Friendica addon https://github.com/friendica/friendica-addons/tree/master/diaspora  -- Thanks, @annando!
*
* Ein fies zusammengehackter PHP-Diaspory-Client, der direkt von diesem abgeschaut ist:
* https://github.com/Javafant/diaspy/blob/master/client.py
*/

class Diasphp {
    function __construct($pod) {
        $this->token_regex = '/content="(.*?)" name="csrf-token/';

        $this->pod = $pod;
        $this->cookiejar = tempnam(sys_get_temp_dir(), 'cookies');
    }

    function _fetch_token() {
        $ch = curl_init();

        curl_setopt ($ch, CURLOPT_URL, $this->pod . "/stream");
        curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);

        $output = curl_exec ($ch);
        curl_close($ch);

        // Token holen und zurückgeben
        preg_match($this->token_regex, $output, $matches);
        return $matches[1];
    }

    function login($username, $password) {
        $datatopost = array(
            'user[username]' => $username,
            'user[password]' => $password,
            'authenticity_token' => $this->_fetch_token()
        );

        $poststr = http_build_query($datatopost);

        // Adresse per cURL abrufen
        $ch = curl_init();

        curl_setopt ($ch, CURLOPT_URL, $this->pod . "/users/sign_in");
        curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($ch, CURLOPT_POST, true);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $poststr);

        curl_exec ($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if($info['http_code'] != 302) {
            throw new Exception('Login error '.print_r($info, true));
        }

        // Das Objekt zurückgeben, damit man Aurufe verketten kann.
        return $this;
    }

    function post($text, $provider = "diasphp") {
        // post-daten vorbereiten
        $datatopost = json_encode(array(
                'aspect_ids' => 'public',
                'status_message' => array('text' => $text,
                            'provider_display_name' => $provider)
        ));

        // header vorbereiten
        $headers = array(
            'Content-Type: application/json',
            'accept: application/json',
            'x-csrf-token: '.$this->_fetch_token()
        );

        // Adresse per cURL abrufen
        $ch = curl_init();

        curl_setopt ($ch, CURLOPT_URL, $this->pod . "/status_messages");
        curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($ch, CURLOPT_POST, true);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $datatopost);
        curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);

        curl_exec ($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if($info['http_code'] != 201) {
            throw new Exception('Post error '.print_r($info, true));
        }

        // Ende der möglichen Kette, gib mal "true" zurück.
        return true;
    }
}

?>