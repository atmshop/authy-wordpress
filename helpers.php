<?php
/**
* Header for authy pages
*/
function authy_header($step = '') {
  ?>
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
    <?php
      if ( $step == 'verify_installation' ) { ?>
        <link href="<?php echo plugins_url( 'assets/authy.css', __FILE__ ); ?>" media="screen" rel="stylesheet" type="text/css">
        <script type="text/javascript">
        /* <![CDATA[ */
        var AuthyAjax = {"ajaxurl":"<?php echo admin_url('admin-ajax.php'); ?>"};
        /* ]]> */
        </script>
        <script src="<?php echo admin_url( 'load-scripts.php?c=1&load=jquery,utils'); ?>" type="text/javascript"></script>
        <script src="<?php echo plugins_url( 'assets/authy-installation.js', __FILE__ ); ?>" type="text/javascript"></script><?php
      }
    ?>
  </head>
  <?php
}


/**
 * Generate the authy token form
 * @param string $username
 * @param array $user_data
 * @param array $user_signature
 * @return string
 */

function authy_token_form($username, $user_data, $user_signature, $redirect) {
  ?>
  <html>
    <?php echo authy_header(); ?>
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
* Enable authy page
*
* @param mixed $user
* @return string
*/
function enable_authy_page($user, $signature, $errors = array()) {
  ?>
  <html>
    <?php echo authy_header(); ?>
    <body class='login wp-core-ui'>
      <div id="login">
        <h1><a href="http://wordpress.org/" title="Powered by WordPress"><?php echo get_bloginfo('name'); ?></a></h1>
        <h3 style="text-align: center; margin-bottom:10px;">Enable Authy Two-Factor Authentication</h3>
        <?php
          if( !empty($errors) ) {
            $message = '';
            foreach ($errors as $msg) {
              $message .= "<strong>ERROR: </strong>" . $msg . '<br>';
            }
            ?><div id="login_error"><?php echo __($message, 'authy'); ?></div><?php
          }
        ?>
        <p class="message"><?php _e("Your administrator has requested that you add Two-Factor Authentication to your account, please enter your cellphone below to enable.", 'authy'); ?></p>
        <form method="POST" id="authy" action="wp-login.php">
          <label for="authy_user[country_code]"><?php _e( 'Country', 'authy' ); ?></label>
          <input type="text" name="authy_user[country_code]" id="authy-countries" class="input" />

          <label for="authy_user[cellphone]"><?php _e( 'Cellphone number', 'authy' ); ?></label>
          <input type="tel" name="authy_user[cellphone]" id="authy-cellphone" class="input" />
          <input type="hidden" name="username" value="<?php echo esc_attr($user->user_login); ?>"/>
          <input type="hidden" name="step" value="enable_authy"/>
          <input type="hidden" name="authy_signature" value="<?php echo esc_attr($signature); ?>"/>

          <p class="submit">
            <input type="submit" value="<?php echo _e('Enable', 'authy') ?>" id="wp_submit" class="button button-primary button-large">
          </p>
        </form>
      </div>
    </body>
  </html>
  <?php
}

/**
 * Form enable authy on profile
 * @param string $users_key
 * @param array $user_datas
 * @return string
 */
function register_form_on_profile($users_key, $user_data) {
  ?>
  <table class="form-table" id="<?php echo esc_attr( $users_key ); ?>">
    <tr>
      <th><label for="phone"><?php _e( 'Country', 'authy' ); ?></label></th>
      <td>
        <input type="text" id="authy-countries" class="small-text" name="<?php echo esc_attr( $users_key ); ?>[country_code]" value="<?php echo esc_attr( $user_data['country_code'] ); ?>" />
      </td>
    </tr>
    <tr>
      <th><label for="phone"><?php _e( 'Cellphone number', 'authy' ); ?></label></th>
      <td>
        <input type="tel" id="authy-cellphone" class="regular-text" name="<?php echo esc_attr( $users_key ); ?>[phone]" value="<?php echo esc_attr( $user_data['phone'] ); ?>" />

        <?php wp_nonce_field( $users_key . 'edit_own', $users_key . '[nonce]' ); ?>
      </td>
    </tr>
  </table>
  <?php
}

/**
 * Form disable authy on profile
 * @return string
 */
function disable_form_on_profile($users_key) {
  ?>
  <table class="form-table" id="<?php echo esc_attr( $users_key ); ?>">
    <tr>
      <th><label for="<?php echo esc_attr( $users_key ); ?>_disable"><?php _e( 'Disable Two Factor Authentication?', 'authy' ); ?></label></th>
      <td>
        <input type="checkbox" id="<?php echo esc_attr( $users_key ); ?>_disable" name="<?php echo esc_attr( $users_key ); ?>[disable_own]" value="1" />
        <label for="<?php echo esc_attr( $users_key ); ?>_disable"><?php _e( 'Yes, disable Authy for your account.', 'authy' ); ?></label>

        <?php wp_nonce_field( $users_key . 'disable_own', $users_key . '[nonce]' ); ?>
      </td>
    </tr>
  </table>
  <?php
}

/**
 * Form verify authy installation
 * @return string
 */
function authy_installation_form($user, $user_data, $user_signature, $errors) {
  ?>
  <html>
    <?php echo authy_header('verify_installation'); ?>
    <body class='login wp-core-ui'>
      <div id="authy-verify">
        <h1><a href="http://wordpress.org/" title="Powered by WordPress"><?php echo get_bloginfo('name'); ?></a></h1>
        <?php
          if ( !empty($errors) ) { ?>
            <div id="login_error"><strong><?php echo __('ERROR: ', 'authy'); ?></strong><?php echo __($errors, 'authy'); ?></div><?php
          }
        ?>
        <form method="POST" id="authy" action="wp-login.php">
          <p><?php echo _e('To activate your account you need to setup Authy Two-Factor Authentication.', 'authy'); ?></p>

          <div class='step'>
            <div class='description-step'>
              <span class='number'>1.</span>
              <span>On your phone browser go to <a href="https://www.authy.com/install" alt="install authy" style="padding-left: 18px;">https://www.authy.com/install</a></span>
            </div>
            <img src="<?php echo plugins_url('/assets/images/step1-image.png', __FILE__); ?>" alt='installation' />
          </div>

          <div class='step'>
            <div class='description-step'>
              <span class='number'>2.</span>
              <span>Open the App and register.</span>
            </div>
            <img src="<?php echo plugins_url('/assets/images/step2-image.png', __FILE__); ?>" alt='smartphones' style='padding-left: 22px;' />
          </div>

          <p class='italic-text'>
            <?php echo _e('If you don’t have an iPhone or Android ', 'authy'); ?>
            <a href="#" class="request-sms-link"
              data-username="<?php echo $user->user_login;?>"
              data-signature="<?php echo esc_attr($user_signature); ?>"><?php echo _e('click here to get the Token as a Text Message.', 'authy'); ?>
            </a>
          </p>

          <label for="authy_token">
            <?php _e( 'Authy Token', 'authy' ); ?>
            <br>
            <input type="text" name="authy_token" id="authy-token" class="input" value="" size="20" />
          </label>
          <input type="hidden" name="username" value="<?php echo esc_attr($user->user_login); ?>"/>
          <input type="hidden" name="step" value="verify_installation"/>
          <?php if(isset($user_signature)) { ?>
            <input type="hidden" name="authy_signature" value="<?php echo esc_attr($user_signature); ?>"/>
          <?php } ?>

          <input type="submit" value="<?php echo _e('Verify Token', 'authy') ?>" id="wp_submit" class="button button-primary">
          <div class="rsms">
            <img src="<?php echo plugins_url('/assets/images/phone-icon.png', __FILE__); ?>" alt="cellphone">
            <a href="#" class='request-sms-link' data-username="<?php echo $user->user_login;?>" data-signature="<?php echo esc_attr($user_signature); ?>">
              <?php echo _e('Get the token via SMS', 'authy'); ?>
            </a>
          </div>
        </form>
      </div>
    </body>
  </html>
  <?php
}