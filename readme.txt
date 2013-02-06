=== Authy Two Factor Authentication ===
Contributors: authy, ethitter
Tags: authentication, authy, two factor, security, login, authenticate
Requires at least: 3.0
Tested up to: 3.5
Stable tag: 1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin add's Authy two-factor authentication to WordPress.

== Description ==
Authy helps you proctect your WordPress site from hackers using simple Two-Factor Authentication.

You can get your free API key at [www.authy.com/signup](https://www.authy.com/signup).

Plugin development is found at https://github.com/authy/authy-wordpress.

== Installation ==

1. Get your Authy API Key at [www.authy.com/signup](www.authy.com/signup).
2. Install the plugin either via your site's dashboard, or by downloading the plugin from WordPress.org and uploading the files to your server.
3. Activate plugin through the WordPress Plugins menu.
4. Navigate to **Settings -> Authy** to enter your Authy API keys.

== Frequently Asked Questions ==

= How can a user enable Two-Factor Authentication? =
The user should go to WordPress profile page and add his mobile number and country code.

= How can a user disable Authy after enabling it? =
The user should return to his or her WordPress profile screen and disable Authy at the bottom.

= Can an Admin can select specific user roles that should authenticate with Authy Two Factor Authentication? =
Yes, as an admin you can go to settings page of plugin, select the user roles in the list and click Save Changes to save configuration.

= How can the admin an admin force Authy Two Factor Authentication on a specific user? =
As an admin you can go to users page. Then select the user in the list and click edit. Go to the bottom an enter the user mobile number and contry code and click update user.

== Screenshots ==
1. Authy Two-Factor Authentication page.

== Changelog ==

= 1.3 =
Display API errors when try to register a user

= 1.2 =
Fix update user profile and verify SSL certificates

= 1.1 =
Fix reported issues and refactor code

= 1.0 =
* Initial public release.