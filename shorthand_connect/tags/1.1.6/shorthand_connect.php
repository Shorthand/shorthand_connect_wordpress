<?php
/**
 * @package Shorthand Connect
 * @version 1.1.6
 */
/*
Plugin Name: Shorthand Connect
Plugin URI: http://shorthand.com/
Description: Import your Shorthand stories into your Wordpress CMS as simply as possible - magic!
Author: Shorthand
Version: 1.1.6
Author URI: http://shorthand.com
*/

$included = @include_once('config.php');
if (!$included) {
	require_once('config.default.php');
}
$version = get_option('sh_api_version');
if ($version == 'v2') {
	require_once('includes/api-v2.php');
} else {
	require_once('includes/api.php');
}
require_once('includes/shorthand_options.php');
require_once('templates/abstract.php');

/* Create the Shorthand post type */
function shand_create_post_type() {
  $permalink = get_option('sh_permalink');
  if ($permalink == '') {
  	$permalink = 'shorthand_story';
  }
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
      'supports' => array('title', 'thumbnail', 'author', 'custom-fields'),
      'register_meta_box_cb' => 'shand_add_shorthand_metaboxes',
      'menu_icon' => plugins_url( '/includes/icon.png', __FILE__ ),
      'rewrite' => array('slug' => $permalink, 'with_front' => true),
      'taxonomies' => array('category', 'post_tag'),
    )
  );

  register_taxonomy_for_object_type( 'category', 'shorthand_story' );
  register_taxonomy_for_object_type( 'post_tag', 'shorthand_story' );
}
add_action( 'init', 'shand_create_post_type' );


function shand_wpt_shorthand_story() {

	global $post;

	global $serverURL;
	global $serverv2URL;
	global $showArchivedStories;

	$baseurl = '';

	$version = get_option('sh_api_version');
	//Version 2 already has a proper URL
	if ($version != 'v2') {
		$baseurl = $serverURL;
	}


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
			height:160px;
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
		li.story label span.desc {
			display: none;
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
		#abstract {
			border:1px solid #999999;
  			width:100%;
  			height:200px;
		}
		ul.stories {
			max-height:400px;
			overflow-y:scroll;
		}
		ul.stories img {
			object-fit: cover;
			height:110px;
		}
		p.warning {
			color: red;
			font-weight: bold;
		}
	</style>
<?php

	$selected_story = get_post_meta($post->ID, 'story_id', true);
	$story_api_version = get_post_meta($post->ID, 'api_version', true);
	if ($selected_story) {
		if ($story_api_version == $version) {
			echo '<p>Clicking UPDATE will update Wordpress with the latest version of the story from Shorthand.</p>';
			echo '<input name="story_id" type="hidden" value="'.$selected_story.'" />';
		}	else {
			echo '<p class="warning">To update this story from Shorthand, please switch to the correct API version <a href="options-general.php?page=shorthand-options">here</a></p>';
			echo '<style>#publish { visibility: hidden !important; }</style>';
			echo '<input name="story_id" type="hidden" value="'.$selected_story.'" />';
		}
		return;
	}
	$stories = sh_get_stories();

	if(!is_array($stories)) {
		echo 'Could not connect to Shorthand, please check your <a href="options-general.php?page=shorthand-options">Wordpress Shorthand settings</a>.';
	} else if(sizeOf($stories) == 0) {
		echo 'You currently have no stories ready for publishing on Shorthand. Please check that your story is set to be ready for publishing.';
	} else {
		echo '<ul class="stories">';
		foreach($stories as $story) {
			$selected = '';
			$story_selected = '';
			if ($selected_story == $story->id) {
				$selected = 'checked';
				$story_selected = 'selected';
			}
			$archived = '';
			if ($version == 'v2' && isset($story->story_version) && $story->story_version == '1') {
				if ($showArchivedStories) {
					$archived = ' (archived)';
				} else {
					continue;
				}
			}
			echo '<li class="story '.$story_selected.'"><label><input name="story_id" type="radio" value="'.$story->id.'" '.$selected.' /><img width="150" src="'.$baseurl.$story->image.'" /><span class="title">'.$story->title.$archived.'</span><span class="desc">'.$story->metadata->description.'</span></a></label></li>';
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
		jQuery('input#title').val(jQuery(this).parent().find('span.title').text());
		if (jQuery('textarea#abstract').val() === '') {
			jQuery('textarea#abstract').val(jQuery(this).parent().find('span.desc').text());
		}
	});
	</script>
<?php
}

