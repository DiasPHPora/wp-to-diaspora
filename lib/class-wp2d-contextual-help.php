<?php
/**
 * Plugin Contextual Help.
 *
 * @package WP_To_Diaspora\Help
 * @since   1.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class that handles the contextual help.
 */
class WP2D_Contextual_Help {

	/**
	 * Only instance of this class.
	 *
	 * @var WP2D_Contextual_Help|null
	 */
	private static ?WP2D_Contextual_Help $instance = null;

	/**
	 * Create / Get the instance of this class.
	 *
	 * @return WP2D_Contextual_Help Instance of this class.
	 */
	public static function instance(): WP2D_Contextual_Help {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->constants();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Define all the required constants.
	 *
	 * @since 1.5.0
	 */
	private function constants(): void {
		define( 'WP2D_EXT_WPORG', esc_url( 'https://wordpress.org/plugins/wp-to-diaspora' ) );
		define( 'WP2D_EXT_I18N', esc_url( 'https://poeditor.com/join/project?hash=c085b3654a5e04c69ec942e0f136716a' ) );
		define( 'WP2D_EXT_GH', esc_url( 'https://github.com/DiasPHPora/wp-to-diaspora' ) );
		define( 'WP2D_EXT_DONATE', esc_url( 'https://github.com/DiasPHPora/wp-to-diaspora#donate' ) );
		define( 'WP2D_EXT_GH_ISSUES', esc_url( 'https://github.com/DiasPHPora/wp-to-diaspora/issues' ) );
		define( 'WP2D_EXT_GH_ISSUES_NEW', esc_url( 'https://github.com/DiasPHPora/wp-to-diaspora/issues/new' ) );
	}

	/**
	 * Set up the contextual help menu.
	 */
	private function setup(): void {
		// Do we display the help tabs?
		$post_type          = get_current_screen()?->post_type;
		$enabled_post_types = WP2D_Options::instance()->get_option( 'enabled_post_types' );
		if ( '' !== $post_type && ! in_array( $post_type, $enabled_post_types, true ) ) {
			return;
		}

		// If we don't have a post type, we're on the main settings page.
		if ( '' === $post_type ) {
			// Set the sidebar in the contextual help.
			$this->set_sidebar();

			// Add the main settings tabs and their content.
			$this->add_settings_help_tabs();
		} else {
			// Add the post type specific tabs and their content.
			$this->add_post_type_help_tabs();
		}
	}

	/** Singleton, keep private. */
	private function __clone() {
	}

	/** Singleton, keep private. */
	private function __construct() {
	}

	/**
	 * Set the sidebar in the contextual help.
	 */
	private function set_sidebar(): void {
		get_current_screen()?->set_help_sidebar(
			'<p><strong>' . esc_html__( 'WP to diaspora*', 'wp-to-diaspora' ) . '</strong></p>
			<ul>
				<li><a href="' . WP2D_EXT_GH . '" target="_blank">GitHub</a>
				<li><a href="' . WP2D_EXT_WPORG . '" target="_blank">WordPress.org</a>
				<li><a href="' . WP2D_EXT_I18N . '" target="_blank">' . esc_html__( 'Help with translations', 'wp-to-diaspora' ) . '</a>
				<li><a href="' . WP2D_EXT_DONATE . '" target="_blank">' . esc_html__( 'Make a donation', 'wp-to-diaspora' ) . '</a>
			</ul>'
		);
	}

	/**
	 * Add help tabs to the contextual help on the settings page.
	 */
	private function add_settings_help_tabs(): void {
		$screen = get_current_screen();

		// A short overview of the plugin.
		$screen?->add_help_tab( [
			'id'      => 'overview',
			'title'   => esc_html__( 'Overview', 'wp-to-diaspora' ),
			'content' => '<p><strong>' . esc_html__( 'With WP to diaspora*, sharing your WordPress posts to diaspora* is as easy as ever.', 'wp-to-diaspora' ) . '</strong></p>
				<ol>
					<li>' . esc_html__( 'Enter your diaspora* login details on the "Setup" tab.', 'wp-to-diaspora' ) . '
					<li>' . esc_html__( 'Define the default posting behaviour on the "Defaults" tab.', 'wp-to-diaspora' ) . '
					<li>' . esc_html__( 'Automatically share your WordPress post on diaspora* when publishing it on your website.', 'wp-to-diaspora' ) . '
					<li>' . esc_html__( 'Check out your new post on diaspora*.', 'wp-to-diaspora' ) . '
				</ol>',
		] );

		// How to set up the connection to diaspora*.
		$screen?->add_help_tab( [
			'id'      => 'setup',
			'title'   => esc_html__( 'Setup', 'wp-to-diaspora' ),
			'content' => '<p><strong>' . esc_html__( 'Enter your diaspora* login details to connect your account.', 'wp-to-diaspora' ) . '</strong></p>
				<ul>
					<li><strong>' . esc_html__( 'diaspora* Pod', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'This is the domain name of the pod you are on (e.g. joindiaspora.com)', 'wp-to-diaspora' ) . '
					<li><strong>' . esc_html__( 'Username', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Your diaspora* username (without the pod domain).', 'wp-to-diaspora' ) . '
					<li><strong>' . esc_html__( 'Password', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Your diaspora* password.', 'wp-to-diaspora' ) . '
				</ul>',
		] );

