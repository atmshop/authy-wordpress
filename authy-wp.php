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
		$this->register_settings_fields();
		$this->prepare_api();

		// Commong plugin elements
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
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
	public function user_settings_page() {
		//
	}
}

Authy_WP::instance();