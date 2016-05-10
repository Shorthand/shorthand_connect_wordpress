<?php

require_once('api.php');
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

$serverURL = 'http://localhost:8000';

add_action( 'init', 'create_post_type' );
function create_post_type() {
  register_post_type( 'shorthand_story',
    array(
      'labels' => array(
        'name' => __( 'Shorthand' ),
        'singular_name' => __( 'Shorthand Story' ),
        'add_new' => __( 'Add Shorthand Story' ),
        'add_new_item' => __( 'Add Shorthand Story' ),
        'new_item' => __( 'Add Shorthand Story' ),
        'edit_item' => __( 'Update Shorthand Story' ),
        'view_item' => __( 'View Shorthand Story' ),
        'not_found' => __( 'No stories found' ),
        'not_found_in_trash' => __( 'No stories found in trash' )
      ),
      'public' => true,
      'has_archive' => true,
      'menu_position' => 4,
      'supports' => array('title'),
      'register_meta_box_cb' => 'add_shorthand_metaboxes',
      'menu_icon' => '/wordpress/wp-content/plugins/shorthand_connect/icon.png'
    )
  );
}

function add_shorthand_metaboxes() {
    add_meta_box('wpt_shorthand_story', 'Select Shorthand Story', 'wpt_shorthand_story', 'shorthand_story', 'normal', 'default');
    add_meta_box('wpt_shorthand_extra_html', 'Add additional HTML', 'wpt_shorthand_extra_html', 'shorthand_story', 'normal', 'default');
}

add_action( 'add_meta_boxes', 'add_shorthand_metaboxes' );

function wpt_shorthand_story() {

	global $serverURL;

	$stories = get_stories();
	global $post;

?>
	<style>
		li.story {
			float:left;
			width:160px;
			margin:5px;
		}
		li.story.selected label {
			background:#5b9dd9;
			color:#eee;
		}
		li.story input {
			display: none !important;
		}
		li.story label {
			display:block;
			background:#fafafa;
			text-align: center;
			height:140px;
			padding-top:5px;
			border:1px solid #eeeeee;
		}
		li.story label:hover {
			border:1px solid #5b9dd9;
		}
		li.story label span {
			display: block;
			font-weight: bold;
		}
		div.clear {
			clear:left;
		}
		#codearea {
			border:1px solid #999999;
  			font-family:Consolas,Monaco,Lucida Console,Liberation Mono,DejaVu Sans Mono,Bitstream Vera Sans Mono,Courier New, monospace;
  			width:100%;
  			height:300px;
		}
	</style>
<?php

	$selected_story = get_post_meta($post->ID, 'story_id', true);

	echo '<ul>';
	foreach($stories as $story) {
		$selected = '';
		$story_selected = '';
		if ($selected_story == $story->id) {
			$selected = 'checked';
			$story_selected = 'selected';
		}
		echo '<li class="story '.$story_selected.'"><label><input name="story_id" type="radio" value="'.$story->id.'" '.$selected.' /><img width="150" src="'.$serverURL.$story->image.'" /><span>'.$story->title.'</span></a></label></li>';
	}
	echo '</ul><div class="clear"></div>';
	
	// Noncename needed to verify where the data originated
	echo '<input type="hidden" name="eventmeta_noncename" id="eventmeta_noncename" value="' . 
	wp_create_nonce( plugin_basename(__FILE__) ) . '" />';

?>
	<script>
	jQuery('li.story input:radio').click(function(){
		jQuery('li.story').removeClass('selected');
		jQuery(this).parent().parent().addClass('selected');
		jQuery('label#title-prompt-text').text('');
		jQuery('input#title').val(jQuery(this).parent().find('span').text());
	});
	</script>
<?php

}

function wpt_shorthand_extra_html() {

	global $post;

	$extra_html = get_post_meta($post->ID, 'extra_html', true);

	echo '<input type="hidden" name="eventmeta_noncename" id="eventmeta_noncename" value="' . 
	wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
	echo '<textarea id="codearea" name="extra_html">'.$extra_html.'</textarea>';
}






function save_shorthand_story( $post_id, $post, $update ) {

	$slug = 'shorthand_story';

	if ( $slug != $post->post_type ) {
        return;
    }

    if (isset($_REQUEST['story_id'])) {
    	update_post_meta( $post_id, 'story_id', sanitize_text_field( $_REQUEST['story_id'] ) );
    	$story_data = get_story($_REQUEST['story_id']);
    	if(isset($story_data['path'])) {
    		update_post_meta($post_id, 'story_path', $story_data['path']);
    	}
    }

    if (isset($_REQUEST['extra_html'])) {
    	update_post_meta( $post_id, 'extra_html', sanitize_text_field( $_REQUEST['extra_html'] ) );
    }
}



add_action( 'save_post', 'save_shorthand_story', 10, 3 );



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

	$stories = get_stories();
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
	<ul>
	<?php foreach($stories as $story) { ?>
		<li><?php echo $story->title; ?></li>
	<?php } ?>
	</ul>
<?php
}

add_action( 'admin_menu', 'shorthand_menu' );






// TEMPLATE VIEW

function load_shorthand_template($template) {
    global $post;

    // Is this a "my-custom-post-type" post?
    if ($post->post_type == "shorthand_story"){

        //Your plugin path 
        $plugin_path = plugin_dir_path( __FILE__ );

        // The name of custom post type single template
        $template_name = 'single-shorthand_story.php';

        // A specific single template for my custom post type exists in theme folder? Or it also doesn't exist in my plugin?
        if($template === get_stylesheet_directory() . '/' . $template_name
            || !file_exists($plugin_path . $template_name)) {

            //Then return "single.php" or "single-my-custom-post-type.php" from theme directory.
            return $template;
        }

        // If not, return my plugin custom post type template.
        return $plugin_path . $template_name;
    }

    //This is not my custom post type, do nothing with $template
    return $template;
}
add_filter('single_template', 'load_shorthand_template');









?>