function shand_wpt_shorthand_abstract() {
	global $post;
	$abstract = get_post_meta($post->ID, 'abstract', true);
	echo '<input type="hidden" name="eventmeta_noncename" id="eventmeta_noncename" value="' .
	wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
	echo '<textarea id="abstract" name="abstract">'.esc_textarea($abstract).'</textarea>';
}

function shand_wpt_shorthand_extra_html() {
	global $post;
	$extra_html = get_post_meta($post->ID, 'extra_html', true);
	echo '<input type="hidden" name="eventmeta_noncename" id="eventmeta_noncename" value="' .
	wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
	echo '<textarea id="codearea" name="extra_html">'.esc_textarea($extra_html).'</textarea>';
}

function shand_add_shorthand_metaboxes() {
	global $version;
	global $post;
	$selected_story = get_post_meta($post->ID, 'story_id', true);
	if ($selected_story) {
		add_meta_box('shand_wpt_shorthand_story', 'Update Shorthand Story - '.$version.' (<a href="options-general.php?page=shorthand-options">change version</a>)', 'shand_wpt_shorthand_story', 'shorthand_story', 'normal', 'default');
    } else {
    	add_meta_box('shand_wpt_shorthand_story', 'Select Shorthand Story - '.$version.' (<a href="options-general.php?page=shorthand-options">change version</a>)', 'shand_wpt_shorthand_story', 'shorthand_story', 'normal', 'default');
    }
    add_meta_box('shand_wpt_shorthand_abstract', 'Add story abstract', 'shand_wpt_shorthand_abstract', 'shorthand_story', 'normal', 'default');
    add_meta_box('shand_wpt_shorthand_extra_html', 'Add additional HTML', 'shand_wpt_shorthand_extra_html', 'shorthand_story', 'normal', 'default');
}
add_action( 'add_meta_boxes', 'shand_add_shorthand_metaboxes' );


/* Save the shorthand story */
function shand_save_shorthand_story( $post_id, $post, $update ) {
	$slug = 'shorthand_story';
	if ( $slug != $post->post_type ) {
        return;
    }

    if (isset($_REQUEST['abstract'])) {
    	update_post_meta( $post_id, 'abstract', wp_kses_post($_REQUEST['abstract']) );
    }

    if (isset($_REQUEST['extra_html'])) {
    	update_post_meta( $post_id, 'extra_html', wp_kses_post($_REQUEST['extra_html']) );
		}

    if (isset($_REQUEST['story_id'])) {
			$safe_story_id = $_REQUEST['story_id'];
    	update_post_meta( $post_id, 'story_id', sanitize_text_field( $safe_story_id ) );
    	$err = sh_copy_story($post_id, $safe_story_id);
			$story_path = sh_get_story_path($post_id, $safe_story_id);

    	//Sometimes the story needs to be gotten twice
    	if(!isset($story_path)) {
    		$err = sh_copy_story($post_id, $safe_story_id);
    		$story_path = sh_get_story_path($post_id, $safe_story_id);
    	}

    	if(isset($story_path)) {
				// The story has been uploaded
    		update_post_meta($post_id, 'story_path', $story_path);

    		// Get path to the assets
				$assets_path = sh_get_story_url($post_id, $safe_story_id);

    		// Save the head and body
				$version = get_option('sh_api_version');
				update_post_meta( $post_id, 'api_version', $version);
				$head_file = $story_path.'/component_head.html';
				$article_file = $story_path.'/component_article.html';
				if ($version == 'v2') {
					$head_file = $story_path.'/head.html';
					$article_file = $story_path.'/article.html';
				}
    		$body = shand_fix_content_paths($assets_path, file_get_contents($article_file), $version);
    		update_post_meta($post_id, 'story_body', wp_slash($body));
				$head = shand_fix_content_paths($assets_path, file_get_contents($head_file), $version);
				update_post_meta($post_id, 'story_head', wp_slash($head));

    		// Save the abstract
    		$abstract = shand_fix_content_paths($assets_path, file_get_contents($article_file), $version);
    		remove_action( 'save_post', 'shand_save_shorthand_story', 10, 3);
    		$post = array(
    			'ID' => $post_id,
    			'post_content' => shand_abstract_template($post_id, wp_kses_post($_REQUEST['abstract']), $abstract)
    		);
    		wp_update_post( $post );
    		add_action( 'save_post', 'shand_save_shorthand_story', 10, 3);
    	} else {
    		echo 'Something went wrong, please try again';
    		print_r($err);
    		die();
    	}
    }
}
add_action( 'save_post', 'shand_save_shorthand_story', 10, 3);


