<?php

/**
 * diaspora* flavoured WP Post.
 *
 * @package WP_To_Diaspora
 * @subpackage Post
 * @since 1.4.1
 */
class WP2D_Post {

  /**
   * The original post object.
   *
   * @var WP_Post
   */
  private $_wp_post = null;

  /**
   * The post's custom meta data.
   *
   * @var array
   */
  private $_meta = null;

  /**
   * Setup all the necessary WP callbacks.
   */
  public static function setup() {
    $instance = new WP2D_Post();

    // Notices when a post has been shared or if it has failed.
    add_action( 'admin_notices', array( $instance, 'admin_notices' ) );
    add_action( 'admin_init', array( $instance, 'ignore_post_error' ) );

    // Handle diaspora* posting when saving the post.
    add_action( 'save_post', array( $instance, 'post' ), 20, 2 );
    add_action( 'save_post', array( $instance, 'save_meta_box_data' ), 10 );

    // Add meta boxes.
    add_action( 'add_meta_boxes', array( $instance, 'add_meta_boxes' ) );
  }

  /**
   * Constructor.
   *
   * @param int|WP_Post $post Post ID or the post itself.
   */
  public function __construct( $post = null) {
    // Assign the original post object.
    $this->_wp_post = get_post( $post );
  }

  /**
   * Post to diaspora* when saving a post.
   *
   * @param integer $post_id ID of the post being saved.
   * @param WP_Post $post    Post object being saved.
   */
  public function post( $post_id, $post ) {
    $this->_wp_post = $post;
    $options = WP2D_Options::get_instance();

    // Is this post type enabled for posting?
    if ( ! in_array( $post->post_type, $options->get_option( 'enabled_post_types' ) ) ) {
      return;
    }

    // Get the post's meta data.
    $this->_meta = get_post_meta( $post_id, '_wp_to_diaspora', true );
    if ( empty( $this->_meta ) ) {
      return;
    }

    // Facilitate access to meta data.
    extract( $this->_meta, EXTR_PREFIX_ALL, 'meta' );

    // Make sure we're posting to diaspora* and the post isn't password protected.
    // TODO: Maybe somebody wants to share a password protected post to a closed aspect.
    if ( $meta_post_to_diaspora && 'publish' === $post->post_status && '' === $post->post_password ) {

      $status_message = $this->_get_title_link();

      // Post the full post text or just the excerpt?
      if ( 'full' === $meta_display ) {
        $status_message .= $this->_get_full_content();
      } else {
        $status_message .= $this->_get_excerpt_content();
      }

      // Add the tags assigned to the post.
      $status_message .= $this->_get_tags_to_add();

      // Add the original entry link to the post?
      $status_message .= $this->_get_posted_at_link();

      $status_markdown = new HTML_To_Markdown( $status_message );
      $status_message  = $status_markdown->output();

      // Add services to share to via diaspora*.
      $extra_data = array(
        'services' => $meta_services
      );

      // Set up the connection to diaspora*.

      $api = new WP2D_API( $options->get_option( 'pod' ) );
      if ( ! ( $api->init() && $api->login( $options->get_option( 'username' ), WP2D_Helpers::decrypt( $options->get_option( 'password' ) ) ) ) ) {
        return false;
      }

      if ( ! empty( $status_message ) && ! $api->last_error && $response = $api->post( $status_message, $meta_aspects, $extra_data ) ) {
        // Save certain diaspora* post data as meta data for future reference.
        $this->_save_to_history( $response );

        // If there is still a previous post error around, remove it.
        delete_post_meta( $post_id, '_wp_to_diaspora_post_error' );
      } else {
        // Save the post error as post meta data, so we can display it to the user.
        update_post_meta( $post_id, '_wp_to_diaspora_post_error', $api->last_error );
      }
    }
  }

  /**
   * Get the title of the post linking to the post itself.
   *
   * @return string Post title as a link.
   */
  private function _get_title_link() {
    return sprintf(
      '<p><b><a href="%1$s" title="permalink to %2$s">%2$s</a></b></p>',
      get_permalink( $this->_wp_post->ID ),
      $this->_wp_post->post_title
    );
  }

