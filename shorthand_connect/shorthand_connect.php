<?php
/**
 * @package Shorthand Connect
 * @version 0.1
 */
/*
Plugin Name: Shorthand Connect
Plugin URI: http://shorthand.com/
Description: Import your Shorthand stories into your Wordpress CMS as simply as possible - magic!
Author: Shorthand
Version: 0.1
Author URI: http://shorthand.com
*/

add_action( 'init', 'create_post_type' );
function create_post_type() {
  register_post_type( 'shorthand_story',
    array(
      'labels' => array(
        'name' => __( 'Shorthand' ),
        'singular_name' => __( 'Shorthand Story' )
      ),
      'public' => true,
      'has_archive' => true,
    )
  );
}

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

	$valid_token = false;

	$stories = array();

	//Attempt to connect to the server
	if($token && $user_id) {
		$url = 'http://localhost:8000/api/index/';
		$vars = 'user='.$user_id.'&token='.$token;
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $vars);
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		$response = curl_exec( $ch );
		$data = json_decode($response);
		if(isset($data->stories)) {
			$valid_token = true;
			$stories = $data->stories;
		}
	}
?>
	<h3>Shorthand API Details</h3>
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
	<h3>Shorthand Stories</h3>
	<?php if ($valid_token) { ?>
		<ul>
	<?php foreach($stories as $story) { ?>
			<li><a href='<?php echo $story->id; ?>'>+ <?php echo $story->title; ?></strong></a></li>
	<?php } ?>
		</ul>
	<?php } else { ?>
		<p>Please check your token</p>
	<?php } ?>
<?php
}



add_action( 'admin_menu', 'shorthand_menu' );

?>
