<?php
/*
 * Plugin Name: Authy for WordPress
 * Plugin URI: http://www.ethitter.com/plugins/authy-for-wordpress/
 * Description: Add <a href="http://www.authy.com/">Authy</a> two-factor authentication to WordPress. Users opt in for an added level of security that relies on random codes from their mobile devices.
 * Author: Erick Hitter
 * Version: 0.1
 * Author URI: http://www.ethitter.com/
 * License: GPL2+
 * Text Domain: authy_for_wp

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
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Authy_WP {
	/**
	 * Class variables
	 */
	// Oh look, a singleton
	private static $__instance = null;

	// Some plugin info
	protected $name = 'Authy for WordPress';

	// Parsed settings
	private $settings = null;

	// Is API ready, should plugin act?
	protected $ready = false;

	// Authy API
	protected $api = null;
	protected $api_key = null;
	protected $api_endpoint = null;

	// Interface keys
	protected $settings_page = 'authy-for-wp';
	protected $users_page = 'authy-for-wp-user';

	// Data storage keys
	protected $settings_key = 'authy_for_wp';
	protected $users_key = 'authy_for_wp_user';

	// Settings field placeholders
	protected $settings_fields = array();

	protected $settings_field_defaults = array(
		'label'    => null,
		'type'     => 'text',
		'sanitizer' => 'sanitize_text_field',
		'section'  => 'default',
		'class'    => null
	);

	// Default Authy data
	protected $user_defaults = array(
		'email'        => null,
		'phone'        => null,
		'country_code' => '+1',
		'authy_id'     => null
	);

	/**
	 * Singleton implementation
	 *
	 * @uses this::setup
	 * @return object
	 */
	public static function instance() {
		if ( ! is_a( self::$__instance, 'Authy_WP' ) ) {
			self::$__instance = new Authy_WP;
			self::$__instance->setup();
		}

		return self::$__instance;
	}

	/**
	 * Silence is golden.
	 */
	private function __construct() {}

	/**
	 * Plugin setup
	 *
	 * @uses this::register_settings_fields, this::prepare_api, add_action, add_filter
	 * @return null
	 */
	private function setup() {
		require( 'authy-wp-api.php' );

		$this->register_settings_fields();
		$this->prepare_api();

		// Plugin settings
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );

		add_filter( 'plugin_action_links', array( $this, 'filter_plugin_action_links' ), 10, 2 );

		// Anything other than plugin configuration belongs in here.
		if ( $this->ready ) {
			// User settings
			add_action( 'show_user_profile', array( $this, 'action_show_user_profile' ) );
			add_action( 'edit_user_profile', array( $this, 'action_edit_user_profile' ) );
			add_action( 'wp_ajax_' . $this->users_page, array( $this, 'ajax_get_id' ) );

			add_action( 'personal_options_update', array( $this, 'action_personal_options_update' ) );
			add_action( 'edit_user_profile_update', array( $this, 'action_edit_user_profile_update' ) );

			// Authentication
			add_filter( 'authenticate', array( $this, 'authenticate_user'), 10, 3);
		}
	}

	/**
	 * Add settings fields for main plugin page
	 *
	 * @uses __
	 * @return null
	 */
	protected function register_settings_fields() {
		$this->settings_fields = array(
			array(
				'name'      => 'api_key_production',
				'label'     => __( 'Production API Key', 'authy_wp' ),
				'type'      => 'text',
				'sanitizer' => 'alphanumeric'
			)
		);
	}

	/**
	 * Set class variables regarding API
	 * Instantiates the Authy API class into $this->api
	 *
	 * @uses this::get_setting, Authy_WP_API::instance
	 */
	protected function prepare_api() {
		$endpoints = array(
			'production'  => 'https://api.authy.com'
		);

		$api_key = $this->get_setting( 'api_key_production');

		// Only prepare the API endpoint if we have all information needed.
		if ( $api_key && isset( $endpoints['production'] ) ) {
			$this->api_key = $api_key;
			$this->api_endpoint = $endpoints[ 'production' ];

			$this->ready = true;
		}

		// Instantiate the API class
		$this->api = Authy_WP_API::instance( $this->api_key, $this->api_endpoint );
	}

	/**
	 * COMMON PLUGIN ELEMENTS
	 */

	/**
	 * Register plugin's setting and validation callback
	 *
	 * @param action admin_init
	 * @uses register_setting
	 * @return null
	 */
	public function action_admin_init() {
		register_setting( $this->settings_page, $this->settings_key, array( $this, 'validate_plugin_settings' ) );
	}

	/**
	 * Register plugin settings page and page's sections
	 *
	 * @uses add_options_page, add_settings_section
	 * @action admin_menu
	 * @return null
	 */
	public function action_admin_menu() {
		add_options_page( $this->name, 'Authy for WP', 'manage_options', $this->settings_page, array( $this, 'plugin_settings_page' ) );
		add_settings_section( 'default', '', array( $this, 'register_settings_page_sections' ), $this->settings_page );
	}

	/**
	 * Enqueue admin script for connection modal
	 *
	 * @uses get_current_screen, wp_enqueue_script, plugins_url, wp_localize_script, this::get_ajax_url, wp_enqueue_style
	 * @action admin_enqueue_scripts
	 * @return null
	 */
	public function action_admin_enqueue_scripts() {
		if ( ! $this->ready )
			return;

		$current_screen = get_current_screen();

		if ( 'profile' == $current_screen->base ) {
			wp_enqueue_script( 'authy-wp-profile', plugins_url( 'assets/authy-wp-profile.js', __FILE__ ), array( 'jquery', 'thickbox' ), 1.01, true );
			wp_localize_script( 'authy-wp-profile', 'AuthyForWP', array(
				'ajax' => $this->get_ajax_url(),
				'th_text' => __( 'Connection', 'authy_for_wp' ),
				'button_text' => __( 'Manage Authy Connection', 'authy_for_wp' )
			) );

			wp_enqueue_style( 'thickbox' );
		}
	}

	/**
	 * Add settings link to plugin row actions
	 *
	 * @param array $links
	 * @param string $plugin_file
	 * @uses menu_page_url, __
	 * @filter plugin_action_links
	 * @return array
	 */
	public function filter_plugin_action_links( $links, $plugin_file ) {
		if ( false !== strpos( $plugin_file, pathinfo( __FILE__, PATHINFO_FILENAME ) ) )
			$links['settings'] = '<a href="' . menu_page_url( $this->settings_page, false ) . '">' . __( 'Settings', 'authy_for_wp' ) . '</a>';

		return $links;
	}

	/**
	 * Retrieve a plugin setting
	 *
	 * @param string $key
	 * @uses get_option, wp_parse_args, apply_filters
	 * @return array or false
	 */
	public function get_setting( $key ) {
		$value = false;

		if ( is_null( $this->settings ) || ! is_array( $this->settings ) ) {
			$this->settings = get_option( $this->settings_key );
			$this->settings = wp_parse_args( $this->settings, array(
				'api_key_production'  => '',
				'api_key_development' => '',
				'environment'         => apply_filters( 'authy_wp_environment', 'production' )
			) );
		}

		if ( isset( $this->settings[ $key ] ) )
			$value = $this->settings[ $key ];

		return $value;
	}

	/**
	 * Build Ajax URL for users' connection management
	 *
	 * @uses add_query_arg, wp_create_nonce, admin_url
	 * @return string
	 */
	protected function get_ajax_url() {
		return add_query_arg( array(
			'action' => $this->users_page,
			'nonce' => wp_create_nonce( $this->users_key . '_ajax' )
		), admin_url( 'admin-ajax.php' ) );
	}

	/**
	 * GENERAL OPTIONS PAGE
	 */

	/**
	 * Populate settings page's sections
	 *
	 * @uses wp_parse_args, add_settings_field
	 * @return null
	 */
	public function register_settings_page_sections() {
		foreach ( $this->settings_fields as $args ) {
			$args = wp_parse_args( $args, $this->settings_field_defaults );

			add_settings_field( $args['name'], $args['label'], array( $this, 'form_field_' . $args['type'] ), $this->settings_page, $args['section'], $args );
		}
	}

	/**
	 * Render text input
	 *
	 * @param array $args
	 * @uses wp_parse_args, esc_attr, this::get_setting, esc_attr
	 * @return string or null
	 */
	public function form_field_text( $args ) {
		$args = wp_parse_args( $args, $this->settings_field_defaults );

		$name = esc_attr( $args['name'] );
		if ( empty( $name ) )
			return;

		if ( is_null( $args['class'] ) )
			$args['class'] = 'regular-text';

		$value = $this->get_setting( $args['name'] );

		?><input type="text" name="<?php echo esc_attr( $this->settings_key ); ?>[<?php echo $name; ?>]" class="<?php echo esc_attr( $args['class'] ); ?>" id="field-<?php echo $name; ?>" value="<?php echo esc_attr( $value ); ?>" /><?php
	}

	/**
	 * Render settings page
	 *
	 * @uses screen_icon, esc_html, get_admin_page_title, settings_fields, do_settings_sections, submit_button
	 * @return string
	 */
	public function plugin_settings_page() {
		$plugin_name = esc_html( get_admin_page_title() );
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php echo $plugin_name; ?></h2>

			<?php if ( $this->ready ) : ?>
				<p><?php _e( "With API keys entered, your users can now enable Authy on their individual accounts by visting their user profile pages.", 'authy_for_wp' ); ?></p>
			<?php else : ?>
				<p><?php printf( __( 'To use the Authy service, you must register an account at <a href="%1$s"><strong>%1$s</strong></a> and create an application for access to the Authy API.', 'authy_for_wp' ), 'http://www.authy.com/' ); ?></p>
				<p><?php _e( "Once you've created your application, enter your API keys in the fields below.", 'authy_for_wp' ); ?></p>
				<p><?php printf( __( "Until your API keys are entered, the %s plugin cannot function.", 'authy_for_wp' ), $plugin_name ); ?></p>
			<?php endif; ?>

			<form action="options.php" method="post">

				<?php settings_fields( $this->settings_page ); ?>

				<?php do_settings_sections( $this->settings_page ); ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Validate plugin settings
	 *
	 * @param array $settings
	 * @uses check_admin_referer, wp_parse_args, sanitize_text_field
	 * @return array
	 */
	public function validate_plugin_settings( $settings ) {
		check_admin_referer( $this->settings_page . '-options' );

		$settings_validated = array();

		foreach ( $this->settings_fields as $field ) {
			$field = wp_parse_args( $field, $this->settings_field_defaults );

			if ( ! isset( $settings[ $field['name'] ] ) )
				continue;

			switch ( $field['type'] ) {
				case 'text' :
						switch ( $field['sanitizer'] ) {
							case 'alphanumeric' :
								$value = preg_replace( '#[^a-z0-9]#i', '', $settings[ $field['name' ] ] );
								break;

							default:
							case 'sanitize_text_field' :
								$value = sanitize_text_field( $settings[ $field['name'] ] );
								break;
						}
					break;

				default:
					$value = sanitize_text_field( $settings[ $field['name'] ] );
					break;
			}

			if ( isset( $value ) && ! empty( $value ) )
				$settings_validated[ $field['name'] ] = $value;
		}

		return $settings_validated;
	}

	/**
	 * USER INFORMATION FUNCTIONS
	 */

	/**
	 * Add Authy data to a given user account
	 *
	 * @param int $user_id
	 * @param string $email
	 * @param string $phone
	 * @param string $country_code
	 * @uses this::user_has_authy_id, this::api::get_id, wp_parse_args, this::clear_authy_data, get_user_meta, update_user_meta
	 * @return null
	 */
	public function set_authy_data( $user_id, $email, $phone, $country_code ) {
		// Retrieve user's existing Authy ID, or get one from Authy
		if ( $this->user_has_authy_id( $user_id ) ) {
			$authy_id = $this->get_user_authy_id( $user_id );
		} else {
			// Request an Authy ID with given user information
			$authy_id = (int) $this->api->get_id( $email, $phone, $country_code );

			if ( ! $authy_id )
				unset( $authy_id );
		}

		// Build array of Authy data
		$data_sanitized = array(
			'email'        => $email,
			'phone'        => $phone,
			'country_code' => $country_code
		);

		if ( isset( $authy_id ) )
			$data_sanitized['authy_id'] = $authy_id;

		$data_sanitized = wp_parse_args( $data_sanitized, $this->user_defaults );

		// Update Authy data if sufficient information is provided, otherwise clear the option out.
		if ( empty( $data_sanitized['phone'] ) ) {
			$this->clear_authy_data( $user_id );
		} else {
			$data = get_user_meta( $user_id, $this->users_key, true );
			if ( ! is_array( $data ) )
				$data = array();

			$data[ $this->api_key ] = $data_sanitized;

			update_user_meta( $user_id, $this->users_key, $data );
		}
	}

	/**
	 * Retrieve a user's Authy data for a given API key
	 *
	 * @param int $user_id
	 * @param string $api_key
	 * @uses get_user_meta, wp_parse_args
	 * @return array
	 */
	protected function get_authy_data( $user_id, $api_key = null ) {
		// Bail without a valid user ID
		if ( ! $user_id )
			return $this->user_defaults;

		// Validate API key
		if ( is_null( $api_key ) )
			$api_key = $this->api_key;
		else
			$api_key = preg_replace( '#[a-z0-9]#i', '', $api_key );

		// Get meta, which holds all Authy data by API key
		$data = get_user_meta( $user_id, $this->users_key, true );
		if ( ! is_array( $data ) )
			$data = array();

		// Return data for this API, if present, otherwise return default data
		if ( array_key_exists( $api_key, $data ) )
			return wp_parse_args( $data[ $api_key ], $this->user_defaults );

		return $this->user_defaults;
	}

	/**
	 * Delete any stored Authy connections for the given user.
	 * Expected usage is somewhere where clearing is the known action.
	 *
	 * @param int $user_id
	 * @uses delete_user_meta
	 * @return null
	 */
	protected function clear_authy_data( $user_id ) {
		delete_user_meta( $user_id, $this->users_key );
	}

	/**
	 * Check if a given user has an Authy ID set
	 *
	 * @param int $user_id
	 * @uses this::get_user_authy_id
	 * @return bool
	 */
	protected function user_has_authy_id( $user_id ) {
		return (bool) $this->get_user_authy_id( $user_id );
	}

	/**
	 * Retrieve a given user's Authy ID
	 *
	 * @param int $user_id
	 * @uses this::get_authy_data
	 * @return int|bool
	 */
	protected function get_user_authy_id( $user_id ) {
		$data = $this->get_authy_data( $user_id );

		if ( is_array( $data ) && is_numeric( $data['authy_id'] ) )
			return (int) $data['authy_id'];

		return false;
	}


	/**
	 * USER SETTINGS PAGES
	 */

	/**
	 * Non-JS connection interface
	 *
	 * @param object $user
	 * @uses this::get_authy_data, esc_attr,
	 */
	public function action_show_user_profile( $user ) {
		$meta = $this->get_authy_data( $user->ID );
	?>
		<h3><?php echo esc_html( $this->name ); ?></h3>

		<table class="form-table" id="<?php echo esc_attr( $this->users_key ); ?>">
			<?php if ( $this->user_has_authy_id( $user->ID ) ) : ?>
				<tr>
					<th><label for="<?php echo esc_attr( $this->users_key ); ?>_disable"><?php _e( 'Disable your Authy connection?', 'authy_for_wp' ); ?></label></th>
					<td>
						<input type="checkbox" id="<?php echo esc_attr( $this->users_key ); ?>_disable" name="<?php echo esc_attr( $this->users_key ); ?>[disable_own]" value="1" />
						<label for="<?php echo esc_attr( $this->users_key ); ?>_disable"><?php _e( 'Yes, disable Authy for your account.', 'authy_for_wp' ); ?></label>

						<?php wp_nonce_field( $this->users_key . 'disable_own', $this->users_key . '[nonce]' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<tr>
					<th><label for="phone"><?php _e( 'Mobile number', 'authy_for_wp' ); ?></label></th>
					<td>
						<input type="tel" class="regular-text" name="<?php echo esc_attr( $this->users_key ); ?>[phone]" value="<?php echo esc_attr( $meta['phone'] ); ?>" />

						<?php wp_nonce_field( $this->users_key . 'edit_own', $this->users_key . '[nonce]' ); ?>
					</td>
				</tr>

				<tr>
					<th><label for="phone"><?php _e( 'Country code', 'authy_for_wp' ); ?></label></th>
					<td>
						<input type="text" class="small-text" name="<?php echo esc_attr( $this->users_key ); ?>[country_code]" value="<?php echo esc_attr( $meta['country_code'] ); ?>" />
					</td>
				</tr>
			<?php endif; ?>
		</table>

	<?php
	}

	/**
	 * Handle non-JS changes to users' own connection
	 *
	 * @param int $user_id
	 * @uses check_admin_referer, wp_verify_nonce, get_userdata, is_wp_error, this::set_authy_data, this::clear_authy_data,
	 * @return null
	 */
	public function action_personal_options_update( $user_id ) {
		check_admin_referer( 'update-user_' . $user_id );

		// Check if we have data to work with
		$authy_data = isset( $_POST[ $this->users_key ] ) ? $_POST[ $this->users_key ] : false;

		// Parse for nonce and API existence
		if ( is_array( $authy_data ) && array_key_exists( 'nonce', $authy_data ) ) {
			if ( wp_verify_nonce( $authy_data['nonce'], $this->users_key . 'edit_own' ) ) {
				// Email address
				$userdata = get_userdata( $user_id );
				if ( is_object( $userdata ) && ! is_wp_error( $userdata ) )
					$email = $userdata->data->user_email;
				else
					$email = null;

				// Phone number
				$phone = preg_replace( '#[^\d]#', '', $authy_data['phone'] );
				$country_code = preg_replace( '#[^\d\+]#', '', $authy_data['country_code'] );

				// Process information with Authy
				$this->set_authy_data( $user_id, $email, $phone, $country_code );
			} elseif ( wp_verify_nonce( $authy_data['nonce'], $this->users_key . 'disable_own' ) ) {
				// Delete Authy usermeta if requested
				if ( isset( $authy_data['disable_own'] ) )
					$this->clear_authy_data( $user_id );
			}
		}
	}

	/**
	 * Allow sufficiently-priviledged users to disable another user's Authy service.
	 *
	 * @param object $user
	 * @uses current_user_can, this::user_has_authy_id, get_user_meta, wp_parse_args, esc_attr, wp_nonce_field
	 * @action edit_user_profile
	 * @return string
	 */
	public function action_edit_user_profile( $user ) {
		if ( current_user_can( 'create_users' ) ) {
		?>
			<h3>Authy Two-factor Authentication</h3>

			<table class="form-table">
				<?php if ( $this->user_has_authy_id( $user->ID ) ) :
					$meta = get_user_meta( get_current_user_id(), $this->users_key, true );
					$meta = wp_parse_args( $meta, $this->user_defaults );

					$name = esc_attr( $this->users_key );
				?>
				<tr>
					<th><label for="<?php echo $name; ?>"><?php _e( "Disable user's Authy connection?", 'authy_for_wp' ); ?></label></th>
					<td>
						<input type="checkbox" id="<?php echo $name; ?>" name="<?php echo $name; ?>" value="1" />
						<label for="<?php echo $name; ?>"><?php _e( 'Yes, force user to reset the Authy connection.', 'authy_for_wp' ); ?></label>
					</td>
				</tr>
				<?php else : ?>
				<tr>
					<th><?php _e( 'Connection', 'authy_for_wp' ); ?></th>
					<td><?php _e( 'This user has not enabled Authy.', 'authy_for_wp' ); ?></td>
				</tr>
				<?php endif; ?>
			</table>
		<?php

			wp_nonce_field( $this->users_key . '_disable', "_{$this->users_key}_wpnonce" );
		}
	}

	/**
	 * Ajax handler for users' connection manager
	 *
	 * @uses wp_verify_nonce, get_current_user_id, get_userdata, this::get_authy_data, wp_print_scripts, wp_print_styles, body_class, esc_url, this::get_ajax_url, this::user_has_authy_id, _e, __, submit_button, wp_nonce_field, esc_attr, this::clear_authy_data, wp_safe_redirect, sanitize_email, this::set_authy_data
	 * @action wp_ajax_{$this->users_page}
	 * @return string
	 */
	public function ajax_get_id() {
		// If nonce isn't set, bail
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], $this->users_key . '_ajax' ) ) {
			?><script type="text/javascript">self.parent.tb_remove();</script><?php
			exit;
		}

		// User data
		$user_id = get_current_user_id();
		$user_data = get_userdata( $user_id );
		$authy_data = $this->get_authy_data( $user_id );

		// Step
		$step = isset( $_REQUEST['authy_step'] ) ? preg_replace( '#[^a-z0-9\-_]#i', '', $_REQUEST['authy_step'] ) : false;

		// iframe head
		?><head>
			<?php
				wp_print_scripts( array( 'jquery' ) );
				wp_print_styles( array( 'colors' ) );
			?>

			<style type="text/css">
				body {
					width: 450px;
					height: 250px;
					overflow: hidden;
					padding: 0 10px 10px 10px;
				}

				div.wrap {
					width: 450px;
					height: 250px;
					overflow: hidden;
				}

				table th label {
					font-size: 12px;
				}
			</style>
		</head><?php

		// iframe body
		?><body <?php body_class( 'wp-admin wp-core-ui' ); ?>>
			<div class="wrap">
				<h2>Authy for WP</h2>

				<form action="<?php echo esc_url( $this->get_ajax_url() ); ?>" method="post">

					<?php
						switch( $step ) {
							default :
								if ( $this->user_has_authy_id( $user_id ) ) : ?>
									<p><?php _e( 'You already have any Authy ID associated with your account.', 'authy_for_wp' ); ?></p>

									<p><?php printf( __( 'You can disable Authy for your <strong>%s</strong> user by clicking the button below', 'authy_for_wp' ), $user_data->user_login ); ?></p>

									<?php submit_button( __( 'Disable Authy', 'authy_for_wp' ) ); ?>

									<input type="hidden" name="authy_step" value="disable" />
									<?php wp_nonce_field( $this->users_key . '_ajax_disable' ); ?>
								<?php else : ?>
									<p><?php printf( __( 'Authy is not yet configured for your the <strong>%s</strong> account.', 'authy_for_wp' ), $user_data->user_login ); ?></p>

									<p><?php _e( 'To enable Authy for this account, complete the form below, then click <em>Continue</em>.', 'authy_for_wp' ); ?></p>

									<table class="form-table" id="<?php echo esc_attr( $this->users_key ); ?>-ajax">
										<tr>
											<th><label for="phone"><?php _e( 'Mobile number', 'authy_for_wp' ); ?></label></th>
											<td>
												<input type="tel" class="regular-text" name="authy_phone" value="<?php echo esc_attr( $authy_data['phone'] ); ?>" />
											</td>
										</tr>

										<tr>
											<th><label for="phone"><?php _e( 'Country code', 'authy_for_wp' ); ?></label></th>
											<td>
												<input type="text" class="small-text" name="authy_country_code" value="<?php echo esc_attr( $authy_data['country_code'] ); ?>" />
											</td>
										</tr>
									</table>

									<input type="hidden" name="authy_step" value="check" />
									<?php wp_nonce_field( $this->users_key . '_ajax_check' ); ?>

									<?php submit_button( __( 'Continue', 'authy_for_wp' ) ); ?>

								<?php endif;

								break;

							case 'disable' :
								if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], $this->users_key . '_ajax_disable' ) )
									$this->clear_authy_data( $user_id );

								wp_safe_redirect( $this->get_ajax_url() );
								exit;

								break;

							case 'check' :
								if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], $this->users_key . '_ajax_check' ) ) {
									$email = sanitize_email( $user_data->user_email );
									$phone = isset( $_POST['authy_phone'] ) ? preg_replace( '#[^\d]#', '', $_POST['authy_phone'] ) : false;
									$country_code = isset( $_POST['authy_country_code'] ) ? preg_replace( '#[^\d]#', '', $_POST['authy_country_code'] ) : false;

									if ( $email && $phone && $country_code ) {
										$this->set_authy_data( $user_id, $email, $phone, $country_code );

										if ( $this->user_has_authy_id( $user_id ) ) : ?>
											<p><?php printf( __( 'Congratulations, Authy is now configured for your <strong>%s</strong> user account.', 'authy_for_wp' ), $user_data->user_login ); ?></p>

											<p><?php _e( 'Until disabled, you will be asked for an Authy token each time you log in.', 'authy_for_wp' ); ?></p>

											<p><a class="button button-primary" href="#" onClick="self.parent.tb_remove();return false;"><?php _e( 'Return to your profile', 'authy_for_wp' ); ?></a></p>
										<?php else : ?>
											<p><?php printf( __( 'Authy could not be activated for the <strong>%s</strong> user account.', 'authy_for_wp' ), $user_data->user_login ); ?></p>

											<p><?php _e( 'Please try again later.', 'authy_for_wp' ); ?></p>

											<p><a class="button button-primary" href="<?php echo esc_url( $this->get_ajax_url() ); ?>"><?php _e( 'Try again', 'authy_for_wp' ); ?></a></p>
										<?php endif;

										exit;
									}
								}

								wp_safe_redirect( $this->get_ajax_url() );
								exit;

								break;

						}
					?>
				</form>
			</div>
		</body><?php

		exit;
	}

	/**
	 * Clear a user's Authy configuration if an allowed user requests it.
	 *
	 * @param int $user_id
	 * @uses wp_verify_nonce, this::clear_authy_data
	 * @action edit_user_profile_update
	 * @return null
	 */
	public function action_edit_user_profile_update( $user_id ) {
		if ( isset( $_POST["_{$this->users_key}_wpnonce"] ) && wp_verify_nonce( $_POST["_{$this->users_key}_wpnonce"], $this->users_key . '_disable' ) ) {
			if ( isset( $_POST[ $this->users_key ] ) )
				$this->clear_authy_data( $user_id );
		}
	}

	/**
	 * AUTHENTICATION CHANGES
	 */

	/**
	 * Add Two factor authentication page
	 *
	 * @param mixed $user
	 * @param string $redirect
	 * @uses _e
	 * @return string
	 */
	public function authy_token_form($user, $redirect) {
		$username = $user->user_login;
		?>
		<html>
          <head>
          	<?php
                global $wp_version;
                if(version_compare($wp_version, "3.3", "<=")){
            ?>
                    <link rel="stylesheet" type="text/css" href="<?php echo admin_url('css/login.css'); ?>" />
            <?php
                }
                else{
            ?>
                    <link rel="stylesheet" type="text/css" href="<?php echo admin_url('css/wp-admin.css'); ?>" />
                    <link rel="stylesheet" type="text/css" href="<?php echo admin_url('css/colors-fresh.css'); ?>" />
            <?php
                }
            ?>
          </head>
          <body class='login'>
            <div id="login">
                <h1><a href="http://wordpress.org/" title="Powered by WordPress"><?php echo get_bloginfo('name'); ?></a></h1>
          		<h3 style="text-align: center;">Two Factor Authentication</h3>
	          	<form method="POST" id="authy_for_wp" action="wp-login.php">
					<label for="authy_token"><?php _e( 'Authy Token', 'authy_for_wp' ); ?><br>
					<input type="text" name="authy_token" id="authy_token" class="input" value="" size="20"></label>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>"/>
					<input type="hidden" name="username" value="<?php echo esc_attr($username); ?>"/>
					<input type="submit" value="<?php echo _e('Login', 'authy_for_wp') ?>" id="wp_submit">
			    </form>
			</div>
          </body>
		<?php
	}

	/**
	* @param mixed $user
	* @param string $username
	* @param string $password
	* @uses XMLRPC_REQUEST, APP_REQUEST, this::user_has_authy_id, this::get_user_authy_id, this::api::check_token
	* @return mixed
	*/

	public function authenticate_user($user="", $username="", $password="") {
		// If the method isn't supported, stop.
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'APP_REQUEST' ) && APP_REQUEST ) )
			return $user;

		if (isset( $_POST['authy_token'] )) {
			remove_action('authenticate', 'wp_authenticate_username_password', 20);

	        // Check the specified token
	        $user = get_user_by('login', $_POST['username']);
			$authy_id = $this->get_user_authy_id( $user->ID );
			$authy_token = preg_replace( '#[^\d]#', '', $_POST['authy_token'] );
			$api_check = $this->api->check_token( $authy_id, $authy_token );

			// Act on API response
			if ( $api_check === false )
				return null;
			elseif ( is_string( $api_check ) )
				return new WP_Error( 'authentication_failed', __('<strong>ERROR</strong>: ' . $api_check ) );

            wp_set_auth_cookie($user->ID);
			wp_safe_redirect($_POST['redirect_to']);
            exit();
        }


        // If have a username
        if (! empty( $username )) {
        	$user = get_user_by('login', $username);

	        // Don't bother if WP can't provide a user object.
			if ( ! is_object( $user ) || ! property_exists( $user, 'ID' ) )
				return $user;

	        // User must opt in.
	        if ( ! $this->user_has_authy_id( $user->ID ) )
				return $user;

	        remove_action('authenticate', 'wp_authenticate_username_password', 20);

	        if (wp_check_password($password, $user->user_pass, $user->ID)) {
	        	$this->authy_token_form($user, $_POST['redirect_to']);
	        	exit();
			}else{
				$user = new WP_Error('authentication_failed', __('<strong>ERROR</strong>: Invalid username or incorrect password.'));
				return $user;
			}
		}
	}
}

Authy_WP::instance();
