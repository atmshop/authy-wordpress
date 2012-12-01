<?php
/*
 * Plugin Name: Authy for WordPress
 * Plugin URI: http://www.ethitter.com/plugins/authy-for-wordpress/
 * Description: Add <a href="http://www.authy.com/">Authy</a> two-factor authentication to WordPress.
 * Author: Erick Hitter
 * Version: 0.1
 * Author URI: http://www.ethitter.com/
 * License: GPL2+

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

	private $settings = null;

	// Authy API
	protected $api = null;
	protected $api_key = null;
	protected $api_endpoint = null;

	// Commong plugin elements
	protected $settings_page = 'authy-for-wp';
	protected $users_page = 'authy-for-wp-user';

	protected $settings_key = 'authy_for_wp';
	protected $users_key = 'authy_for_wp_user';

	protected $settings_fields = array();

	protected $settings_field_defaults = array(
		'label'    => null,
		'type'     => 'text',
		'sanitizer' => 'sanitize_text_field',
		'section'  => 'default',
		'class'    => null
	);

	protected $user_defaults = array(
		'email'        => null,
		'phone'        => null,
		'country_code' => null,
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

		// User settings
		add_action( 'show_user_profile', array( $this, 'action_show_user_profile' ) );
		// add_action( 'edit_user_profile', array( $this, 'action_edit_user_profile' ) );
		add_action( 'personal_options_update', array( $this, 'action_personal_options_update' ) );
		// add_action( 'edit_user_profile_update', array( $this, 'action_edit_user_profile_update' ) );
	}

	/**
	 *
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
	 *
	 */
	protected function prepare_api() {
		$endpoints = array(
			'production'  => 'https://api.authy.com',
			'development' => 'http://sandbox-api.authy.com'
		);

		$environment = $this->get_setting( 'environment' );

		$api_key = $this->get_setting( 'api_key_' . $environment );

		if ( $api_key && isset( $endpoints[ $environment ] ) ) {
			$this->api_key = $api_key;
			$this->api_endpoint = $endpoints[ $environment ];
		}

		$this->api = Authy_WP_API::instance( $this->api_key, $this->api_endpoint );
	}

	/**
	 * COMMON PLUGIN ELEMENTS
	 */

	/**
	 *
	 */
	public function action_admin_init() {
		register_setting( $this->settings_page, $this->settings_key, array( $this, 'validate_plugin_settings' ) );
		add_settings_section( 'default', '', array( $this, 'register_settings_page_sections' ), $this->settings_page );
	}

	/**
	 *
	 */
	public function action_admin_menu() {
		add_options_page( 'Authy for WP', 'Authy for WP', 'manage_options', $this->settings_page, array( $this, 'plugin_settings_page' ) );
	}

	/**
	 *
	 */
	public function get_setting( $key ) {
		$value = false;

		if ( is_null( $this->settings ) || ! is_array( $this->settings ) ) {
			$this->settings = get_option( $this->settings_key );
			$this->settings = wp_parse_args( $this->settings, array(
				'api_key_production'  => '',
				'api_key_development' => '',
				'environment'            => 'development'
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
	 *
	 */
	public function register_settings_page_sections() {
		foreach ( $this->settings_fields as $args ) {
			$args = wp_parse_args( $args, $this->settings_field_defaults );

			add_settings_field( $args['name'], $args['label'], array( $this, 'form_field_' . $args['type'] ), $this->settings_page, $args['section'], $args );
		}
	}

	/**
	 *
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
	 *
	 */
	public function plugin_settings_page() {
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

			<form action="options.php" method="post">

				<?php settings_fields( $this->settings_page ); ?>

				<?php do_settings_sections( $this->settings_page ); ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 *
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
	 * USER SETTINGS PAGE
	 */

	/**
	 *
	 */
	public function action_show_user_profile() {
		$meta = get_user_meta( get_current_user_id(), $this->users_key, true );
		$meta = wp_parse_args( $meta, $this->user_defaults );
	?>
		<h3>Authy Two-factor Authentication</h3>

		<table class="form-table">
			<tr>
				<th><label for="phone">Mobile number</lable></th>
				<td>
					<input type="tel" name="<?php echo esc_attr( $this->users_key ); ?>[phone]" value="<?php echo esc_attr( $meta['phone'] ); ?>" />
				</td>
			</tr>

			<tr>
				<th><label for="phone">Country code</lable></th>
				<td>
					<input type="text" name="<?php echo esc_attr( $this->users_key ); ?>[country_code]" value="<?php echo esc_attr( $meta['country_code'] ); ?>" />
				</td>
			</tr>
		</table>

		<input type="hidden" name="<?php echo esc_attr( $this->users_key ); ?>[authy_id]" value="<?php echo esc_attr( $meta['authy_id'] ); ?>" />
		<?php wp_nonce_field( $this->users_key . 'edit_own', $this->users_key . '[nonce]' ); ?>
	<?php
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

		// Record Authy data, or clear out if empty
		$data_sanitized = array(
			'email'        => $email,
			'phone'        => $phone,
			'country_code' => $country_code
		);

		if ( isset( $authy_id ) )
			$data_sanitized['authy_id'] = $authy_id;

		$data_sanitized = wp_parse_args( $data_sanitized, $this->user_defaults );

		if ( empty( $data_sanitized ) )
			delete_user_meta( $user_id, $this->users_key );
		else
			update_user_meta( $user_id, $this->users_key, $data_sanitized );
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
	 * @uses get_user_meta
	 * @return int|bool
	 */
	protected function get_user_authy_id( $user_id ) {
		$data = get_user_meta( $user_id, $this->users_key, true );

		if ( is_array( $data ) && array_key_exists( 'authy_id', $data ) )
			return (int) $data['authy_id'];

		return false;
	}

	/**
	 *
	 */
	public function action_edit_user_profile() {
		// If user has rights, permit them to disable Authy for a given user.
	}
}

Authy_WP::instance();