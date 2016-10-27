=== Shorthand Connect ===
Contributors: shorthandconnect
Donate link:
Tags: shorthand, api
Requires at least: 4
Tested up to: 4.6.1
Stable tag: 1.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin provides a simple method for publishing and updating Shorthand stories directly inside of Wordpress.

== Description ==

This plugin will allow premium Shorthand users to connect their Wordpress installation to Shorthand (http://app.shorthand.com).  This will allow users to single click publish Shorthand stories into Wordpress.

== Installation ==

1a. Install the search for the plugin and install it within wp-admin:
1b. Or, Upload `shorthand_connect` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Shorthand and enter in your user ID and API Key (get these from your account page in Wordpress).
4. Optionally change the CSS settings in the options to best present stories within your theme.

== Screenshots ==

1. Coming soon

== Changelog ==

= 1.0.3 =
* Update description.

= 1.0.2 =
* Change author and instructions.

= 1.0.1 =
* Sanitize form inputs.

= 1.0 =
* First release.

== Upgrade notice ==
 - N/A

== Frequently Asked Questions ==
 - If you have any questions, please contact help@shorthand.com

== Future Directions ==

 - Better integration with 3rd party themes
 - A nicer feed view of the story
 - Javascript/CSS should be placed in the head rather than the body

== Troubleshooting ==

Only try these after experiencing issues

 - Firstly make sure that your www directory is owned by the correct user
 - In your wp-config.php file, add `define('FS_CHMOD_DIR', 0777 )`;
 - Ensure CURL and PHP-CURL are installed (ala: `sudo apt-get install php5-curl`)
 - Contact product@shorthand.com and dekker@shorthand.com for further support and feedback
