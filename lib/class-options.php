<?php
/**
 * Plugin Options.
 *
 * @package WP_To_Diaspora\Options
 * @since   1.3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class to manage the settings using the Settings API.
 */
class WP2D_Options {

	/**
	 * Only instance of this class.
	 *
	 * @var WP2D_Options
	 */
	private static $instance;

	/**
	 * All default plugin options.
	 *
	 * @var array
	 */
	private static $default_options = [
		'aspects_list'       => [],
		'services_list'      => [],
		'post_to_diaspora'   => true,
		'enabled_post_types' => [ 'post' ],
		'fullentrylink'      => true,
		'display'            => 'full',
		'tags_to_post'       => [ 'global', 'custom', 'post' ],
		'global_tags'        => '',
		'aspects'            => [ 'public' ],
		'services'           => [],
		'auth_key_hash'      => '',
		'version'            => WP2D_VERSION,
	];

	/**
	 * Valid values for select fields.
	 *
	 * @var array
	 */
	private static $valid_values = [
		'display'      => [ 'full', 'excerpt', 'none' ],
		'tags_to_post' => [ 'global', 'custom', 'post' ],
	];

	/**
	 * All plugin options.
	 *
	 * @var array
	 */
	private static $options;

	/** Singleton, keep private. */
	final private function __clone() {
	}

	/** Singleton, keep private. */
	final private function __construct() {
	}

	/**
	 * Create / Get the instance of this class.
	 *
	 * @return WP2D_Options Instance of this class.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Set up the options menu.
	 */
	private function setup() {

		// Populate options array.
		$this->get_option();

		// Setup Options page and Contextual Help.
		add_action( 'admin_menu', [ $this, 'setup_wpadmin_pages' ] );

		// Register all settings.
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}


	/**
	 * Get the currently selected tab.
	 *
	 * @todo Multi-level if statement to make it look prettier.
	 *
	 * @param string $default Tab to select if the current selection is invalid.
	 *
	 * @return string Return the currently selected tab.
	 */
	private function current_tab( $default = 'defaults' ) {
		$tab = ( isset ( $_GET['tab'] ) ? $_GET['tab'] : $default );

		// If the pod settings aren't configured yet, open the 'Setup' tab.
		if ( ! $this->is_pod_set_up() ) {
			$tab = 'setup';
		}

		return $tab;
	}

	/**
	 * Output all options tabs and return an array of them all, if requested by $return.
	 *
	 * @param bool $return Define if the options tabs should be returned.
	 *
	 * @return array (If requested) An array of the outputted options tabs.
	 */
	private function options_page_tabs( $return = false ) {
		// The array defining all options sections to be shown as tabs.
		$tabs = [];
		if ( $this->is_pod_set_up() ) {
			$tabs['defaults'] = __( 'Defaults', 'wp-to-diaspora' );
		}

		// Add the 'Setup' tab to the end of the list.
		$tabs['setup'] = __( 'Setup', 'wp-to-diaspora' ) . '<span id="pod-connection-status" class="dashicons-before hidden"></span><span class="spinner"></span>';

		// Container for all options tabs.
		$out = '<h2 id="options-tabs" class="nav-tab-wrapper">';
		foreach ( $tabs as $tab => $name ) {
			// The tab link.
			$out .= '<a class="nav-tab' . ( $this->current_tab() === $tab ? ' nav-tab-active' : '' ) . '" href="?page=wp_to_diaspora&tab=' . $tab . '">' . $name . '</a>';
		}
		$out .= '</h2>';

		// Output the container with all tabs.
		echo $out;

		// Check if the tabs should be returned.
		if ( $return ) {
			return $tabs;
		}

		return [];
	}


