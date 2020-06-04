<?php
/**
 * Called when uninstalling the plugin.
 *
 * @package WP_To_Diaspora
 * @todo    What about all the post meta data?
 */

// Exit if accessed directly.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Delete the WP2D settings.
delete_option( 'wp_to_diaspora_settings' );
