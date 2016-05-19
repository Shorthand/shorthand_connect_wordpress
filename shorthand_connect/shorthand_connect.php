<?php

$included = @include_once('config.php');
if (!$included) {
	require_once('config.default.php');
}
require_once('includes/api.php');
require_once('includes/shorthand_options.php');
require_once('templates/abstract.php');
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
        'singular_name' => __( 'Shorthand Story' ),
        'add_new' => __( 'Add Shorthand Story' ),
        'add_new_item' => __( 'Add Shorthand Story' ),
        'new_item' => __( 'Add Shorthand Story' ),
        'edit_item' => __( 'Update Shorthand Story' ),
        'view_item' => __( 'View Shorthand Story' ),
        'not_found' => __( 'No stories found' ),
        'not_found_in_trash' => __( 'No stories found in trash' )
      ),
      'publicly_queryable' => true,
      'public' => true,
      'has_archive' => true,
      'menu_position' => 4,
      'supports' => array('title'),
      'register_meta_box_cb' => 'add_shorthand_metaboxes',
      'menu_icon' => get_site_url().'/wp-content/plugins/shorthand_connect/includes/icon.png',
      'taxonomies' => array('category', 'post_tag'),
    )
  );
  register_taxonomy_for_object_type( 'category', 'shorthand_story' );
  register_taxonomy_for_object_type( 'post_tag', 'shorthand_story' );
}

function post_type_tags_fix($request) {
    if ( isset($request['tag']) && !isset($request['post_type']) ) {
    	$request['post_type'] = 'any';
    }
    if ( isset($request['category_name']) && !isset($request['post_type']) ) {
    	$request['post_type'] = 'any';
    }
    return $request;
} 
add_filter('request', 'post_type_tags_fix');

function add_shorthand_metaboxes() {
    add_meta_box('wpt_shorthand_story', 'Select Shorthand Story', 'wpt_shorthand_story', 'shorthand_story', 'normal', 'default');
    add_meta_box('wpt_shorthand_extra_html', 'Add additional HTML', 'wpt_shorthand_extra_html', 'shorthand_story', 'normal', 'default');
}

add_action( 'add_meta_boxes', 'add_shorthand_metaboxes' );

function wpt_shorthand_story() {

	global $serverURL;

	$stories = sh_get_stories();
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
		ul.stories {
			max-height:400px;
			overflow-y:scroll;
		}
	</style>
<?php

	$selected_story = get_post_meta($post->ID, 'story_id', true);

	if(!$stories) {
		echo 'Could not connect to Shorthand, please check your <a href="options-general.php?page=shorthand-options">Wordpress settings</a>.';
	} else {
		echo '<ul class="stories">';
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
	}
	
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

    if (isset($_REQUEST['extra_html'])) {
    	update_post_meta( $post_id, 'extra_html', $_REQUEST['extra_html'] );
    }



    if (isset($_REQUEST['story_id'])) {
    	update_post_meta( $post_id, 'story_id', sanitize_text_field( $_REQUEST['story_id'] ) );
    	sh_copy_story($post_id, $_REQUEST['story_id']);
    	$story_path = sh_get_story_path($post_id, $_REQUEST['story_id']);

    	//Sometimes the story needs to be gotten twice
    	if(!isset($story_path)) {
    		sh_copy_story($post_id, $_REQUEST['story_id']);
    		$story_path = sh_get_story_path($post_id, $_REQUEST['story_id']);    		
    	}

    	if(isset($story_path)) {
    		// The story has been uploaded
    		update_post_meta($post_id, 'story_path', $story_path);

    		// Get path to the assets
    		$assets_path = get_site_url().substr($story_path, strpos($story_path, '/wp-content/uploads'));

    		// Save the head and body
    		$body = fix_content_paths($assets_path, file_get_contents($story_path.'/component_article.html'));
    		update_post_meta($post_id, 'story_body', $body);
			
			$head = fix_content_paths($assets_path, file_get_contents($story_path.'/component_head.html'));
			update_post_meta($post_id, 'story_head', $head);

    		// Save the abstract
    		$abstract = fix_content_paths($assets_path, file_get_contents($story_path.'/component_article.html'));
    		remove_action( 'save_post', 'save_shorthand_story', 10, 3);
    		$post = array(
    			'ID' => $post_id,
    			'post_content' => abstract_template($post_id, $abstract)
    		);
    		wp_update_post( $post );
    		add_action( 'save_post', 'save_shorthand_story', 10, 3);
    	} else {
    		echo 'Something went wrong, please try again';
    		die();
    	}
    }
}


add_action( 'save_post', 'save_shorthand_story', 10, 3);

function fix_content_paths($assets_path, $content) {
	$content = str_replace('./static/', $assets_path.'/static/', $content);
	$content = str_replace('./media/', $assets_path.'/media/', $content);
	return $content;
}



// TEMPLATE VIEW

function load_single_shorthand_template($template) {
    global $post;

    if ($post->post_type == "shorthand_story"){
        $plugin_path = plugin_dir_path( __FILE__ );
        $template_name = 'templates/single-shorthand_story.php';
        if($template === get_stylesheet_directory() . '/' . $template_name
            || !file_exists($plugin_path . $template_name)) {
            return $template;
        }
        return $plugin_path . $template_name;
    }
    return $template;
}

add_filter('single_template', 'load_single_shorthand_template');




function my_get_posts( $query ) {

	if ( is_home() && $query->is_main_query() )
		$query->set( 'post_type', array( 'post', 'shorthand_story' ) );

	return $query;
}

add_filter( 'pre_get_posts', 'my_get_posts' );


function add_shorthand_story_columns($columns) {
    //unset($columns['author']);
    $cols = array_slice($columns, 0, 2, true) + array('story_id' => __('Shorthand Story ID')) + array_slice($columns, 2, count($columns)-2, true);
    return $cols;
}
add_filter('manage_shorthand_story_posts_columns' , 'add_shorthand_story_columns');

function shorthand_show_columns($name) {
    global $post;
    switch ($name) {
        case 'story_id':
            $views = get_post_meta($post->ID, 'story_id', true);
            echo $views;
    }
}
add_action('manage_posts_custom_column',  'shorthand_show_columns');


?>