	/**
	 * Set up admin options page.
	 */
	public function admin_options_page() {
		?>
		<div class="wrap">
			<h2>WP to diaspora*</h2>

			<div id="wp2d-message" class="notice hidden" <?php echo defined( 'WP2D_DEBUGGING' ) ? ' data-debugging' : ''; ?>></div>

			<?php
			// Check the connection status to diaspora.
			if ( ! $this->is_pod_set_up() ) {
				add_settings_error(
					'wp_to_diaspora_settings',
					'wp_to_diaspora_connected',
					__( 'First of all, set up the connection to your pod below.', 'wp-to-diaspora' ),
					'updated'
				);
			} else {
				// Get initial aspects list and connected services.
				// DON'T check for empty services list here!!
				// It could always be empty, resulting in this code being run every time the page is loaded.
				// The aspects will at least have a "Public" entry after the initial fetch.
				$aspects_list = $this->get_option( 'aspects_list' );
				if ( ( $force = get_transient( 'wp2d_no_js_force_refetch' ) ) || empty( $aspects_list ) ) {

					// Set up the connection to diaspora*.
					$api = WP2D_Helpers::api_quick_connect();
					if ( ! $api->has_last_error() ) {
						// Get the loaded aspects.
						if ( is_array( $aspects = $api->get_aspects() ) ) {
							// Save the new list of aspects.
							$this->set_option( 'aspects_list', $aspects );
						}

						// Get the loaded services.
						if ( is_array( $services = $api->get_services() ) ) {
							// Save the new list of services.
							$this->set_option( 'services_list', $services );
						}

						$this->save();
					}

					if ( $force ) {
						delete_transient( 'wp2d_no_js_force_refetch' );
						$message = ( ! $api->has_last_error() ) ? __( 'Connection successful.', 'wp-to-diaspora' ) : $api->get_last_error();
						add_settings_error(
							'wp_to_diaspora_settings',
							'wp_to_diaspora_connected',
							$message,
							$api->has_last_error() ? 'error' : 'updated'
						);
					}
				}
			}

			// Output success or error message.
			settings_errors( 'wp_to_diaspora_settings' );
			?>

			<?php $page_tabs = array_keys( $this->options_page_tabs( true ) ); ?>

			<form action="<?php echo admin_url( 'options.php' ); ?>" method="post">
				<input id="wp2d_no_js" type="hidden" name="wp_to_diaspora_settings[no_js]" value="1">
				<?php
				// Load the settings fields.
				settings_fields( 'wp_to_diaspora_settings' );
				do_settings_sections( 'wp_to_diaspora_settings' );

				// Get the name of the current tab, if set, else take the first one from the list.
				$tab = $this->current_tab( $page_tabs[0] );

				// Add Save and Reset buttons.
				echo '<input id="submit-' . esc_attr( $tab ) . '" name="wp_to_diaspora_settings[submit_' . esc_attr( $tab ) . ']" type="submit" class="button-primary" value="' . esc_attr__( 'Save Changes' ) . '" />&nbsp;';
				if ( 'setup' !== $tab ) {
					echo '<input id="reset-' . esc_attr( $tab ) . '" name="wp_to_diaspora_settings[reset_' . esc_attr( $tab ) . ']" type="submit" class="button-secondary" value="' . esc_attr__( 'Reset Defaults', 'wp-to-diaspora' ) . '" />';
				}
				?>

			</form>
		</div>

		<?php
	}

	/**
	 * Return if the settings for the pod setup have been entered.
	 *
	 * @return bool If the setup for the pod has been done.
	 */
	public function is_pod_set_up() {
		return ( $this->get_option( 'pod' ) && $this->get_option( 'username' ) && $this->get_option( 'password' ) );
	}

	/**
	 * Setup Contextual Help and Options pages.
	 */
	public function setup_wpadmin_pages() {
		// Add options page.
		$hook = add_options_page( 'WP to diaspora*', 'WP to diaspora*', 'manage_options', 'wp_to_diaspora', [ $this, 'admin_options_page' ] );

		// Setup the contextual help menu after the options page has been loaded.
		add_action( 'load-' . $hook, [ 'WP2D_Contextual_Help', 'instance' ] );

		// Setup the contextual help menu tab for post types. Checks are made there!
		add_action( 'load-post.php', [ 'WP2D_Contextual_Help', 'instance' ] );
		add_action( 'load-post-new.php', [ 'WP2D_Contextual_Help', 'instance' ] );
	}

	/**
	 * Initialise the settings sections and fields of the currently selected tab.
	 */
	public function register_settings() {
		// Register the settings with validation callback.
		register_setting( 'wp_to_diaspora_settings', 'wp_to_diaspora_settings', [ $this, 'validate_settings' ] );

		// Load only the sections of the selected tab.
		switch ( $this->current_tab() ) {
			case 'defaults' :
				// Add a "Defaults" section that contains all posting settings to be used by default.
				add_settings_section( 'wp_to_diaspora_defaults_section', __( 'Posting Defaults', 'wp-to-diaspora' ), [ $this, 'defaults_section' ], 'wp_to_diaspora_settings' );
				break;
			case 'setup' :
				// Add a "Setup" section that contains the Pod domain, Username and Password.
				add_settings_section( 'wp_to_diaspora_setup_section', __( 'diaspora* Setup', 'wp-to-diaspora' ), [ $this, 'setup_section' ], 'wp_to_diaspora_settings' );
				break;
		}
	}


	/**
	 * Callback for the "Setup" section.
	 */
	public function setup_section() {
		esc_html_e( 'Set up the connection to your diaspora* account.', 'wp-to-diaspora' );

		// Pod entry field.
		add_settings_field( 'pod', __( 'Diaspora* Pod', 'wp-to-diaspora' ), [ $this, 'pod_render' ], 'wp_to_diaspora_settings', 'wp_to_diaspora_setup_section' );

		// Username entry field.
		add_settings_field( 'username', __( 'Username' ), [ $this, 'username_render' ], 'wp_to_diaspora_settings', 'wp_to_diaspora_setup_section' );

		// Password entry field.
		add_settings_field( 'password', __( 'Password' ), [ $this, 'password_render' ], 'wp_to_diaspora_settings', 'wp_to_diaspora_setup_section' );
	}