  /**
   * Get the full post content with only default filters applied.
   *
   * @return string The full post content.
   */
  private function _get_full_content() {
    // Disable all filters and then enable only defaults. This prevents additional filters from being posted to diaspora*.
    remove_all_filters( 'the_content' );
    foreach ( array( 'wptexturize', 'convert_smilies', 'convert_chars', 'wpautop', 'shortcode_unautop', 'prepend_attachment', array( $this, 'embed_remove' ) ) as $filter ) {
      add_filter( 'the_content', $filter );
    }
    // Extract URLs from [embed] shortcodes.
    add_filter( 'embed_oembed_html', array( $this, 'embed_url' ), 10, 2 );

    return apply_filters( 'the_content', $this->_wp_post->post_content );
  }

  /**
   * Get the post's excerpt in a nice format.
   *
   * @return string Post's excerpt.
   */
  private function _get_excerpt_content() {
    // Look for the excerpt in the following order:
    // 1. Custom post excerpt
    // 2. Text up to the <!--more--> tag
    // 3. Manually trimmed content
    $post = $this->_wp_post;
    $excerpt = $post->post_excerpt;
    if ( '' == $excerpt ) {
      if ( $more_pos = strpos( $post->post_content, '<!--more' ) ) {
        $excerpt = substr( $post->post_content, 0, $more_pos );
      } else {
        $excerpt = wp_trim_words( $post->post_content, 42, '[...]' );
      }
    }
    return '<p>' . $excerpt . '</p>';
  }

  /**
   * Get a string of tags that have been added to the post.
   *
   * @return string Tags added to the post.
   */
  private function _get_tags_to_add() {
    $options = WP2D_Options::get_instance();
    $tags_to_post = $this->_meta['tags_to_post'];
    $custom_tags  = $this->_meta['custom_tags'];
    $tags_to_add  = '';

    // Add any diaspora* tags?l
    if ( ! empty( $tags_to_post ) ) {
      // The diaspora* tags to add to the post.
      $diaspora_tags = array();

      // Add global tags?
      if ( in_array( 'global', $tags_to_post ) ) {
        $diaspora_tags += array_flip( $options->get_option( 'global_tags' ) );
      }

      // Add custom tags?
      if ( in_array( 'custom', $tags_to_post ) ) {
        $diaspora_tags += array_flip( $custom_tags );
      }

      // Add post tags?
      if ( in_array( 'post', $tags_to_post ) ) {
        // Clean up the post tags.
        $diaspora_tags += array_flip( wp_get_post_tags( $this->_wp_post->ID, array( 'fields' => 'slugs' ) ) );
      }

      // Get an array of cleaned up tags.
      $diaspora_tags = WP2D_Helpers::get_clean_tags( array_keys( $diaspora_tags ) );

      // Get all the tags and list them all nicely in a row.
      $diaspora_tags_clean = array();
      foreach ( $diaspora_tags as $tag ) {
        $diaspora_tags_clean[] = '#' . $tag;
      }

      // Add all the found tags.
      if ( ! empty( $diaspora_tags_clean ) ) {
        $tags_to_add = implode( ' ', $diaspora_tags_clean ) . '<br />';
      }
    }
    return $tags_to_add;
  }

  /**
   * Get the link to the original post.
   *
   * @return string Original post link.
   */
  private function _get_posted_at_link() {
    $link = '';
    if ( $this->_meta['fullentrylink'] ) {
      $link = sprintf( '%1$s [%2$s](%2$s "%3$s")',
        __( 'Originally posted at:', 'wp_to_diaspora' ),
        get_permalink( $this->_wp_post->ID ),
        $this->_wp_post->post_title
      );
    }
    return $link;
  }

