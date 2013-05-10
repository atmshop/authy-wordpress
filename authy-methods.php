<?php
/**************************************************
 * Methods for Authy plugin
 **************************************************/

/**
 * Generate and save the app specific password for the user
 * @param string $user_id
 * @param string $specific_password_key
 * @return null
 * @since 2.0.3
 */
function generate_app_specific_password( $user_id ) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $password = '';
    for ( $i = 0; $i < 16; $i++ ) {
        $password .= substr($chars, rand(0, strlen($chars) - 1), 1);
    }
    delete_user_meta( $user_id, 'authy_password' );
    update_user_meta( $user_id, 'authy_app_password', $password );
}

/**
 * Get the Authy applicaiton specific password from DB for user
 * @param string $user_id
 * @return string
 * @since 2.0.3
 */
function get_app_password( $user_id ) {
    return get_user_meta( $user_id, 'authy_app_password', true );
}

/**
 * Authentication for externals apps (XML-RPC) using application
 * specific password generate with Authy plugin
 * @param string $username
 * @param string $password
 * @return mixed
 * @since 2.0.3
 */
function authenticate_for_xml_rpc( $user, $password ) {
    remove_action( 'authenticate', 'wp_authenticate_username_password', 20 );

    $app_password = get_app_password( $user->ID );
    if ( !empty( $app_password ) && $app_password === $password ) {
        return $user;
    }

    return new WP_Error( 'authentication_failed', __( 'Incorrect Password', 'authy' ) );
}