	/**
	 * Render the "Pod" field.
	 */
	public function pod_render() {
		// Update entries: curl -G 'https://the-federation.info/graphql?raw' --data-urlencode 'query={nodes(platform:"diaspora"){host}}' | jq '.data.nodes[].host'

		$pod_list = [
			'a.grumpy.world',
			'amiensdiaspora.fr',
			'azazel.ultragreen.net',
			'berlinspora.de',
			'blue.skye.cx',
			'bobspora.com',
			'brighton.social',
			'catpod.cat.scot',
			'community.sandtler.club',
			'd.aheapofseconds.com',
			'd.consumium.org',
			'd.kretschmann.social',
			'dame.life',
			'datataffel.dk',
			'deko.cloud',
			'despora.de',
			'dia.ax9.eu',
			'dia.gelse.eu',
			'dia.goexchange.de',
			'dia.gordons.gen.nz',
			'dia.sh',
			'dia.z-gears.com',
			'diapod.net',
			'diapod.org',
			'diasp.ca',
			'diasp.cz',
			'diasp.de',
			'diasp.eu',
			'diasp.eu.com',
			'diasp.in',
			'diasp.nl',
			'diasp.org',
			'diasp.pl',
			'diasp.sk',
			'diaspod.de',
			'diaspora-fr.org',
			'diaspora.1312.media',
			'diaspora.abendstille.at',
			'diaspora.animalnet.de',
			'diaspora.asrun.eu',
			'diaspora.baucum.me',
			'diaspora.boris.syncloud.it',
			'diaspora.by5.de',
			'diaspora.com.ar',
			'diaspora.conxtor.com',
			'diaspora.counterspin.org.nz',
			'diaspora.crossfamilyweb.com',
			'diaspora.cyber-tribal.com',
			'diaspora.digi-merc.org',
			'diaspora.ennder.fr',
			'diaspora.flidais.net',
			'diaspora.flyar.net',
			'diaspora.gegeweb.eu',
			'diaspora.gianforte.org',
			'diaspora.goethe12.de',
			'diaspora.goethe20.de',
			'diaspora.hofud.com',
			'diaspora.immae.eu',
			'diaspora.in.ua',
			'diaspora.kajalinifi.de',
			'diaspora.kapper.net',
			'diaspora.koehn.com',
			'diaspora.laka.lv',
			'diaspora.lebarjack.com',
			'diaspora.levity.nl',
			'diaspora.libertais.org',
			'diaspora.mathematicon.com',
			'diaspora.microdata.co.uk',
			'diaspora.mifritscher.de',
			'diaspora.monovs.com',
			'diaspora.normandie-libre.fr',
			'diaspora.otherreality.net',
			'diaspora.pades-asso.fr',
			'diaspora.permutationsofchaos.com',
			'diaspora.pingupod.de',
			'diaspora.psyco.fr',
			'diaspora.raven-ip.com',
			'diaspora.ruhrmail.de',
			'diaspora.sceal.ie',
			'diaspora.schoenf.de',
			'diaspora.skewed.de',
			'diaspora.snakenode.eu',
			'diaspora.softwarelivre.org',
			'diaspora.solusar.de',
			'diaspora.somethinghub.com',
			'diaspora.subsignal.org',
			'diaspora.the-penguin.de',
			'diaspora.thus.ch',
			'diaspora.totg.fr',
			'diaspora.town',
			'diaspora.tribblefamily.org',
			'diaspora.u4u.org',
			'diaspora.vrije-mens.org',
			'diaspora.walkingmountains.fr',
			'diaspora.weidestraat.nl',
			'diaspora.wfn.reisen',
			'diaspora.williamsonday.org',
			'diaspora.wollner.za.net',
			'diasporabr.com.br',
			'diasporabrazil.org',
			'diasporanederland.nl',
			'diasporapod.no',
			'diasporasocial.net',
			'diasporing.ch',
			'diaspote.org',
			'diaspsocial.com',
			'dorf-post.de',
			'dspr.geekshell.fr',
			'dspr.io',
			'ege.land',
			'espora.com.es',
			'expod.de',
			'failure.net',
			'fb.irc.org.my',
			'flokk.no',
			'framasphere.org',
			'freehuman.fr',
			'friendsmeet.win',
			'gesichtsbu.ch',
			'gnc.thepew.life',
			'hanksdiasporadevtestpod.com',
			'huyakhuyak.online',
			'iitians.xyz',
			'iliketoast.net',
			'ingtech.net',
			'jardin.umaneti.net',
			'jochem.name',
			'joindiaspora.com',
			'jons.gr',
			'kapok.se',
			'kentshikama.com',
			'kitsune.click',
			'lanx.fr',
			'libdi.net',
			'librenet.gr',
			'livepods.net',
			'lstoll-diaspora.herokuapp.com',
			'mindfeed.herokuapp.com',
			'mondiaspora.net',
			'my.wallmeier.info',
			'nerdpol.ch',
			'nota.404.mn',
			'pekospora.pekoyama.com',
			'phreedom.tk',
			'plusfriends.net',
			'pluspora.com',
			'pod.aevl.us',
			'pod.afox.me',
			'pod.alhague.net',
			'pod.asap-soft.com',
			'pod.athrandironline.com',
			'pod.beautifulmathuncensored.de',
			'pod.braitbart.net',
			'pod.brief.guru',
			'pod.buergi.lugs.ch',
			'pod.buffalopugs.org',
			'pod.chabotsi.fr',
			'pod.cyberdungeon.de',
			'pod.dapor.net',
			'pod.ddna.co',
			'pod.diaspora.la',
			'pod.diaspora.software',
			'pod.digitac.cc',
			'pod.dirkomatik.de',
			'pod.disroot.org',
			'pod.dukun.de',
			'pod.fab-l3.org',
			'pod.ferner-online.de',
			'pod.fulll.name',
			'pod.g3l.org',
			'pod.geraspora.de',
			'pod.gothic.net.au',
			'pod.haxxors.com',
			'pod.hoizi.net',
			'pod.interlin.nl',
			'pod.itabs.nl',
			'pod.jamidisi-edu.de',
			'pod.jeweet.net',
			'pod.jns.im',
			'pod.jpope.org',
			'pod.karlsborg.de',
			'pod.kneedrag.org',
			'pod.libreplanetbr.org',
			'pod.mesdonnees.ch',
			'pod.mttv.it',
			'pod.nomorestars.com',
			'pod.omgsrsly.net',
			'pod.orkz.net',
			'pod.pc-tiede.de',
			'pod.polichinel.nl',
			'pod.psynet.su',
			'pod.qarte.de',
			'pod.reckord.de',
			'pod.redfish.ca',
			'pod.sfunk1x.com',
			'pod.storel.li',
			'pod.tchncs.de',
			'pod.thing.org',
			'pod.thomasdalichow.de',
			'pod.togart.de',
			'pod.tsumi.it',
			'pod2.hanksdiasporadevtestpod.com',
			'podbay.net',
			'podricing.pw',
			'podverdorie.mrbim.nl',
			'protagio.social',
			'pubpod.alqualonde.org',
			'revreso.de',
			'rhizome-dev.ezpz.io',
			'rhizome.hfbk.net',
			'ruhrspora.de',
			'russiandiaspora.org',
			'sechat.org',
			'share.naturalnews.com',
			'shoosh.de',
			'shrekislove.us',
			'social.antisthenes.org',
			'social.cloudair.org',
			'social.cooleysekula.net',
			'social.daxbau.net',
			'social.elinux.org',
			'social.gibberfish.org',
			'social.hauspie.fr',
			'social.hiernaux.eu',
			'social.hugin-hosting.de',
			'social.mbuto.me',
			'social.mrzyx.de',
			'social.sumptuouscapital.com',
			'social.tethered-node.de',
			'social.tftsr.com',
			'sphere.libratoi.fr',
			'spyurk.am',
			'subvillage.de',
			'swecha.social',
			'sysad.org',
			'the-dank.com',
			'tippentappen.de',
			'titis.xyz',
			'treehouse.pub',
			'tyrell.marway.org',
			'vecinoscde.org',
			'verge.info.tm',
			'weare.boldlygoingnowhere.org',
			'webthinking.de',
			'wertier.de',
			'wgserv.eu',
			'whatsnewz.com',
			'wk3.org',
			'www.diasporaix.de',
			'www.geekbrigade.co.uk',
			'xn--b1aafeapdba0ada1es.xn--p1ai',
			'zxc.st',
		];
		?>
		https://<input type="text" name="wp_to_diaspora_settings[pod]" value="<?php echo esc_attr( $this->get_option( 'pod' ) ); ?>" placeholder="e.g. joindiaspora.com" autocomplete="on" list="pod-list" required>
		<datalist id="pod-list">
			<?php foreach ( $pod_list as $pod ) : ?>
				<option value="<?php echo esc_attr( $pod ); ?>"></option>
			<?php endforeach; ?>
		</datalist>
		<?php
	}