		// Explain the default options and what they do.
		$screen?->add_help_tab( [
			'id'      => 'defaults',
			'title'   => esc_html__( 'Defaults', 'wp-to-diaspora' ),
			'content' => '<p><strong>' . esc_html__( 'Define the default posting behaviour.', 'wp-to-diaspora' ) . '</strong></p>
				<ul>
					<li><strong>' . esc_html__( 'Post types', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Choose the post types that are allowed to be shared to diaspora*.', 'wp-to-diaspora' ) . '
					<li><strong>' . esc_html__( 'Post to diaspora*', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Automatically share new posts to diaspora* when publishing them.', 'wp-to-diaspora' ) . '
					<li><strong>' . esc_html__( 'Show "Posted at" link?', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Add a link back to your original post, at the bottom of the diaspora* post.', 'wp-to-diaspora' ) . '
					<li><strong>' . esc_html__( 'Display', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Choose whether you would like to post the whole post or just the excerpt.', 'wp-to-diaspora' ) . '
					<li><strong>' . esc_html__( 'Tags to post', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'You can add tags to your post to make it easier to find on diaspora*.', 'wp-to-diaspora' ) . '<br>
						<ul>
							<li><strong>' . esc_html__( 'Global tags', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Tags that apply to all posts.', 'wp-to-diaspora' ) . '
							<li><strong>' . esc_html__( 'Custom tags', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Tags that apply to individual posts (can be set on each post).', 'wp-to-diaspora' ) . '
							<li><strong>' . esc_html__( 'Post tags', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Default WordPress Tags of individual posts.', 'wp-to-diaspora' ) . '
						</ul>
					<li><strong>' . esc_html__( 'Global tags', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'A list of tags that gets added to every post.', 'wp-to-diaspora' ) . '
					<li><strong>' . esc_html__( 'Aspects', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Decide which of your diaspora* aspects can see your posts.', 'wp-to-diaspora' ) . '<br><em>' . sprintf( esc_html__( 'Use the "%s" button to load your aspects from diaspora*.', 'wp-to-diaspora' ), esc_html__( 'Refresh Aspects', 'wp-to-diaspora' ) ) . '</em>
					<li><strong>' . esc_html__( 'Services', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Choose the services your new diaspora* post gets shared to.', 'wp-to-diaspora' ) . '<br><em>' . sprintf( esc_html__( 'Use the "%s" button to fetch the list of your connected services from diaspora*.', 'wp-to-diaspora' ), esc_html__( 'Refresh Services', 'wp-to-diaspora' ) ) . '</em>
				</ul>',
		] );

		$screen?->add_help_tab( [
			'id'      => 'ssl',
			'title'   => esc_html__( 'SSL', 'wp-to-diaspora' ),
			'content' => '<p><strong>' . esc_html__( 'WP to diaspora* makes sure the connection to your pod is secure!', 'wp-to-diaspora' ) . '</strong></p>
				<p>' . esc_html__( 'Most diaspora* pods are secured using SSL (Secure Sockets Layer), which makes your connection encrypted. For this connection to work, your server needs to know that those SSL certificates can be trusted.', 'wp-to-diaspora' ) . '</p>
				<p>' . esc_html__( 'Therefore, if your WordPress installation or server does not have an up to date CA certificate bundle, WP to diaspora* may not work for you.', 'wp-to-diaspora' ) . '</p>
				<p>' . esc_html__( 'Lucky for you though, we have you covered if this is the case for you!', 'wp-to-diaspora' ) . '</p>
				<p><a href="https://github.com/DiasPHPora/wp-to-diaspora/wiki/SSL-and-TLS-Issues">' . esc_html__( 'Learn more in the wiki', 'wp-to-diaspora' ) . '</a></p>',
		] );

		// Explain the meta box and the differences to the global defaults.
		$screen?->add_help_tab( [
			'id'      => 'meta-box',
			'title'   => esc_html__( 'Meta Box', 'wp-to-diaspora' ),
			'content' => '<p><strong>' . esc_html__( 'The Meta Box is the new "WP to diaspora*" box you see when editing a post.', 'wp-to-diaspora' ) . '</strong></p>
				<p>' . esc_html__( 'When creating or editing a post, you will notice a new meta box called "WP to diaspora*" which has some options. These options are almost the same as the options you can find in the "Defaults" tab on the settings page. These options are post-specific though, meaning they override the global defaults for the post itself. You will see that the default values are filled in automatically, allowing you to change individual ones as you please.', 'wp-to-diaspora' ) . '</p>
				<p>' . esc_html__( 'There are a few important differences to the settings page:', 'wp-to-diaspora' ) . '</p>
				<ul>
					<li><strong>' . esc_html__( 'Already posted to diaspora*', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'If the post has already been posted to diaspora* a link to the diaspora* post will appear at the top.', 'wp-to-diaspora' ) . '
					<li><strong>' . esc_html__( 'Custom tags', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'A list of tags that gets added to this post. Note that they are separate from the WordPress post tags!', 'wp-to-diaspora' ) . '
				</ul>
				<p class="dashicons-before dashicons-info">' . esc_html__( 'If you don\'t see the meta box, make sure the post type you\'re on has been added to the "Post types" list on the settings page. Also make sure it has been selected from the "Screen Options" at the top of the screen.', 'wp-to-diaspora' ) . '</p>',
		] );

		// Troubleshooting.
		$screen?->add_help_tab( [
			'id'      => 'troubleshooting',
			'title'   => esc_html__( 'Troubleshooting', 'wp-to-diaspora' ),
			'content' => '<p><strong>' . esc_html__( 'Troubleshooting common errors.', 'wp-to-diaspora' ) . '</strong></p>
				<p>' . esc_html__( 'Here are a few common errors and their possible solutions:', 'wp-to-diaspora' ) . '</p>
				<ul>
					<li><strong>' . esc_html( sprintf( __( 'Failed to initialise connection to pod "%s"', 'wp-to-diaspora' ), 'xyz' ) ) . '</strong>: ' . esc_html__( 'This could have multiple reasons.', 'wp-to-diaspora' ) . '
						<ul>
							<li>' . esc_html__( 'Make sure that your pod domain is entered correctly.', 'wp-to-diaspora' ) . '
							<li>' . esc_html__( 'It might be an SSL problem.', 'wp-to-diaspora' ) . sprintf( ' <a href="https://github.com/DiasPHPora/wp-to-diaspora/wiki/SSL-and-TLS-Issues" class="open-help-tab" data-help-tab="ssl">%s</a>', esc_html__( 'Learn more', 'wp-to-diaspora' ) ) . '
							<li>' . esc_html__( 'The pod might be offline at the moment.', 'wp-to-diaspora' ) . '
						</ul>
					<li><strong>' . esc_html__( 'Login failed. Check your login details.', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'Make sure that your username and password are entered correctly.', 'wp-to-diaspora' ) . '
					<li><strong>' . esc_html__( 'Invalid credentials. Please re-save your login info.', 'wp-to-diaspora' ) . '</strong>: ' . esc_html__( 'This may be due to defining WP2D_ENC_KEY after upgrading to 2.2.0, which saves a new encrypted version of your password.', 'wp-to-diaspora' ) . sprintf( ' <a href="https://github.com/DiasPHPora/wp-to-diaspora/wiki/Configuration#wp2d_enc_key-since-220" target="_blank">%s</a>', esc_html__( 'Learn more', 'wp-to-diaspora' ) ) . '
				</ul>',
		] );

		// Show different ways to contribute to the plugin.
		$screen?->add_help_tab( [
			'id'      => 'contributing',
			'title'   => esc_html__( 'Contributing', 'wp-to-diaspora' ),
			'content' => '<p><strong>' . esc_html__( 'So you feel like contributing to the WP to diaspora* plugin? Great!', 'wp-to-diaspora' ) . '</strong></p>
				<p>' . esc_html__( 'There are many different ways that you can help out with this plugin:', 'wp-to-diaspora' ) . '</p>
				<ul>
					<li><a href="' . WP2D_EXT_GH_ISSUES_NEW . '" target="_blank">' . esc_html__( 'Report a bug', 'wp-to-diaspora' ) . '</a>
					<li><a href="' . WP2D_EXT_GH_ISSUES_NEW . '" target="_blank">' . esc_html__( 'Suggest a new feature', 'wp-to-diaspora' ) . '</a>
					<li><a href="' . WP2D_EXT_I18N . '" target="_blank">' . esc_html__( 'Help with translations', 'wp-to-diaspora' ) . '</a>
					<li><a href="' . WP2D_EXT_DONATE . '" target="_blank">' . esc_html__( 'Make a donation', 'wp-to-diaspora' ) . '</a>
				</ul>',
		] );
	}

	/**
	 * Add help tabs to the contextual help on the post pages.
	 */
	private function add_post_type_help_tabs(): void {
		get_current_screen()?->add_help_tab( [
			'id'      => 'wp-to-diaspora',
			'title'   => esc_html__( 'WP to diaspora*', 'wp-to-diaspora' ),
			'content' => sprintf(
				'<p>' . esc_html_x(
					'For detailed information, refer to the contextual help on the %1$sWP to diaspora*%2$s settings page.',
					'Placeholders represent the link.',
					'wp-to-diaspora'
				) . '</p>',
				'<a href="' . esc_url( admin_url( 'options-general.php?page=wp_to_diaspora' ) ) . '" target="_blank">',
				'</a>'
			),
		] );
	}

	/**
	 * Get a link that directly opens a help tab via JS.
	 *
	 * @since 1.6.0
	 *
	 * @param WP_Error|string $error The WP_Error object with the tab id as data or the tab id itself.
	 *
	 * @return string HTML link.
	 */
	public static function get_help_tab_quick_link( WP_Error|string $error ): string {
		$help_tab = '';
		if ( is_wp_error( $error ) && ( $error_data = $error->get_error_data() ) && array_key_exists( 'help_tab', $error_data ) ) {
			$help_tab = $error_data['help_tab'];
		} elseif ( is_string( $error ) ) {
			$help_tab = $error;
		}
		if ( '' !== $help_tab ) {
			return sprintf(
				'<a href="#" class="open-help-tab" data-help-tab="%1$s">%2$s</a>',
				$help_tab,
				esc_html__( 'Help', 'wp-to-diaspora' )
			);
		}

		return '';
	}
}
