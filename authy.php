<?php
/*
 * Plugin Name: Authy Two Factor Authentication
 * Plugin URI: https://github.com/authy/authy-wordpress
 * Description: Add <a href="http://www.authy.com/">Authy</a> two-factor authentication to WordPress.
 * Author: Authy Inc
 * Version: 1.2
 * Author URI: https://www.authy.com
 * License: GPL2+
 * Text Domain: authy

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

class Authy {
	/**
	 * Class variables
	 */
	// Oh look, a singleton
	private static $__instance = null;

	// Some plugin info
	protected $name = 'Authy Two-Factor Authentication';

	// Parsed settings
	private $settings = null;

	// Is API ready, should plugin act?
	protected $ready = false;

	// Authy API
	protected $api = null;
	protected $api_key = null;
	protected $api_endpoint = null;

	// Interface keys
	protected $settings_page = 'authy';
	protected $users_page = 'authy-user';

	// Data storage keys
	protected $settings_key = 'authy';
	protected $users_key = 'authy_user';
	protected $signature_key = 'user_signature';

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
		'authy_id'     => null,
		'force_by_admin' => 'false'
	);

	/**
	 * Singleton implementation
	 *
	 * @uses this::setup
	 * @return object
	 */
	public static function instance() {
		if ( ! is_a( self::$__instance, 'Authy' ) ) {
			self::$__instance = new Authy;
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
		require( 'authy-api.php' );

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
			add_filter( 'user_profile_update_errors', array( $this, 'check_user_fields' ), 10, 3);

			// Authentication
			add_filter( 'authenticate', array( $this, 'authenticate_user'), 10, 3);

			// Disable XML-RPC
			if ( $this->get_setting('disable_xmlrpc') )
				add_filter( 'xmlrpc_enabled', '__return_false' );

			add_action( 'admin_notices', array( $this, 'action_admin_notices' ) );
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
				'label'     => __( 'Authy Production API Key', 'authy' ),
				'type'      => 'text',
				'sanitizer' => 'alphanumeric'
			),
			array(
				'name'      => 'disable_xmlrpc',
				'label'     => __( "Disable external apps that don't support Two-factor Authentication", 'authy_wp' ),
				'type'      => 'checkbox',
				'sanitizer' => null
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
		$this->api = Authy_API::instance( $this->api_key, $this->api_endpoint );
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
		register_setting( $this->settings_page, 'authy_roles', array($this, 'roles_validate'));
	}

	/**
	 * Register plugin settings page and page's sections
	 *
	 * @uses add_options_page, add_settings_section
	 * @action admin_menu
	 * @return null
	 */
	public function action_admin_menu() {
		add_options_page( $this->name, 'Authy', 'manage_options', $this->settings_page, array( $this, 'plugin_settings_page' ) );
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

		global $current_screen;

		if ( $current_screen->base == 'profile') {
			wp_enqueue_script( 'authy-profile', plugins_url( 'assets/authy-profile.js', __FILE__ ), array( 'jquery', 'thickbox' ), 1.01, true );
			wp_enqueue_script( 'form-authy-js', 'https://www.authy.com/form.authy.min.js', array(), false, true);
			wp_localize_script( 'authy-profile', 'Authy', array(
				'ajax' => $this->get_ajax_url(),
				'th_text' => __( 'Two-Factor Authentication', 'authy' ),
				'button_text' => __( 'Enable/Disable Authy', 'authy' )
			) );

			wp_enqueue_style( 'thickbox' );
			wp_enqueue_style( 'form-authy-css', 'https://www.authy.com/form.authy.min.css', array(), false, 'screen' );
		}elseif ( $current_screen->base == 'user-edit' ) {
			wp_enqueue_script( 'form-authy-js', 'https://www.authy.com/form.authy.min.js', array(), false, true);
			wp_enqueue_style( 'form-authy-css', 'https://www.authy.com/form.authy.min.css', array(), false, 'screen' );
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
		if ( strpos( $plugin_file, pathinfo( __FILE__, PATHINFO_FILENAME ) ) !== false )
			$links['settings'] = '<a href="options-general.php?page=' . $this->settings_page . '">' . __( 'Settings', 'authy' ) . '</a>';

		return $links;
	}

	/**
	* Display an admin notice when the server doesn't installed a cert bundle.
	*/
	public function action_admin_notices() {
		$response = $this->api->curl_ca_certificates();
		if ( is_string($response) ){ ?>
		  <div id="message" class="error">
				<p>
					<strong>Error:</strong>
					<?php echo $response; ?>
				</p>
			</div>

		<?php }
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
				'environment'         => apply_filters( 'authy_environment', 'production' ),
				'disable_xmlrpc'      => false
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
			'nonce' => wp_create_nonce( $this->users_key . '_ajax' ),
		), admin_url( 'admin-ajax.php' ) );
	}

	/**
	* Check if Two factor authentication is available for role
	* @param object $user
	* @uses wp_roles, get_option
	* @return boolean
	*
	*/
	public function available_authy_for_role($user) {
		global $wp_roles;
		$available_authy = false;

		$listRoles = $wp_roles->get_names();

		$authy_roles = get_option('authy_roles', $listRoles);

		foreach ($user->roles as $role) {
			if (array_key_exists($role, $authy_roles))
				$available_authy = true;
		}
		return $available_authy;
	}

	/**
	 * GENERAL OPTIONS PAGE
	 */

	/**
	 * Populate settings page's sections
	 *
	 * @uses add_settings_field
	 * @return null
	 */
	public function register_settings_page_sections() {
		add_settings_field('api_key_production', __('Authy Production API Key', 'authy'), array( $this, 'add_settings_api_key' ), $this->settings_page, 'default');
		add_settings_field('authy_roles', __('Allow Authy for the following roles', 'authy'), array( $this, 'add_settings_roles' ), $this->settings_page, 'default');
		add_settings_field('disable_xmlrpc', __("Disable external apps that don't support Two-factor Authentication", 'authy'), array( $this, 'add_settings_disbale_xmlrpc' ), $this->settings_page, 'default');
	}

	/**
	 * Render settings api key
	 *
	 * @uses this::get_setting, esc_attr
	 * @return string
	 */
	public function add_settings_api_key() {
		$value = $this->get_setting( 'api_key_production' );

		?><input type="text" name="<?php echo esc_attr( $this->settings_key ); ?>[api_key_production]" class="regular-text" id="field-api_key_production" value="<?php echo esc_attr( $value ); ?>" /><?php
	}

	/**
	* Render settings roles
	* @uses $wp_roles
	* @return string
	*/
	public function add_settings_roles() {
		global $wp_roles;

		$roles = $wp_roles->get_names();
		$listRoles = array();

		foreach($roles as $key=>$role) {
			$listRoles[before_last_bar($key)] = before_last_bar($role);
		}

		$selected = get_option('authy_roles', $listRoles);

		foreach ($wp_roles->get_names() as $role) {
			?>
			<input name='authy_roles[<?php echo strtolower(before_last_bar($role)); ?>]' type='checkbox' value='<?php echo before_last_bar($role); ?>'<?php if(in_array(before_last_bar($role), $selected)) echo 'checked="checked"'; ?> /> <?php echo before_last_bar($role); ?></br>
			<?php
		}
	}

	/**
	* Render settings disable XMLRPC
	*
	* @return string
	*/
	public function add_settings_disbale_xmlrpc() {
		$value = $this->get_setting( 'disable_xmlrpc' );
		?>
		<label for='<?php echo esc_attr( $this->settings_key ); ?>[disable_xmlrpc]'>
			<input name="<?php echo esc_attr( $this->settings_key ); ?>[disable_xmlrpc]" type="checkbox" value="true" <?php if($value) echo 'checked="checked"'; ?> >
			<span style='color: #bc0b0b;'><?php _e("Ensure Two-factor authentication is always respected." , 'authy')?></span>
		</label>
		<p class ='description'><?php _e("WordPress mobile app's don't support Two-Factor authentication. If you disable this option you will be able to use the apps but it will bypass Two-Factor Authentication.", 'authy')?></p>
		<?php
	}

	/**
	 * Render settings page
	 *
	 * @uses screen_icon, esc_html, get_admin_page_title, settings_fields, do_settings_sections
	 * @return string
	 */

	public function plugin_settings_page() {
		$plugin_name = esc_html( get_admin_page_title() );
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php echo $plugin_name; ?></h2>

			<?php if ( $this->ready ) :
				$details = $this->api->application_details();
			?>
			<p><?php _e( "Enter your Authy API key (get one on authy.com/signup). You can select which users can enable authy by their WordPress role. Users can then enable Authy on their individual accounts by visting their user profile pages.", 'authy' ); ?></p>
			<p><?php _e( "You can also enable and force Two-Factor Authentication by editing the user on the Users page, and then clicking \"Enable Authy\" button on their settings.", 'authy' ); ?></p>

			<?php else :  ?>
				<p><?php printf( __( 'To use the Authy service, you must register an account at <a href="%1$s"><strong>%1$s</strong></a> and create an application for access to the Authy API.', 'authy' ), 'https://www.authy.com/' ); ?></p>
				<p><?php _e( "Once you've created your application, enter your API keys in the fields below.", 'authy' ); ?></p>
				<p><?php printf( __( "Until your API keys are entered, the %s plugin cannot function.", 'authy' ), $plugin_name ); ?></p>
			<?php endif; ?>

			<form action="options.php" method="post">

				<?php settings_fields( $this->settings_page ); ?>

				<?php do_settings_sections( $this->settings_page ); ?>

				<p class="submit">
					<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes');?>" class="button-primary">
				</p>
			</form>

			<?php if( !empty($details) ){ ?>
				<h2>Application Details</h2>

				<table class='widefat' style="width:400px;">
					<tbody>
						<tr>
							<th><?php printf(__('Application name', 'authy')); ?></th>
							<td><?php print $details['app']->name ?></td>
						</tr>
						<tr>
							<th><?php printf(__('Plan', 'authy')); ?></th>
							<td><?php print ucfirst($details['app']->plan) ?></td>
						</tr>
					</tbody>
				</table>

				<?php if($details['app']->plan == 'sandbox'){ ?>
					<strong style='color: #bc0b0b;'><?php _e( "Warning: text-messages won't work on the current plan. Upgrade for free to the Starter plan on your authy.com dashboard to enable text-messages.") ?></strong>
				<?php }
			}?>
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
	* Validate roles
	* @param array $roles
	* @uses $wp_roles
	* @return array
	*/

	public function roles_validate ($roles){

		if(!is_array($roles) || empty($roles)){
			return array();
		}

		global $wp_roles;
		$listRoles = $wp_roles->get_names();

		foreach ($roles as $role) {
			if (!in_array($roles, $listRoles)) {
				unset($roles[$role]);
			}
		}

		return $roles;
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
	 * @param string $force_by_admin
	 * @uses this::user_has_authy_id, this::api::get_id, wp_parse_args, this::clear_authy_data, get_user_meta, update_user_meta
	 * @return null
	 */
	public function set_authy_data( $user_id, $email, $phone, $country_code, $force_by_admin = 'false', $authy_id = '') {
		// Retrieve user's existing Authy ID, or get one from Authy
		if ( $this->user_has_authy_id( $user_id ) ) {
			$authy_id = $this->get_user_authy_id( $user_id );
		} elseif ($authy_id == '') {
			// Request an Authy ID with given user information
			$response = $this->api->register_user( $email, $phone, $country_code );

			if ( $response->user && $response->user->id ) {
				$authy_id = $response->user->id;
			} else {
				unset( $authy_id );
			}
		}

		// Build array of Authy data
		$data_sanitized = array(
			'email'          => $email,
			'phone'          => $phone,
			'country_code'   => $country_code,
			'force_by_admin' => $force_by_admin
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
	* Check if a given user has Two factor authentication forced by admin
	* @param int $user_id
	* @uses this::get_authy_data
	* @return bool
	*
	*/
	protected function with_force_by_admin( $user_id ) {
		$data = $this->get_authy_data( $user_id);

		if ($data['force_by_admin'] == 'true')
			return true;

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

		if ( $this->user_has_authy_id( $user->ID ) ) {
			if (!$this->with_force_by_admin( $user->ID)){ ?>
				<h3><?php echo esc_html( $this->name ); ?></h3>
				<table class="form-table" id="<?php echo esc_attr( $this->users_key ); ?>">
					<tr>
						<th><label for="<?php echo esc_attr( $this->users_key ); ?>_disable"><?php _e( 'Disable Two Factor Authentication?', 'authy' ); ?></label></th>
						<td>
							<input type="checkbox" id="<?php echo esc_attr( $this->users_key ); ?>_disable" name="<?php echo esc_attr( $this->users_key ); ?>[disable_own]" value="1" />
							<label for="<?php echo esc_attr( $this->users_key ); ?>_disable"><?php _e( 'Yes, disable Authy for your account.', 'authy' ); ?></label>

							<?php wp_nonce_field( $this->users_key . 'disable_own', $this->users_key . '[nonce]' ); ?>
						</td>
					</tr>
				</table>
			<?php }
		}elseif ($this->available_authy_for_role($user)) {?>
			<h3><?php echo esc_html( $this->name ); ?></h3>
			<table class="form-table" id="<?php echo esc_attr( $this->users_key ); ?>">
				<tr>
					<th><label for="phone"><?php _e( 'Country', 'authy' ); ?></label></th>
					<td>
						<input type="text" id="authy-countries" class="small-text" name="<?php echo esc_attr( $this->users_key ); ?>[country_code]" value="<?php echo esc_attr( $meta['country_code'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th><label for="phone"><?php _e( 'Cellphone number', 'authy' ); ?></label></th>
					<td>
						<input type="tel" id="authy-cellphone" class="regular-text" name="<?php echo esc_attr( $this->users_key ); ?>[phone]" value="<?php echo esc_attr( $meta['phone'] ); ?>" />

						<?php wp_nonce_field( $this->users_key . 'edit_own', $this->users_key . '[nonce]' ); ?>
					</td>
				</tr>
			</table>
		<?php }
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
					<th><label for="<?php echo $name; ?>"><?php _e( "Two Factor Authentication", 'authy' ); ?></label></th>
					<td>
						<input type="checkbox" id="<?php echo $name; ?>" name="<?php echo $name; ?>" value="1" checked/>
					</td>
				</tr>
				<?php wp_nonce_field( $this->users_key . '_disable', "_{$this->users_key}_wpnonce" );

				else :
					$authy_data = $this->get_authy_data( $user->ID );
				?>
				<tr>
					<p><?php _e("To enable Authy enter the country and cellphone number of the person who is going to use this account.", 'authy')?></p>
					<th><label for="phone"><?php _e( 'Country', 'authy' ); ?></label></th>
					<td>
						<input type="text" id="authy-countries" class="small-text" name="<?php echo esc_attr( $this->users_key ); ?>[country_code]" value="<?php echo esc_attr( $authy_data['country_code'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th><label for="phone"><?php _e( 'Cellphone number', 'authy' ); ?></label></th>
					<td>
						<input type="tel" class="regular-text" id="authy-cellphone" name="<?php echo esc_attr( $this->users_key ); ?>[phone]" value="<?php echo esc_attr( $authy_data['phone'] ); ?>" />
					</td>
					<?php wp_nonce_field( $this->users_key . '_edit', "_{$this->users_key}_wpnonce" ); ?>
				</tr>
				<?php endif; ?>
			</table>
		<?php
		}
	}

	/**
	* Add errors when editing another user's profile
	*
	*/
	public function check_user_fields(&$errors, $update, &$user) {
		if ( $update && !empty($_POST['authy_user']['phone'])) {
			$response = $this->api->register_user( $_POST['email'], $_POST['authy_user']['phone'], $_POST['authy_user']['country_code'] );

			if ($response->errors) {
				foreach ($response->errors as $attr => $message) {

					if ($attr == 'country_code')
						$errors->add('authy_error', '<strong>Error:</strong> ' . 'Authy country code is invalid');
					else
					  $errors->add('authy_error', '<strong>Error:</strong> ' . 'Authy ' . $attr . ' ' . $message);
				}
			}
		}
	}

  /**
  * Print head element
  *
  * @uses wp_print_scripts, wp_print_styles
  * @return @string
  */
  public function ajax_head() {
		?><head>
			<?php
				wp_print_scripts( array( 'jquery', 'authy' ) );
				wp_print_styles( array( 'colors', 'authy' ) );
			?>
			<link href="https://www.authy.com/form.authy.min.css" media="screen" rel="stylesheet" type="text/css">
			<script src="https://www.authy.com/form.authy.min.js" type="text/javascript"></script>

			<style type="text/css">
				body {
					width: 450px;
					height: 380px;
					overflow: hidden;
					padding: 0 10px 10px 10px;
				}

				div.wrap {
					width: 450px;
					height: 380px;
					overflow: hidden;
				}

				table th label {
					font-size: 12px;
				}
			</style>
		</head><?php
  }

	/**
	 * Ajax handler for users' connection manager
	 *
	 * @uses wp_verify_nonce, get_current_user_id, get_userdata, this::get_authy_data, wp_print_scripts, wp_print_styles, body_class, esc_url, this::get_ajax_url, this::user_has_authy_id, _e, __, wp_nonce_field, esc_attr, this::clear_authy_data, wp_safe_redirect, sanitize_email, this::set_authy_data
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
		$errors = array();

		// Step
		$step = isset( $_REQUEST['authy_step'] ) ? preg_replace( '#[^a-z0-9\-_]#i', '', $_REQUEST['authy_step'] ) : false;

    //iframe head
    $this->ajax_head();

		// iframe body
		?><body <?php body_class('wp-admin wp-core-ui authy-user-modal'); ?>>
			<div class="wrap">
				<h2>Authy Two-Factor Authentication</h2>

				<form action="<?php echo esc_url( $this->get_ajax_url() ); ?>" method="post">

					<?php
						switch( $step ) {
							default :
								if ( $this->user_has_authy_id( $user_id ) ) { ?>
									<p><?php _e( 'Authy is enabled for this account.', 'authy' ); ?></p>

									<p><?php printf( __( 'Click the button below to disable Two-Factor Authentication for <strong>%s</strong>', 'authy' ), $user_data->user_login ); ?></p>

                  <p class="submit">
										<input name="Disable" type="submit" value="<?php esc_attr_e('Disable Authy');?>" class="button-primary">
									</p>

									<input type="hidden" name="authy_step" value="disable" />
									<?php wp_nonce_field( $this->users_key . '_ajax_disable' );
								} else {
									if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], $this->users_key . '_ajax_check' ) ) {
										$email = sanitize_email( $user_data->user_email );
										$phone = isset( $_POST['authy_phone'] ) ? preg_replace( '#[^\d]#', '', $_POST['authy_phone'] ) : false;
										$country_code = isset( $_POST['authy_country_code'] ) ? preg_replace( '#[^\d]#', '', $_POST['authy_country_code'] ) : false;

										$response = $this->api->register_user( $email, $phone, $country_code );

										if ( $response->success == 'true' ) {
											$this->set_authy_data( $user_id, $email, $phone, $country_code, $response->user->id );

											if ( $this->user_has_authy_id( $user_id ) ) { ?>
												<p><?php printf( __( 'Congratulations, Authy is now configured for <strong>%s</strong> user account.', 'authy' ), $user_data->user_login ); ?></p>

												<p>
													<?php _e( 'We\'ve sent you an e-mail and text-message with instruction on how to install the Authy App. If you do not install the App, we\'ll automatically send you a text-message to your cellphone ', 'authy'); ?>
													<strong><?php echo $phone; ?></strong>
													<?php _e('on every login with the token that you need to use for when you login.', 'authy' ); ?>
												</p>

												<p><a class="button button-primary" href="#" onClick="self.parent.tb_remove();return false;"><?php _e( 'Return to your profile', 'authy' ); ?></a></p>
											  <?php
											} else { ?>
												<p><?php printf( __( 'Authy could not be activated for the <strong>%s</strong> user account.', 'authy' ), $user_data->user_login ); ?></p>

												<p><?php _e( 'Please try again later.', 'authy' ); ?></p>

												<p><a class="button button-primary" href="<?php echo esc_url( $this->get_ajax_url() ); ?>"><?php _e( 'Try again', 'authy' ); ?></a></p>
											  <?php
											}
											exit;

										} else {
											$errors = get_object_vars($response->errors);
										}
									} ?>

									<p><?php printf( __( 'Authy is not yet configured for your the <strong>%s</strong> account.', 'authy' ), $user_data->user_login ); ?></p>

									<p><?php _e( 'To enable Authy for this account, complete the form below, then click <em>Continue</em>.', 'authy' ); ?></p>

									<?php
										if ( !empty($errors) ) { ?>
											<div class='error'><?php
												foreach ($errors as $key => $value) {
													if ($key == 'country_code') {
														?><p><strong>Country code</strong> is not valid.</p><?php
													} else {
														?><p><strong><?php echo ucfirst($key); ?></strong><?php echo ' ' . $value; ?></p><?php
													}
												}?>
											</div><?php
										}
									?>

									<table class="form-table" id="<?php echo esc_attr( $this->users_key ); ?>-ajax">
										<tr>
											<th><label for="phone"><?php _e( 'Country', 'authy' ); ?></label></th>
											<td>
												<input type="text" id="authy-countries" class="small-text" name="authy_country_code" value="<?php echo esc_attr( $authy_data['country_code'] ); ?>" required />
											</td>
										</tr>
										<tr>
											<th><label for="phone"><?php _e( 'Cellphone number', 'authy' ); ?></label></th>
											<td>
												<input type="tel" id="authy-cellphone" class="regular-text" name="authy_phone" value="<?php echo esc_attr( $authy_data['phone'] ); ?>" style="width:140px;" />
											</td>
										</tr>

									</table>

									<input type="hidden" name="authy_step" value="" />
									<?php wp_nonce_field( $this->users_key . '_ajax_check' ); ?>

									<p class="submit">
										<input name="Continue" type="submit" value="<?php esc_attr_e('Continue');?>" class="button-primary">
									</p>

								<?php }

								break;

							case 'disable' :
								if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], $this->users_key . '_ajax_disable' ) )
									$this->clear_authy_data( $user_id );?>

								<p><?php print_r( __('Authy was disabled', 'authy'));?></p>
								<p><a class="button button-primary" href="#" onClick="self.parent.tb_remove();return false;"><?php _e( 'Return to your profile', 'authy' ); ?></a></p>
								<?php
									exit;

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
	 * Send SMS with Authy token
	 * @param string $username
	 * @return null
	 */
	public function action_request_sms($username) {
		$user = get_user_by('login', $username);
		$authy_id = $this->get_user_authy_id( $user->ID );
		$api_rsms = $this->api->request_sms( $authy_id);
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
			if ( !isset( $_POST[ $this->users_key ] ) ){
				$this->clear_authy_data( $user_id );
			}
		}else{
			$email = $_POST['email'];
			$phone = $_POST['authy_user']['phone'];
			$country_code = $_POST['authy_user']['country_code'];
			$this->set_authy_data( $user_id, $email, $phone, $country_code, 'true' );
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
    $user_data = $this->get_authy_data( $user->ID );
	$user_signature = get_user_meta($user->ID, $this->signature_key, true);
    ?>
		<html>
			<head>
				<?php
				global $wp_version;
				if(version_compare($wp_version, "3.3", "<=")){?>
					<link rel="stylesheet" type="text/css" href="<?php echo admin_url('css/login.css'); ?>" />
					<link rel="stylesheet" type="text/css" href="<?php echo admin_url('css/colors-fresh.css'); ?>" />
					<?php
				}else{
					?>
					<link rel="stylesheet" type="text/css" href="<?php echo admin_url('css/wp-admin.css'); ?>" />
					<link rel="stylesheet" type="text/css" href="<?php echo includes_url('css/buttons.css'); ?>" />
					<link rel="stylesheet" type="text/css" href="<?php echo admin_url('css/colors-fresh.css'); ?>" />
					<?php
				}
				?>
				<link href="https://www.authy.com/form.authy.min.css" media="screen" rel="stylesheet" type="text/css">
				<script src="https://www.authy.com/form.authy.min.js" type="text/javascript"></script>
			</head>
			<body class='login wp-core-ui'>
				<div id="login">
					<h1><a href="http://wordpress.org/" title="Powered by WordPress"><?php echo get_bloginfo('name'); ?></a></h1>
					<h3 style="text-align: center; margin-bottom:10px;">Authy Two-Factor Authentication</h3>
					<p class="message"><?php _e("You can get this token from the Authy mobile app. If you are not using the Authy app we've automatically sent you a token via text-message to cellphone number: ", 'authy'); ?><strong><?php echo $user_data['phone']; ?></strong></p>

					<form method="POST" id="authy" action="wp-login.php">
						<label for="authy_token"><?php _e( 'Authy Token', 'authy' ); ?><br>
						<input type="text" name="authy_token" id="authy-token" class="input" value="" size="20"></label>
						<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>"/>
						<input type="hidden" name="username" value="<?php echo esc_attr($username); ?>"/>
						<?php if(isset($user_signature['authy_signature']) && isset($user_signature['signed_at']) ) { ?>
							<input type="hidden" name="authy_signature" value="<?php echo esc_attr($user_signature['authy_signature']); ?>"/>
						<?php } ?>
						<p class="submit">
						  <input type="submit" value="<?php echo _e('Login', 'authy') ?>" id="wp_submit" class="button button-primary button-large">
						</p>
					</form>
				</div>
			</body>
		</html>
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
		// If the method isn't supported, stop:
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'APP_REQUEST' ) && APP_REQUEST ) )
			return $user;

		if (isset($_POST['authy_signature']) && isset( $_POST['authy_token'] )) {
			$user = get_user_by('login', $_POST['username']);

			// This line prevents WordPress from setting the authentication cookie and display errors.
			remove_action('authenticate', 'wp_authenticate_username_password', 20);

			// Do 2FA if signature is valid.
			if($this->api->verify_signature(get_user_meta($user->ID, $this->signature_key, true), $_POST['authy_signature'])) {
				// invalidate signature
				update_user_meta($user->ID, $this->signature_key, array("authy_signature" => $this->api->generate_signature(), "signed_at" => null));

				// Check the specified token
				$authy_id = $this->get_user_authy_id( $user->ID );
				$authy_token = preg_replace( '#[^\d]#', '', $_POST['authy_token'] );
				$api_check = $this->api->check_token( $authy_id, $authy_token);

				// Act on API response
				if ( $api_check === true ) {
					wp_set_auth_cookie($user->ID);
					wp_safe_redirect($_POST['redirect_to']);
					exit(); // redirect without returning anything.
				} elseif ( is_string( $api_check ) ) {
					return new WP_Error( 'authentication_failed', __('<strong>ERROR</strong>: ' . $api_check ) );
				}
			}

			return new WP_Error( 'authentication_failed', __('<strong>ERROR</strong> Authentication timed out. Please try again.'));
		}

		// If have a username do password authentication and redirect to 2nd screen.
		if (! empty( $username )) {
			$userWP = get_user_by('login', $username);

			// Don't bother if WP can't provide a user object.
			if ( ! is_object( $userWP ) || ! property_exists( $userWP, 'ID' ) )
				return $userWP;

			// User must opt in.
			if ( ! $this->user_has_authy_id( $userWP->ID ))
				return $user; // wordpress will continue authentication.

			// from here we take care of the authentication.
			remove_action('authenticate', 'wp_authenticate_username_password', 20);

			$ret = wp_authenticate_username_password($user, $username, $password);
			if(is_wp_error($ret)) {
				// there was an error
				return $ret;
			}

			$user = $ret;

			if (!is_wp_error($user)) {
				// with authy
				update_user_meta($user->ID, $this->signature_key, array("authy_signature" => $this->api->generate_signature(), "signed_at" => time()));
				$this->action_request_sms($username);
				$this->authy_token_form($user, $_POST['redirect_to']);
				exit();
			}
		}

		return new WP_Error('authentication_failed', __('<strong>ERROR</strong>') );
	}
}

Authy::instance();
