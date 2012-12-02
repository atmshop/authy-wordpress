<?php
/*
 * Plugin Name: Authy for WordPress
 * Plugin URI: http://www.ethitter.com/plugins/authy-for-wordpress/
 * Description: Add <a href="http://www.authy.com/">Authy</a> two-factor authentication to WordPress.
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
	 *
	 */
	private function setup() {
		require( 'authy-wp-api.php' );

		$this->register_settings_fields();
		$this->prepare_api();

		// Plugin settings
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );

		// Anything other than plugin configuration belongs in here.
		if ( $this->ready ) {
			// User settings
			add_action( 'show_user_profile', array( $this, 'action_show_user_profile' ) );
			add_action( 'edit_user_profile', array( $this, 'action_edit_user_profile' ) );
			add_action( 'personal_options_update', array( $this, 'action_personal_options_update' ) );
			add_action( 'edit_user_profile_update', array( $this, 'action_edit_user_profile_update' ) );

			// Authentication
			add_action( 'login_form', array( $this, 'action_login_form' ), 50 );
			add_filter( 'authenticate', array( $this, 'action_authenticate' ), 9999, 2 );
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
			),
			array(
				'name'      => 'api_key_development',
				'label'     => __( 'Development API Key', 'authy_wp' ),
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
			'production'  => 'https://api.authy.com',
			'development' => 'http://sandbox-api.authy.com'
		);

		// Plugin page accepts keys for production and development.
		// Cannot be toggled except via the `authy_wp_environment` filter.
		$environment = $this->get_setting( 'environment' );

		// API key is specific to the environment
		$api_key = $this->get_setting( 'api_key_' . $environment );

		// Only prepare the API endpoint if we have all information needed.
		if ( $api_key && isset( $endpoints[ $environment ] ) ) {
			$this->api_key = $api_key;
			$this->api_endpoint = $endpoints[ $environment ];

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
		add_options_page( 'Authy for WP', 'Authy for WP', 'manage_options', $this->settings_page, array( $this, 'plugin_settings_page' ) );
		add_settings_section( 'default', '', array( $this, 'register_settings_page_sections' ), $this->settings_page );
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
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

			<p><?php printf( __( 'To use the Authy service, you must register an account at <a href="%1$s"><strong>%1$s</strong></a> and create an application for access to the Authy API.', 'authy_for_wp' ), 'http://www.authy.com/' ); ?></p>

			<p><?php _e( "Once you've created your application, enter your API keys in the fields below.", 'authy_for_wp' ); ?></p>

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
	 * @uses this::user_has_authy_id, this::api::get_id, wp_parse_args, delete_user_meta, get_user_meta, update_user_meta
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
			delete_user_meta( $user_id, $this->users_key );
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
	 *
	 */
	public function action_show_user_profile() {
		$meta = $this->get_authy_data( get_current_user_id() );
	?>
		<h3>Authy Two-factor Authentication</h3>

		<table class="form-table">
			<tr>
				<th><label for="phone">Mobile number</label></th>
				<td>
					<input type="tel" name="<?php echo esc_attr( $this->users_key ); ?>[phone]" value="<?php echo esc_attr( $meta['phone'] ); ?>" />
				</td>
			</tr>

			<tr>
				<th><label for="phone">Country code</label></th>
				<td>
					<input type="text" name="<?php echo esc_attr( $this->users_key ); ?>[country_code]" value="<?php echo esc_attr( $meta['country_code'] ); ?>" />
				</td>
			</tr>
		</table>

	<?php
		wp_nonce_field( $this->users_key . 'edit_own', $this->users_key . '[nonce]' );
	}

	/**
	 *
	 */
	public function action_personal_options_update( $user_id ) {
		check_admin_referer( 'update-user_' . $user_id );

		// Check if we have data to work with
		$authy_data = isset( $_POST[ $this->users_key ] ) ? $_POST[ $this->users_key ] : false;

		// Parse for nonce and API existence
		if ( is_array( $authy_data ) && array_key_exists( 'nonce', $authy_data ) && wp_verify_nonce( $authy_data['nonce'], $this->users_key . 'edit_own' ) ) {
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
		}
	}

	/**
	 *
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
					<th><label for="<?php echo $name; ?>">Disable user's Authy connection?</label></th>
					<td>
						<input type="checkbox" id="<?php echo $name; ?>" name="<?php echo $name; ?>" value="1" />
						<label for="<?php echo $name; ?>">Yes, force user to reset the Authy connection</label>
					</td>
				</tr>
				<?php else : ?>
				<tr>
					<th>This user has not enabled Authy.</th>
					<td></td>
				</tr>
				<?php endif; ?>
			</table>
		<?php

			wp_nonce_field( $this->users_key . '_disable', "_{$this->users_key}_wpnonce" );
		}
	}

	/**
	 *
	 */
	public function action_edit_user_profile_update( $user_id ) {
		if ( isset( $_POST["_{$this->users_key}_wpnonce"] ) && check_admin_referer( $this->users_key . '_disable', "_{$this->users_key}_wpnonce" ) ) {
			if ( isset( $_POST[ $this->users_key ] ) )
				delete_user_meta( $user_id, $this->users_key );
		}
	}

	/**
	 * AUTHENTICATION CHANGES
	 */

	/**
	 * Add Authy input field to login page
	 *
	 * @action login_form
	 * @return string
	 */
	public function action_login_form() {
		?>
		<p>
			<label for="authy_token">Authy Token<br>
			<input type="text" name="authy_token" id="authy_token" class="input" value="" size="20"></label>
		</p>
		<?php
	}

	/**
	 * Attempt Authy verification if conditions are met.
	 *
	 * @param mixed $user
	 * @param string $username
	 * @uses XMLRPC_REQUEST, APP_REQUEST, this::user_has_authy_id, this::get_user_authy_id, this::api::check_token
	 * @return mixed
	 */
	public function action_authenticate( $user, $username ) {
		// If we don't have a username yet, or the method isn't supported, stop.
		if ( empty( $username ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'APP_REQUEST' ) && APP_REQUEST ) )
			return $user;

		// Don't bother if WP can't provide a user object.
		if ( ! is_object( $user ) || ! property_exists( $user, 'ID' ) )
			return $user;

		// User must opt in.
		if ( ! $this->user_has_authy_id( $user->ID ) )
			return $user;

		// If a user has opted in, he/she must provide a token
		if ( ! isset( $_POST['authy_token'] ) || empty( $_POST['authy_token'] ) )
			return new WP_Error( 'authentication_failed', sprintf( __('<strong>ERROR</strong>: To log in as <strong>%s</strong>, you must provide an Authy token.'), $username ) );

		// Check the specified token
		$authy_id = $this->get_user_authy_id( $user->ID );
		$authy_token = preg_replace( '#[^\d]#', '', $_POST['authy_token'] );
		$api_check = $this->api->check_token( $authy_id, $authy_token );

		// Act on API response
		if ( false === $api_check )
			return null;
		elseif ( is_string( $api_check ) )
			return new WP_Error( 'authentication_failed', __('<strong>ERROR</strong>: ' . $api_check ) );

		return $user;
	}
}

Authy_WP::instance();