	/**
	 * Render the "Username" field.
	 */
	public function username_render() {
		?>
		<input type="text" name="wp_to_diaspora_settings[username]" value="<?php echo esc_attr( $this->get_option( 'username' ) ); ?>" placeholder="<?php esc_attr_e( 'Username' ); ?>" required>
		<?php
	}

	/**
	 * Render the "Password" field.
	 */
	public function password_render() {
		// Special case if we already have a password.
		$has_password = ( '' !== $this->get_option( 'password', '' ) );
		$placeholder  = $has_password ? __( 'Password already set.', 'wp-to-diaspora' ) : __( 'Password' );
		$required     = $has_password ? '' : ' required';
		?>
		<input type="password" name="wp_to_diaspora_settings[password]" value="" placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php echo esc_attr( $required ); ?>>
		<?php if ( $has_password ) : ?>
			<p class="description"><?php esc_html_e( 'If you would like to change the password type a new one. Otherwise leave this blank.', 'wp-to-diaspora' ); ?></p>
		<?php endif;
	}


	/**
	 * Callback for the "Defaults" section.
	 */
	public function defaults_section() {
		esc_html_e( 'Define the default posting behaviour for all posts here. These settings can be modified for each post individually, by changing the values in the "WP to diaspora*" meta box, which gets displayed in your post edit screen.', 'wp-to-diaspora' );

		// Post types field.
		add_settings_field( 'enabled_post_types', __( 'Post types', 'wp-to-diaspora' ), [ $this, 'post_types_render' ], 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section' );

		// Post to diaspora* checkbox.
		add_settings_field( 'post_to_diaspora', __( 'Post to diaspora*', 'wp-to-diaspora' ), [ $this, 'post_to_diaspora_render' ], 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'post_to_diaspora' ) );

		// Full entry link checkbox.
		add_settings_field( 'fullentrylink', __( 'Show "Posted at" link?', 'wp-to-diaspora' ), [ $this, 'fullentrylink_render' ], 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'fullentrylink' ) );

