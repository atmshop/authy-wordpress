=== Authy for WordPress ===
Contributors: ethitter
Tags: authentication, authy, two factor
Requires at least: 3.5
Tested up to: 3.5
Stable tag: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add Authy two-factor authentication to WordPress. Users opt in for an added level of security that relies on random codes from their mobile devices.

== Description ==
Enable the plugin, enter your [Authy](http://www.authy.com/) API keys, and your users can enable Authy on their accounts.

Once users configure Authy through their WordPress user profiles, any login attempts will require an Authy token in addition to the account username and password.

== Installation ==

1. Install the plugin either via your site's dashboard, or by downloading the plugin from WordPress.org and uploading the files to your server.
2. Activate plugin through the WordPress Plugins menu.
3. Navigate to Settings > Authy for WP to enter your Authy API keys.

== Frequently Asked Questions ==

= How can a user disable Authy after enabling it? =
The user should return to his or her WordPress profile screen and manage connections under the section **Authy for WordPress**.

= What if a user loses the mobile device? =
Any administrator (anyone with the `create_users` capability, actually) can disable Authy on a given user account by navigating to that user's WordPress account profile, and following the instructions under **Authy for WordPress**.

== Changelog ==

= 0.1 =
* Initial public release.