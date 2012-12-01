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

	protected $settings_page = 'authy-for-wp';
	protected $users_page = 'authy-for-wp-user';

	protected $settings_key = 'authy_for_wp';
	protected $users_key = 'authy_for_wp_user';

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
		// Commong plugin elements
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
	}

	/**
	 * COMMON PLUGIN ELEMENTS
	 */

	/**
	 *
	 */
	public function action_admin_init() {
		register_setting( $this->settings_key, $this->settings_key, array( $this, 'validate_plugin_settings' ) );
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
	public function plugin_settings_page() {
		//
	}

	/**
	 *
	 */
	public function validate_plugin_settings( $settings ) {
		$settings_validated = array();

		return $settings_validated;
	}

	/**
	 *
	 */
	public function user_settings_page() {
		//
	}
}

Authy_WP::instance();