		// Full text, excerpt or none radio buttons.
		add_settings_field( 'display', __( 'Display', 'wp-to-diaspora' ), [ $this, 'display_render' ], 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'display' ) );

		// Tags to post dropdown.
		add_settings_field( 'tags_to_post', __( 'Tags to post', 'wp-to-diaspora' ), [ $this, 'tags_to_post_render' ], 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'tags_to_post', 'gc' ) );

		// Global tags field.
		add_settings_field( 'global_tags', __( 'Global tags', 'wp-to-diaspora' ), [ $this, 'global_tags_render' ], 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', $this->get_option( 'global_tags' ) );

		// Aspects checkboxes.
		add_settings_field( 'aspects', __( 'Aspects', 'wp-to-diaspora' ), [ $this, 'aspects_services_render' ], 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', [ 'aspects', $this->get_option( 'aspects' ) ] );

		// Services checkboxes.
		add_settings_field( 'services', __( 'Services', 'wp-to-diaspora' ), [ $this, 'aspects_services_render' ], 'wp_to_diaspora_settings', 'wp_to_diaspora_defaults_section', [ 'services', $this->get_option( 'services' ) ] );
	}

	/**
	 * Render the "Post types" checkboxes.
	 */
	public function post_types_render() {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		// Remove excluded post types from the list.
		$excluded_post_types = [ 'attachment', 'nav_menu_item', 'revision' ];
		foreach ( $excluded_post_types as $excluded ) {
			unset( $post_types[ $excluded ] );
		}
		?>

		<select id="enabled-post-types" multiple data-placeholder="<?php esc_attr_e( 'None', 'wp-to-diaspora' ); ?>" class="chosen" name="wp_to_diaspora_settings[enabled_post_types][]">
			<?php foreach ( $post_types as $post_type ) : ?>
				<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( in_array( $post_type->name, $this->get_option( 'enabled_post_types' ), true ) ); ?>><?php echo esc_html( $post_type->label ); ?></option>
			<?php endforeach; ?>
		</select>

		<p class="description"><?php esc_html_e( 'Choose which post types can be posted to diaspora*.', 'wp-to-diaspora' ); ?></p>

		<?php
	}

	/**
	 * Render the "Post to diaspora*" checkbox.
	 *
	 * @param bool $post_to_diaspora If this checkbox is checked or not.
	 */
	public function post_to_diaspora_render( $post_to_diaspora ) {
		$label = ( 'settings_page_wp_to_diaspora' === get_current_screen()->id ) ? __( 'Yes' ) : __( 'Post to diaspora*', 'wp-to-diaspora' );
		?>
		<label><input type="checkbox" id="post-to-diaspora" name="wp_to_diaspora_settings[post_to_diaspora]" value="1" <?php checked( $post_to_diaspora ); ?>><?php echo esc_html( $label ); ?></label>
		<?php
	}

	/**
	 * Render the "Show 'Posted at' link" checkbox.
	 *
	 * @param bool $show_link If the checkbox is checked or not.
	 */
	public function fullentrylink_render( $show_link ) {
		$description = __( 'Include a link back to your original post.', 'wp-to-diaspora' );
		$checkbox    = '<input type="checkbox" id="fullentrylink" name="wp_to_diaspora_settings[fullentrylink]" value="1"' . checked( $show_link, true, false ) . '>';

		if ( 'settings_page_wp_to_diaspora' === get_current_screen()->id ) : ?>
			<label><?php echo $checkbox; ?><?php esc_html_e( 'Yes' ); ?></label>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php else : ?>
			<label title="<?php echo esc_attr( $description ); ?>"><?php echo $checkbox; ?><?php esc_html_e( 'Show "Posted at" link?', 'wp-to-diaspora' ); ?></label>
		<?php endif;
	}

	/**
	 * Render the "Display" radio buttons.
	 *
	 * @param string $display The selected radio button.
	 */
	public function display_render( $display ) {
		?>
		<label><input type="radio" name="wp_to_diaspora_settings[display]" value="full" <?php checked( $display, 'full' ); ?>><?php esc_html_e( 'Full Post', 'wp-to-diaspora' ); ?></label><br/>
		<label><input type="radio" name="wp_to_diaspora_settings[display]" value="excerpt" <?php checked( $display, 'excerpt' ); ?>><?php esc_html_e( 'Excerpt' ); ?></label><br/>
		<label><input type="radio" name="wp_to_diaspora_settings[display]" value="none" <?php checked( $display, 'none' ); ?>><?php esc_html_e( 'None' ); ?></label>
		<?php
	}

	/**
	 * Render the "Tags to post" field.
	 *
	 * @param array $tags_to_post The types of tags to be posted.
	 */
	public function tags_to_post_render( $tags_to_post ) {
		$on_settings_page = ( 'settings_page_wp_to_diaspora' === get_current_screen()->id );
		$description      = esc_html__( 'Choose which tags should be posted to diaspora*.', 'wp-to-diaspora' );

		if ( ! $on_settings_page ) {
			echo '<label>' . esc_html( $description );
		}

		?>
		<select id="tags-to-post" multiple data-placeholder="<?php esc_attr_e( 'No tags', 'wp-to-diaspora' ); ?>" class="chosen" name="wp_to_diaspora_settings[tags_to_post][]">
			<option value="global" <?php selected( in_array( 'global', $tags_to_post, true ) ); ?>><?php esc_html_e( 'Global tags', 'wp-to-diaspora' ); ?></option>
			<option value="custom" <?php selected( in_array( 'custom', $tags_to_post, true ) ); ?>><?php esc_html_e( 'Custom tags', 'wp-to-diaspora' ); ?></option>
			<option value="post" <?php selected( in_array( 'post', $tags_to_post, true ) ); ?>><?php esc_html_e( 'Post tags', 'wp-to-diaspora' ); ?></option>
		</select>

		<?php if ( $on_settings_page ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php else : ?>
			</label>
		<?php endif;
	}

	/**
	 * Render the "Global tags" field.
	 *
	 * @param array $tags The global tags to be posted.
	 */
	public function global_tags_render( $tags ) {
		WP2D_Helpers::arr_to_str( $tags );
		?>
		<input type="text" class="regular-text wp2d-tags" name="wp_to_diaspora_settings[global_tags]" value="<?php echo esc_attr( $tags ); ?>" placeholder="<?php esc_attr_e( 'Global tags', 'wp-to-diaspora' ); ?>">
		<p class="description"><?php esc_html_e( 'Custom tags to add to all posts being posted to diaspora*.', 'wp-to-diaspora' ); ?></p>
		<?php
	}

	/**
	 * Render the "Custom tags" field.
	 *
	 * @param array $tags The custom tags to be posted.
	 */
	public function custom_tags_render( $tags ) {
		WP2D_Helpers::arr_to_str( $tags );
		?>
		<label title="<?php esc_attr_e( 'Custom tags to add to this post when it\'s posted to diaspora*.', 'wp-to-diaspora' ); ?>">
			<?php esc_html_e( 'Custom tags', 'wp-to-diaspora' ); ?>
			<input type="text" class="widefat wp2d-tags" name="wp_to_diaspora_settings[custom_tags]" value="<?php echo esc_attr( $tags ); ?>">
		</label>
		<p class="description"><?php esc_html_e( 'Separate tags with commas' ); ?></p>
		<?php
	}

	/**
	 * Render the "Aspects" and "Services" checkboxes.
	 *
	 * @param array $args Array containing the type and items to output as checkboxes.
	 */
	public function aspects_services_render( $args ) {
		list( $type, $items ) = $args;

		// This is where the 2 types show their differences.
		switch ( $type ) {
			case 'aspects':
				$refresh_button = __( 'Refresh Aspects', 'wp-to-diaspora' );
				$description    = esc_html__( 'Choose which aspects to share to.', 'wp-to-diaspora' );
				$empty_label    = '<input type="checkbox" name="wp_to_diaspora_settings[aspects][]" value="public" checked="checked">' . esc_html__( 'Public' );
				break;

			case 'services':
				$refresh_button = __( 'Refresh Services', 'wp-to-diaspora' );
				$description    = sprintf(
					'%1$s<br><a href="%2$s" target="_blank">%3$s</a>',
					esc_html__( 'Choose which services to share to.', 'wp-to-diaspora' ),
					esc_url( 'https://' . $this->get_option( 'pod' ) . '/services' ),
					esc_html__( 'Show available services on my pod.', 'wp-to-diaspora' )
				);
				$empty_label    = esc_html__( 'No services connected yet.', 'wp-to-diaspora' );
				break;

			default:
				return;
		}

		$items = array_filter( (array) $items ) ?: [];

		// Special case for this field if it's displayed on the settings page.
		$on_settings_page = ( 'settings_page_wp_to_diaspora' === get_current_screen()->id );

		if ( ! $on_settings_page ) {
			echo $description;
			$description = '';
		}

		?>
		<div id="<?php echo esc_attr( $type ); ?>-container" data-<?php echo esc_attr( $type ); ?>-selected="<?php echo esc_attr( implode( ',', $items ) ); ?>">
			<?php if ( $list = (array) $this->get_option( $type . '_list' ) ) : ?>
				<?php foreach ( $list as $id => $name ) : ?>
					<label><input type="checkbox" name="wp_to_diaspora_settings[<?php echo esc_attr( $type ); ?>][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $items ) ); ?>><?php echo esc_html( $name ); ?></label>
				<?php endforeach; ?>
			<?php else : ?>
				<label><?php echo $empty_label; ?></label>
			<?php endif; ?>
		</div>
		<p class="description">
			<?php echo $description; ?>
			<a id="refresh-<?php echo esc_attr( $type ); ?>-list" class="button hide-if-no-js"><?php echo esc_html( $refresh_button ); ?></a>
			<span class="spinner"></span>
			<span class="hide-if-js"><?php printf( esc_html_x( 'To update this list, %sre-save your login info%s.', 'placeholders are link tags to the settings page.', 'wp-to-diaspora' ), '<a href="' . admin_url( 'options-general.php?page=wp_to_diaspora' ) . '&amp;tab=setup" target="_blank">', '</a>' ); ?></span>
		</p>
		<?php
	}


	/**
	 * Get a specific option.
	 *
	 * @param null|string $option  ID of option to get.
	 * @param mixed       $default Override default value if option not found.
	 *
	 * @return mixed Requested option value.
	 */
	public function get_option( $option = null, $default = null ) {
		if ( null === self::$options ) {
			self::$options = get_option( 'wp_to_diaspora_settings', self::$default_options );
		}
		if ( null !== $option ) {
			if ( isset( self::$options[ $option ] ) ) {
				// Return found option value.
				return self::$options[ $option ];
			} elseif ( null !== $default ) {
				// Return overridden default value.
				return $default;
			} elseif ( isset( self::$default_options[ $option ] ) ) {
				// Return default option value.
				return self::$default_options[ $option ];
			}
		}

		return $default;
	}

	/**
	 * Get all options.
	 *
	 * @return array All the options.
	 */
	public function get_options() {
		return self::$options;
	}

	/**
	 * Set a certain option.
	 *
	 * @param string       $option ID of option to get.
	 * @param array|string $value  Value to be set for the passed option.
	 * @param bool         $save   Save the options immediately after setting them.
	 */
	public function set_option( $option, $value, $save = false ) {
		if ( null !== $option ) {
			if ( null !== $value ) {
				self::$options[ $option ] = $value;
			} else {
				unset( self::$options[ $option ] );
			}
		}

		$save && $this->save();
	}

	/**
	 * Save the options.
	 */
	public function save() {
		update_option( 'wp_to_diaspora_settings', self::$options );
	}

	/**
	 * Get all valid input values for the passed field.
	 *
	 * @param string $field Field to get the valid values for.
	 *
	 * @return array List of valid values.
	 */
	public function get_valid_values( $field ) {
		if ( array_key_exists( $field, self::$valid_values ) ) {
			return self::$valid_values[ $field ];
		}
	}

	/**
	 * Check if a value is valid for the passed field.
	 *
	 * @param string $field Field to check the valid value for.
	 * @param mixed  $value Value to check validity.
	 *
	 * @return bool If the passed value is valid.
	 */
	public function is_valid_value( $field, $value ) {
		if ( $valids = $this->get_valid_values( $field ) ) {
			return in_array( $value, $valids, true );
		}

		return false;
	}

	/**
	 * Attempt to upgrade the password from using AUTH_KEY to using WP2D_AUTH_KEY.
	 *
	 * @since 2.2.0
	 *
	 * @param bool $save
	 */
	public function attempt_password_upgrade( $save = false ) {
		if ( AUTH_KEY !== WP2D_ENC_KEY ) {
			$old_pw = WP2D_Helpers::decrypt( (string) $this->get_option( 'password' ), AUTH_KEY );
			if ( $old_pw !== null ) {
				$new_pw = WP2D_Helpers::encrypt( $old_pw, WP2D_ENC_KEY );
				$this->set_option( 'password', $new_pw, $save );
			}
		}
	}

	/**
	 * Validate all settings.
	 *
	 * @param array $input RAW input values.
	 *
	 * @return array Validated input values.
	 */
	public function validate_settings( $input ) {
		/* Validate all settings before saving to the database. */

		// Saving the pod setup details.
		if ( isset( $input['submit_setup'] ) ) {
			$input['pod']      = trim( sanitize_text_field( $input['pod'] ), ' /' );
			$input['username'] = sanitize_text_field( $input['username'] );
			$input['password'] = sanitize_text_field( $input['password'] );

			// If password is blank, it hasn't been changed.
			// If new password is equal to the encrypted password already saved, it was just passed again. It happens everytime update_option('wp_to_diaspora_settings') is called.
			if ( '' === $input['password'] || $this->get_option( 'password' ) === $input['password'] ) {
				// Attempt a password upgrade if applicable.
				$this->attempt_password_upgrade( true );
				$input['password'] = $this->get_option( 'password' );
			} else {
				$input['password'] = WP2D_Helpers::encrypt( $input['password'] );
			}

			// Keep a note of the current AUTH_KEY.
			$this->set_option( 'auth_key_hash', md5( AUTH_KEY ), true );

			// This is for when JS in not enabled, to make sure that the aspects and services
			// are re-fetched when displaying the options page after saving.
			if ( isset( $input['no_js'] ) ) {
				set_transient( 'wp2d_no_js_force_refetch', true );
			}
		}

		// Saving the default options.
		if ( isset( $input['submit_defaults'] ) ) {
			if ( ! isset( $input['enabled_post_types'] ) ) {
				$input['enabled_post_types'] = [];
			}

			// Checkboxes.
			$this->validate_checkboxes( [ 'post_to_diaspora', 'fullentrylink' ], $input );

			// Single Selects.
			$this->validate_single_selects( 'display', $input );

			// Multiple Selects.
			$this->validate_multi_selects( 'tags_to_post', $input );

			// Get unique, non-empty, trimmed tags and clean them up.
			$this->validate_tags( $input['global_tags'] );

			// Clean up the list of aspects. If the list is empty, only use the 'Public' aspect.
			$this->validate_aspects_services( $input['aspects'], [ 'public' ] );

			// Clean up the list of services.
			$this->validate_aspects_services( $input['services'] );
		}

		// Reset to defaults.
		if ( isset( $input['reset_defaults'] ) ) {
			// Set the input to the default options.
			$input = self::$default_options;

			// Don't reset the fetched lists of aspects and services.
			unset( $input['pod_list'], $input['aspects_list'], $input['services_list'] );
		}

		// Unset all unused input fields.
		unset( $input['submit_defaults'], $input['reset_defaults'], $input['submit_setup'] );

		// Parse inputs with default options and return.
		return wp_parse_args( $input, array_merge( self::$default_options, self::$options ) );
	}

	/**
	 * Validate checkboxes, make them either true or false.
	 *
	 * @param string|array $checkboxes Checkboxes to validate.
	 * @param array        $options    Options values themselves.
	 *
	 * @return array The validated options.
	 */
	public function validate_checkboxes( $checkboxes, &$options ) {
		foreach ( WP2D_Helpers::str_to_arr( $checkboxes ) as $checkbox ) {
			$options[ $checkbox ] = isset( $options[ $checkbox ] );
		}

		return $options;
	}

	/**
	 * Validate single-select fields and make sure their selected value are valid.
	 *
	 * @param string|array $selects Name(s) of the select fields.
	 * @param array        $options Options values themselves.
	 *
	 * @return array The validated options.
	 */
	public function validate_single_selects( $selects, &$options ) {
		foreach ( WP2D_Helpers::str_to_arr( $selects ) as $select ) {
			if ( isset( $options[ $select ] ) && ! $this->is_valid_value( $select, $options[ $select ] ) ) {
				unset( $options[ $select ] );
			}
		}

		return $options;
	}

	/**
	 * Validate multi-select fields and make sure their selected values are valid.
	 *
	 * @param string|array $selects Name(s) of the select fields.
	 * @param array        $options Options values themselves.
	 *
	 * @return array The validated options.
	 */
	public function validate_multi_selects( $selects, &$options ) {
		foreach ( WP2D_Helpers::str_to_arr( $selects ) as $select ) {
			if ( isset( $options[ $select ] ) ) {
				foreach ( (array) $options[ $select ] as $option_value ) {
					if ( ! $this->is_valid_value( $select, $option_value ) ) {
						unset( $options[ $select ] );
						break;
					}
				}
			} else {
				$options[ $select ] = [];
			}
		}

		return $options;
	}

	/**
	 * Clean up the passed tags. Keep only alphanumeric, hyphen and underscore characters.
	 *
	 * @param array|string $tags Tags to be cleaned as array or comma seperated values.
	 *
	 * @return array The cleaned tags.
	 */
	public function validate_tags( &$tags ) {
		WP2D_Helpers::str_to_arr( $tags );

		$tags = array_map( [ $this, 'validate_tag' ],
			array_unique(
				array_filter( $tags, 'trim' )
			)
		);

		return $tags;
	}

	/**
	 * Clean up the passed tag. Keep only alphanumeric, hyphen and underscore characters.
	 *
	 * @todo What about eastern characters? (chinese, indian, etc.)
	 *
	 * @param string $tag Tag to be cleaned.
	 *
	 * @return string The clean tag.
	 */
	public function validate_tag( &$tag ) {
		$tag = preg_replace( '/[^\w $\-]/u', '', str_replace( ' ', '-', trim( $tag ) ) );

		return $tag;
	}

	/**
	 * Validate the passed aspects or services.
	 *
	 * @param array $aspects_services List of aspects or services that need to be validated.
	 * @param array $default          Default value if not valid.
	 *
	 * @return array The validated list of aspects or services.
	 */
	public function validate_aspects_services( &$aspects_services, array $default = [] ) {
		if ( empty( $aspects_services ) || ! is_array( $aspects_services ) ) {
			$aspects_services = $default;
		} else {
			array_walk( $aspects_services, 'sanitize_text_field' );
		}

		return $aspects_services;
	}
}
