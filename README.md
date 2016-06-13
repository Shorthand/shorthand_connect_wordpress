# Shorthand Connect plugin for Wordpress#

###Description###

This plugin provides a simple method for publishing Shorthand stories directly inside of the Wordpress CMS.  

###Status###

Unstable, early Beta.

###Usage###

1. Download the shorthand_connect directory and place it in your ```{WORDPRESS_INSTALL}/wp_content/plugins/``` directory.
2. Go to ```Wordpress Admin > Plugins``` and select ```Activate``` on Shorthand Connect
3. Go to ```Settings > Shorthand```, and enter your ```Shorthand User ID``` and ```Shorthand API Token``` (please contact us to get your User ID and Token)
4. (optional) add custom CSS here to make Shorthand stories compatible with your theme (an example is provided for the default Wordpress themes).

The Shorthand menu should now be available on the left in wp-admin, and you can add a Shorthand Story in the same way you would add a normal post/page.

###Troubleshooting###

Only try these after experiencing issues

 - Firstly make sure that your www directory is owned by the correct user
 - In your wp-config.php file, add ```define('FS_CHMOD_DIR', 0777 );```
 - Ensure CURL and PHP-CURL are installed (ala: ```sudo apt-get install php5-curl```)
 - Contact product@shorthand.com and dekker@shorthand.com for further support and feedback


###Future directions###

 - Better integration with 3rd party themes
 - A nicer feed view of the story
 - Javascript/CSS should be placed in the head rather than the body