  /**
   * Save the details of the new diaspora* post to this post's history.
   *
   * @param object $response Response from the API containing the diaspora* post details.
   */
  private function _save_to_history( $response ) {
    $post    = $this->_wp_post;
    $post_id = $post->ID;

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
      'aspects'    => $this->_meta['aspects'],
      'nsfw'       => $response->nsfw,
      'post_url'   => $response->permalink
    );
    update_post_meta( $post_id, '_wp_to_diaspora_post_history', $diaspora_post_history );
  }

  /**
   * Return URL from [embed] shortcode instead of generated iframe.
   */
  public function embed_url( $html, $url ) {
    return $url;
  }

  /**
   * Removes '[embed]' and '[/embed]' left by embed_url.
   *
   *  TODO: It would be great to fix it using only one filter.
   *        It's happening because embed filter is being removed by remove_all_filters('the_content') on wp_to_diaspora_post().
   */
  public function embed_remove( $content ) {
    return str_replace( array( '[embed]', '[/embed]' ), array( '<p>', '</p>' ), $content );
  }


  /* META BOX */

  /**
   * Adds a meta box to the main column on the enabled Post Types' edit screens.
   */
  public function add_meta_boxes() {
    $options = WP2D_Options::get_instance();
    foreach ( $options->get_option( 'enabled_post_types' ) as $post_type ) {
      add_meta_box(
        'wp_to_diaspora_meta_box',
        'WP to diaspora*',
        array( $this, 'meta_box_render' ),
        $post_type,
        'side',
        'high'
      );
    }
  }

  /**
   * Prints the meta box content.
   *
   * @param WP_Post $post The object for the current post.
   */
  public function meta_box_render( $post ) {
    // Add an nonce field so we can check for it later.
    wp_nonce_field( 'wp_to_diaspora_meta_box', 'wp_to_diaspora_meta_box_nonce' );

    // Get the default values to use, but give priority to the meta data already set.
    $options = WP2D_Options::get_instance();

    // Use the default values if no meta data is set yet.
    $post_meta = wp_parse_args(
      get_post_meta( $post->ID, '_wp_to_diaspora', true ),
      $options->get_options()
    );

    // Make sure we have some value for post meta fields.
    $post_meta['custom_tags'] = ( isset( $post_meta['custom_tags'] ) ) ? $post_meta['custom_tags'] : array();

    // Facilitate access to meta data.
    extract( $post_meta, EXTR_PREFIX_ALL, 'meta' );

    // If this post is already published, don't post again to diaspora*.
    $meta_post_to_diaspora = ( 'publish' === get_post_status( $post->ID ) ) ? false : $meta_post_to_diaspora;
    $meta_aspects          = ( ! empty( $meta_aspects )  && is_array( $meta_aspects ) )  ? $meta_aspects  : array();
    $meta_services         = ( ! empty( $meta_services ) && is_array( $meta_services ) ) ? $meta_services : array();

    // Have we already posted on diaspora*?
    if ( ( $diaspora_post_history = get_post_meta( $post->ID, '_wp_to_diaspora_post_history', true ) ) && is_array( $diaspora_post_history ) ) {
      $latest_post = end( $diaspora_post_history );
      ?>
      <p><a href="<?php echo $latest_post['post_url']; ?>" target="_blank"><?php _e( 'Already posted to diaspora*.', 'wp_to_diaspora' ); ?></a></p>
      <?php
    }
    ?>

    <p><?php $options->post_to_diaspora_render( $meta_post_to_diaspora ); ?></p>
    <p><?php $options->fullentrylink_render( $meta_fullentrylink ); ?></p>
    <p><?php $options->display_render( $meta_display ); ?></p>
    <p><?php $options->tags_to_post_render( $meta_tags_to_post ); ?></p>
    <p><?php $options->custom_tags_render( $meta_custom_tags ); ?></p>
    <p><?php $options->aspects_render( $meta_aspects ); ?></p>
    <p><?php $options->services_render( $meta_services ); ?></p>

    <?php
  }

  /**
   * When the post is saved, save our meta data.
   *
   * @param int $post_id The ID of the post being saved.
   */
  public function save_meta_box_data( $post_id ) {
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

    // Make real sure that we have some meta data to save.
    if ( ! isset( $_POST['wp_to_diaspora_settings'] ) ) {
      return;
    }

    // Meta data to save.
    $meta_to_save = $_POST['wp_to_diaspora_settings'];
    $options = WP2D_Options::get_instance();

    // Checkboxes.
    foreach ( array( 'post_to_diaspora', 'fullentrylink' ) as $option ) {
      $meta_to_save[ $option ] = isset( $meta_to_save[ $option ] );
    }

    // Single Selects.
    foreach ( array( 'display' ) as $option ) {
      if ( isset( $meta_to_save[ $option ] ) && ! $options->is_valid_value( $option, $meta_to_save[ $option ] ) ) {
        $meta_to_save[ $option ] = $options->get_option( $option );
      }
    }

    // Multiple Selects.
    foreach ( array( 'tags_to_post' ) as $option ) {
      if ( isset( $meta_to_save[ $option ] ) ) {
        foreach ( (array) $meta_to_save[ $option ] as $option_value ) {
          if ( ! $options->is_valid_value( $option, $option_value ) ) {
            unset( $meta_to_save[ $option ] );
            break;
          }
        }
      } else {
        $meta_to_save[ $option ] = array();
      }
    }


    // Save custom tags as array.
    $meta_to_save['custom_tags'] = WP2D_Helpers::get_clean_tags( $meta_to_save['custom_tags'] );

    // Clean up the list of aspects. If the list is empty, only use the 'Public' aspect.
    if ( empty( $meta_to_save['aspects'] ) || ! is_array( $meta_to_save['aspects'] ) ) {
      $meta_to_save['aspects'] = array( 'public' );
    } else {
      array_walk( $meta_to_save['aspects'], 'sanitize_text_field' );
    }

    // Clean up the list of services.
    if ( empty( $meta_to_save['services'] ) || ! is_array( $meta_to_save['services'] ) ) {
      $meta_to_save['services'] = array();
    } else {
      array_walk( $meta_to_save['services'], 'sanitize_text_field' );
    }

    // Update the meta data for this post.
    update_post_meta( $post_id, '_wp_to_diaspora', $meta_to_save );
  }


  /**
   * Add admin notices when a post gets displayed.
   */
  public function admin_notices() {
    global $post, $pagenow;
    if ( ! $post || 'post.php' !== $pagenow ) {
      return;
    }

    if ( $error = get_post_meta( $post->ID, '_wp_to_diaspora_post_error', true ) ) {
      // This notice will only be shown if posting to diaspora* has failed.
      printf( '<div class="error notice is-dismissible"><p>%1$s %2$s <a href="%3$s">%4$s</a></p></div>',
        __( 'Failed to post to diaspora*.', 'wp_to_diaspora' ),
        $error,
        add_query_arg( 'wp_to_diaspora_ignore_post_error', 'yes' ),
        __( 'Ignore', 'wp_to_diaspora' )
      );
    } elseif ( ( $diaspora_post_history = get_post_meta( $post->ID, '_wp_to_diaspora_post_history', true ) ) && is_array( $diaspora_post_history ) ) {
      // Get the latest post from the history.
      $latest_post = end( $diaspora_post_history );

      // Only show if this post is showing a message and the post is a fresh share.
      if ( isset( $_GET['message'] ) && $post->post_modified == $latest_post['created_at'] ) {
        printf( '<div class="updated notice is-dismissible"><p>%1$s <a href="%2$s" target="_blank">%3$s</a></p></div>',
          __( 'Successfully posted to diaspora*.', 'wp_to_diaspora' ),
          $latest_post['post_url'],
          __( 'View Post' )
        );
      }
    }
  }

  /**
   * Delete the error post meta data if it gets ignored.
   */
  public function ignore_post_error() {
    // If "Ignore" link has been clicked, delete the post error meta data.
    if ( isset( $_GET['wp_to_diaspora_ignore_post_error'], $_GET['post'] ) && 'yes' === $_GET['wp_to_diaspora_ignore_post_error'] ) {
      delete_post_meta( $_GET['post'], '_wp_to_diaspora_post_error' );
    }
  }

}
