<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  exit();
}

delete_option( 'wp_to_diaspora_settings' );

?>