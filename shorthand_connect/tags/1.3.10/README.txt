=== Shorthand Connect ===
Contributors: shorthandconnect
Donate link:
Tags: shorthand, api
Requires at least: 4
Tested up to: 5.9.1
Stable tag: 1.3.10
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin provides a simple method for publishing and updating Shorthand stories directly inside of Wordpress.

== Description ==

This plugin will allow premium Shorthand users to connect their Wordpress installation to Shorthand (http://app.shorthand.com).  This will allow users to single click publish Shorthand stories into Wordpress.

== Installation ==

1. Install the search for the plugin and install it within wp-admin.
  - Or alternately upload `shorthand_connect` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings -> Shorthand to add your Shorthand API Token retrieved in the next step.
3. Get your API token from Shorthand.com: (Only Admin/Owners will have access to generate API Tokens)
  - Open the workspace settings; either by clicking your organisation name from the dashboard OR from the top right menu when editing a story.
  - Under the sub-heading API Tokens generate either a Team Token or Workspace Token.
     - A Team Token only has access to published/prepared stories under the associated team.
     - A Workspace Token has access to all published/prepared stories.
4. Optionally change the CSS settings in the options to best present stories within your theme.
5. Optionally your theme can override the display of Shorthand stories via `single-shorthand_story.php` in your theme directory.

== Updating ==
https://wordpress.org/plugins/about/svn/

== Screenshots ==

1. Coming soon

== Changelog ==

= 1.3.10 =
* Updated Installation instructions (Documentation only)

= 1.3.9 =
* Updated Contact emails

= 1.3.8 =
* Updated Signed URLs for thumbnails

= 1.3.7 =
* Updates to internal php and API handling

= 1.3.6 =
* Better sanitize input

= 1.3.2 =
* Add support for hashed themes

= 1.2.1 =
* Add the ability to remove abstract from post (useful for wordpress as a backend)

= 1.2.0 =
* Support for Wordpress v5
* Clean up settings
* Remove cURL

= 1.1.8 =
* Update stable tag

= 1.1.7 =
* Update testing information
* Default to v2 in new install settings
* Fix a bug where v1 stories were not identified correctly

= 1.1.6 =
* Better support for switching between v1 and v2 of the API

= 1.1.5 =
* Support for the latest release of Shorthand 2.0

= 1.1.4 =
* Support author and custom-field in post type

= 1.1.3 =
* Enable Shorthand version selection

= 1.1.2 =
* Fix release issues

= 1.1.1 =
* Plugin release for version 2.0 support

= 1.1.0 =
* Support for Shorthand 2.0

= 1.0.7 =
* Bump version number

= 1.0.6 =
* Don't show all stories on update story
* Allow single-shorthand_story.php to be overwritten
* Put meta-data in the correct location (inside the head)

= 1.0.5 =
* Add stable tag.

= 1.0.4 =
* Add support for post feature image (thumbnail).

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

 - A nicer feed view of the story

== HTTPS ==

 - If you are experiencing issues related to assets being loaded via http (and your site is using https), check your settings and ensure that the site URLs are set to https.

== Troubleshooting ==

Only try these after experiencing issues

 - Firstly make sure that your www directory is owned by the correct user
 - In your wp-config.php file, add `define('FS_CHMOD_DIR', 0777 )`;
 - Contact help@shorthand.com and ben@shorthand.com for further support and feedback
 - Some WP Plugins can aggressively cache or control how content is displayed at the theme-level; check page templates to ensure nothing is conflicting.