/* Load Shorthand Template Hook */
function shand_load_single_shorthand_template($template) {
    global $post;
    if ($post->post_type == "shorthand_story"){
    	$path = locate_template(array('single-shorthand_story.php', 'templates/single-shorthand_story.php', 'template-parts/single-shorthand_story.php'));
    	if($path) {
    		return $path;
    	}
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
add_filter('single_template', 'shand_load_single_shorthand_template');


function hook_css() {
	if ( is_single() && 'shorthand_story' == get_post_type() ) {
		$meta = get_post_meta(get_post()->ID);
		echo trim($meta['story_head'][0]);
	}
}
add_action('wp_head', 'hook_css');


/* Get Posts Hook */
function shand_shorthand_get_posts( $query ) {
	if ( is_home() && $query->is_main_query() )
		$query->set( 'post_type', array( 'post', 'shorthand_story' ) );

	return $query;
}
add_filter( 'pre_get_posts', 'shand_shorthand_get_posts' );


/* Table Hook */
function shand_add_shorthand_story_columns($columns) {
		$cols = array_slice($columns, 0, 2, true) + array('story_id' => __('Shorthand Story ID')) + array('api_version' => __('API Version')) + array_slice($columns, 2, count($columns)-2, true);
    return $cols;
}
add_filter('manage_shorthand_story_posts_columns' , 'shand_add_shorthand_story_columns');


/* Table Hook */
function shand_shorthand_show_columns($name) {
    global $post;
    switch ($name) {
        case 'story_id':
            $views = get_post_meta($post->ID, 'story_id', true);
						echo $views;
						break;
				case 'api_version':
						$views = get_post_meta($post->ID, 'api_version', true);
						if ($views == '') {
							// Determine the version, save it if possible;
							$views = 'Unknown';
							$story_id = get_post_meta($post->ID, 'story_id', true);
							if ($story_id) {
								$views = determine_version_id($story_id);
							}
						}
						echo $views;
						break;
    }
}
add_action('manage_posts_custom_column',  'shand_shorthand_show_columns');


/* Filter to fix post type tags */
function shand_post_type_tags_fix($request) {
    if ( isset($request['tag']) && !isset($request['post_type']) ) {
    	$request['post_type'] = 'any';
    }
    if ( isset($request['category_name']) && !isset($request['post_type']) ) {
    	$request['post_type'] = 'any';
    }
    return $request;
}
add_filter('request', 'shand_post_type_tags_fix');


/* Activation Hook */
function shand_shorthand_activate() {
	shand_create_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'shand_shorthand_activate' );


/* UTILITY FUNCTIONS */

/* Fix content paths */
function shand_fix_content_paths($assets_path, $content, $version) {
	if ($version == 'v2') {
		$content = str_replace('./assets/', $assets_path.'/assets/', $content);
		$content = str_replace('./static/', $assets_path.'/static/', $content);
		$content = str_replace('./theme.min.css', $assets_path.'/theme.min.css', $content);
	} else {
		$content = str_replace('./static/', $assets_path.'/static/', $content);
		$content = str_replace('./media/', $assets_path.'/media/', $content);
	}
	return $content;
}

function determine_version_id($story_id) {
	if (substr($story_id, 0, 2) == 'v1') {
		return 'v1';
	}
	if (intval($story_id) > 0) {
		return 'v1';
	}
	return 'v2';
}

?>
