<?php
define( 'SHORTHAND_CONFIG_STEP1_SUCCESS', 'Successfully connected' );
define( 'SHORTHAND_CONFIG_STEP1_ERROR', 'Enter valid Shorthand API key to proceed.' );
define( 'SHORTHAND_CONFIG_STEP2_SUCCESS', 'Permalink Structure was saved.' );
define( 'SHORTHAND_CONFIG_STEP3_SUCCESS', 'Custom CSS was saved.' );
define( 'SHORTHAND_CONFIG_STEP4_SUCCESS', 'Post-processing JSON was saved.' );
define( 'SHORTHAND_CONFIG_STEP4_ERROR', 'Something went wrong. Post-processing JSON is invalid and was not saved.' );
define( 'SHORTHAND_CONFIG_STEP5_SUCCESS', 'Experimental Features were saved.' );

// Rather than running a rewrite flush everytime a post is submitted,
// run it on plugin activate/deactivate.
function shorthand_rewrite_flush() {
	shand_create_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'shorthand_rewrite_flush' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
