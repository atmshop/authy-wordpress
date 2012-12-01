<?php
/**
 * AUTHY FOR WP
 * API CLASS
 */

class Authy_WP_API {
	/**
	 * Class variables
	 */
	// Oh look, a singleton
	private static $__instance = null;

	// Authy API
	protected $api_key = null;
	protected $api_endpoint = null;

	/**
	 * Singleton implementation
	 *
	 * @uses this::setup
	 * @return object
	 */
	public static function instance( $api_key, $api_endpoint ) {
		if ( ! is_a( self::$__instance, 'Authy_WP_API' ) ) {
			if ( is_null( $api_key ) || is_null( $api_endpoint ) )
				return null;

			self::$__instance = new Authy_WP_API;

			self::$__instance->api_key = $api_key;
			self::$__instance->api_endpoint = $api_endpoint;

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
	private function setup() {}

	/**
	 *
	 */
	public function get_id( $email, $phone, $country_code ) {
		// Sanitize arguments
		$email = sanitize_email( $email );
		$phone = preg_replace( '#[^\d]#', '', $phone );
		$country_code = preg_replace( '#[^\d\+]#', '', $country_code );

		// Build API endpoint
		$endpoint = sprintf( '%s/protected/json/users/new', $this->api_endpoint );
		$endpoint = add_query_arg( array(
			'api_key' =>$this->api_key,
			'user[email]' => $email,
			'user[cellphone]' => $phone,
			'user[country_code]' => $country_code
		), $endpoint );

		// Make API request up to three times and parse response
		for ( $i = 1; $i <= 3; $i++ ) {
			$response = wp_remote_post( $endpoint );

			if ( '200' == wp_remote_retrieve_response_code( $response ) ) {
				$body = wp_remote_retrieve_body( $response );

				if ( ! empty( $body ) ) {
					$body = json_decode( $body );

					if ( is_object( $body ) && property_exists( $body, 'user' ) && property_exists( $body->user, 'id' ) )
						return $body->user->id;
				}

				break;
			}
		}

		return false;
	}

	/**
	 * // token must be a string because it can have leading zeros
	 */
	public function check_token( $id, $token ) {
		$endpoint = sprintf( '%s/protected/json/verify/%s/%d', $this->api_endpoint, $token, $id );
		$endpoint = add_query_arg( array(
			'api_key' => $this->api_key,
			'force' => 'true'
		), $endpoint );

		// Make API request up to three times and check responding status code
		for ( $i = 1; $i <= 3; $i ++ ) {
			$response = wp_remote_head( $endpoint );
			$status_code = wp_remote_retrieve_response_code( $response );

			if ( 200 == $status_code )
				return true;
			elseif ( 401 == $status_code )
				return __( 'The Authy token provided could not be verified. Please try again.', 'authy_wp' );
		}

		return false;
	}
}
