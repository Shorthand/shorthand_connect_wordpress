<?php

/* Options */
function shorthand_menu() {
	add_options_page( 'Shorthand Options', 'Shorthand', 'manage_options', 'shorthand-options', 'shorthand_options' );
}

function shorthand_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	if( isset($_POST['sh_submit_hidden']) && $_POST['sh_submit_hidden'] == 'Y' ) {
		update_option('sh_token_key', $_POST['sh_token_key']);
	}
	$token = get_option('sh_token_key');
	if( isset($_POST['sh_submit_hidden']) && $_POST['sh_submit_hidden'] == 'Y' ) {
		update_option('sh_user_id', $_POST['sh_user_id']);
	}
	$token = get_option('sh_token_key');
	$user_id = get_option('sh_user_id');

	$profile = sh_get_profile();
?>
	<h3>Shorthand API Configuration</h3>
	<form name="form1" method="post">
		<input type="hidden" name="sh_submit_hidden" value="Y" />
		<p>
			<?php _e("Shorthand User ID:", 'sh-user-value' ); ?> 
			<input type="text" name="sh_user_id" value="<?php echo $user_id; ?>" size="20">
		</p>
		<p>
			<?php _e("Shorthand API Token:", 'sh-token-value' ); ?> 
			<input type="text" name="sh_token_key" value="<?php echo $token; ?>" size="20">
		</p>

		<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
		</p>
		<hr />
	</form>
		<h3>Shorthand Connect Status</h3>
	<?php if ($profile) { ?>
		<p class="status">Successfully connected</p>
		<img class="grav" src="<?php echo $profile->gravatar; ?>" />
		<p><strong>Username</strong>: <?php echo $profile->username; ?></p>
	<?php } else { ?>
		<p class="status warn">Not Connected</p>
	<?php } ?>
		<style>
			img.grav {
				float: left;
				width:80px;
				margin-right:10px;
			}
			p.status {
				background:#dfd;
				color:green;
				font-weight:bold;
				width:250px;
				clear:left;
				padding:5px;
			}
			p.status.warn {
				background:#ffd;
				color:orange;
			}
		</style>
<?php
}

add_action( 'admin_menu', 'shorthand_menu' );

?>