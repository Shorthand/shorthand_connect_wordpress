<?php

/**
 * @package Shorthand Connect
 * @version 1.3.23
 */
/*
Plugin Name: Shorthand Connect
Plugin URI: http://shorthand.com/
Description: Import your Shorthand stories into your Wordpress CMS as simply as possible - magic!
Author: Shorthand
Version: 1.3.23
Author URI: http://shorthand.com
*/

$included = @include_once('config.php');
if (!$included) {
	require_once('config.default.php');
}
$version = 'v2';

require_once('includes/api-v2.php');

require_once('includes/shorthand_options.php');
require_once('templates/abstract.php');

if ( !function_exists('WP_Filesystem') ) {
	require_once(ABSPATH . 'wp-admin/includes/file.php');
}

/* Create the Shorthand post type */
function shand_create_post_type()
{
	$permalink = get_option('sh_permalink');
	if ($permalink == '') {
		$permalink = 'shorthand_story';
	}
	register_post_type(
		'shorthand_story',
		array(
			'labels' => array(
				'name' => __('Shorthand'),
				'singular_name' => __('Shorthand Story'),
				'add_new' => __('Add Shorthand Story'),
				'add_new_item' => __('Add Shorthand Story'),
				'new_item' => __('Add Shorthand Story'),
				'edit_item' => __('Update Shorthand Story'),
				'view_item' => __('View Shorthand Story'),
				'not_found' => __('No stories found'),
				'not_found_in_trash' => __('No stories found in trash')
			),
			'publicly_queryable' => true,
			'public' => true,
			'has_archive' => true,
			'menu_position' => 4,
			'supports' => array('title', 'thumbnail', 'author', 'custom-fields'),
			'register_meta_box_cb' => 'shand_add_shorthand_metaboxes',
			'menu_icon' => plugins_url('/includes/icon.png', __FILE__),
			'rewrite' => array('slug' => $permalink, 'with_front' => true),
			'taxonomies' => array('category', 'post_tag'),
		)
	);
	
	register_taxonomy_for_object_type('category', 'shorthand_story');
	register_taxonomy_for_object_type('post_tag', 'shorthand_story');
}
add_action('init', 'shand_create_post_type');

function shand_wpt_shorthand_media_fetch(){
	global $post;

	global $serverURL;
	global $serverv2URL;
	global $showArchivedStories;

	$story_id = get_post_meta($post->ID, 'story_id', true);

	?>
		<button id="shand_test_media_fetch" class="shand_test_media_fetch" data-postid="<?php echo $post->ID; ?>" data-storyid="<?php echo $story_id; ?>">Execute Media Fetch Function</button>
		<script>
			jQuery( document ).ready( function($) {
				$( 'button.shand_test_media_fetch' ).on( 'click', function(e) {
					e.preventDefault();
					var action = jQuery(this).attr('id');
					var story_id = jQuery(this).attr('data-storyid');
					var post_id = jQuery(this).attr('data-postid');
					$.post(ajaxurl, {action, story_id, post_id}, function( data ) {
						console.log('Test: ' + action);
						console.log(data);
					} );
				} );
			} );
		</script>
	<?php
}


function shand_wpt_shorthand_story()
{

	global $post;

	global $serverURL;
	global $serverv2URL;
	global $showArchivedStories;

	$baseurl = '';

	$version = 'v2';

	$sh_debug_test = filter_var(get_option('sh_debug_test'), FILTER_VALIDATE_BOOLEAN);
	


?>
	<style>
		li.story {
			float: left;
			width: 160px;
			margin: 5px;
		}

		li.story.selected label {
			background: #5b9dd9;
			color: #eee;
		}

		li.story input {
			display: none !important;
		}

		li.story label {
			display: block;
			background: #fafafa;
			text-align: center;
			height: 160px;
			padding-top: 5px;
			border: 1px solid #eeeeee;
		}

		li.story label:hover {
			border: 1px solid #5b9dd9;
		}

		li.story label span {
			display: block;
			font-weight: bold;
		}

		li.story label span.desc {
			display: none;
		}

		div.clear {
			clear: left;
		}

		#codearea {
			border: 1px solid #999999;
			font-family: Consolas, Monaco, Lucida Console, Liberation Mono, DejaVu Sans Mono, Bitstream Vera Sans Mono, Courier New, monospace;
			width: 100%;
			height: 300px;
		}

		#abstract {
			border: 1px solid #999999;
			width: 100%;
			height: 200px;
		}

		ul.stories {
			max-height: 400px;
			overflow-y: scroll;
		}

		ul.stories img {
			object-fit: cover;
			height: 110px;
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
			echo '<p>Clicking UPDATE will update Wordpress with the latest version of the story from Shorthand unless `no_update` is set to "true"</p>';
			echo '<input name="story_id" type="hidden" value="' . esc_attr($selected_story) . '" />';
		
		return;
	}
	$stories = sh_get_stories();

	if (!is_array($stories)) {
		echo 'Could not connect to Shorthand, please check your <a href="options-general.php?page=shorthand-options">Wordpress Shorthand settings</a>.';
	} else if (sizeOf($stories) == 0) {
		echo 'You currently have no stories ready for publishing on Shorthand. Please check that your story is set to be ready for publishing.';
	} else {
		echo '<ul class="stories">';
		foreach ($stories as $story) {
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
			echo '<li class="story ' . esc_attr($story_selected) . '"><label><input name="story_id" type="radio" value="' . esc_attr($story->id) . '" ' . esc_html($selected) . ' /><img width="150" src="' . esc_url($baseurl . $story->image) . '" /><span class="title">' . esc_html($story->title . $archived) . '</span><span class="desc">' . esc_html($story->metadata->description) . '</span></a></label></li>';
		}
		echo '</ul><div class="clear"></div>';
	}

	// Noncename needed to verify where the data originated
	echo '<input type="hidden" name="eventmeta_noncename" id="eventmeta_noncename" value="' .
		esc_attr(wp_create_nonce(plugin_basename(__FILE__))) . '" />';

	?>
	<script>
		jQuery('li.story input:radio').click(function() {

			<?php if($sh_debug_test){ ?>
			//DEBUG MODE
			jQuery('button.shand_test_action').attr('data-storyid', jQuery(this).val()); // inform test buttons of selection
			jQuery('button.shand_test_action').prop('disabled', false); // enable test buttons
			<?php } ?>
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
	if($sh_debug_test){
		shand_wpt_shorthand_tests();
	}
}


function shand_add_shorthand_metaboxes()
{
	global $version;
	global $post;
	global $noabstract;
	$sh_debug_test = filter_var(get_option('sh_debug_test'), FILTER_VALIDATE_BOOLEAN);
	$selected_story = get_post_meta($post->ID, 'story_id', true);
	if ($selected_story) {
		add_meta_box('shand_wpt_shorthand_story', 'Update Shorthand Story', 'shand_wpt_shorthand_story', 'shorthand_story', 'normal', 'default');
	} else {
		add_meta_box('shand_wpt_shorthand_story', 'Select Shorthand Story', 'shand_wpt_shorthand_story', 'shorthand_story', 'normal', 'default');
	}
	if($sh_debug_test && $selected_story){
		add_meta_box('shand_wpt_shorthand_media_fetch', 'DEBUG: Manual Media Fetch (Simulate Cron Task)', 'shand_wpt_shorthand_media_fetch', 'shorthand_story', 'normal', 'default');
	}
	if (!$noabstract) {
		add_meta_box('shand_wpt_shorthand_abstract', 'Add story abstract', 'shand_wpt_shorthand_abstract', 'shorthand_story', 'normal', 'default');
	}
	add_meta_box('shand_wpt_shorthand_extra_html', 'Add additional HTML', 'shand_wpt_shorthand_extra_html', 'shorthand_story', 'normal', 'default');
}
add_action('add_meta_boxes', 'shand_add_shorthand_metaboxes');

function shand_save_media_fetch($post_id, $story_id){
	update_post_meta($post_id, 'media_status', '[Fetching media...]');
	$media = sh_copy_story($post_id, $story_id, false );
	if (isset($media['error'])) {
		$error = json_encode($media['error']);
		error_log($error);
		update_post_meta($post_id, 'media_status', $error);
	}else{
		update_post_meta($post_id, 'media_status', '[Completed]');
	}
	
	
}
add_action('sh_media_fetch', 'shand_save_media_fetch', 10, 2);


/* Save the shorthand story */
function shand_save_shorthand_story($post_id, $post, $update)
{
	WP_Filesystem();

	global $wp_filesystem;

	if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
		$creds = request_filesystem_credentials( site_url() );
		wp_filesystem( $creds );
	}

	global $noabstract;
	$slug = 'shorthand_story';
	if ($slug != $post->post_type) {
		return;
	}

	if (!$noabstract && isset($_REQUEST['abstract'])) {
		update_post_meta($post_id, 'abstract', wp_kses_post($_REQUEST['abstract']));
	} else if ($noabstract && get_post_meta($post_id, 'abstract')) {
		delete_post_meta($post_id, 'abstract');
	}

	if(!get_post_meta($post_id, 'no_update')) {
		update_post_meta($post_id, 'no_update', "false");
	}

	if (isset($_REQUEST['extra_html'])) {
		update_post_meta($post_id, 'extra_html', wp_kses_post($_REQUEST['extra_html']));
	}

	if (isset($_REQUEST['story_id']) && $_REQUEST['story_id'] !== "" && get_post_meta($post_id, 'no_update')[0] !== "true") {
		update_post_meta($post_id, 'no_update', "true");
		$sh_media_cron_offload = filter_var(get_option('sh_media_cron_offload'), FILTER_VALIDATE_BOOLEAN);
		$safe_story_id = preg_replace("/\W|_/", '', $_REQUEST['story_id']);
		update_post_meta($post_id, 'story_id', sanitize_text_field($safe_story_id));
		$err = sh_copy_story($post_id, $safe_story_id, $sh_media_cron_offload);
		$story_path = sh_get_story_path($post_id, $safe_story_id);
		//Sometimes the story needs to be gotten twice
		if (!isset($story_path)) {
			$err = sh_copy_story($post_id, $safe_story_id, $sh_media_cron_offload );
			$story_path = sh_get_story_path($post_id, $safe_story_id);
			
		}
		if($sh_media_cron_offload){
			update_post_meta($post_id, 'media_status', '[Awaiting media fetch...]');
			wp_schedule_single_event(time() + 20, 'sh_media_fetch', array( $post_id, $safe_story_id ));
		}

		if (isset($story_path) ) {
			// The story has been uploaded
			update_post_meta($post_id, 'story_path', $story_path);

			//Log any story-specific errors to the metadata 
			if(isset($err['error'])){
				update_post_meta($post_id, 'ERROR', json_encode($err));
			}else{
				delete_post_meta($post_id, 'ERROR');
			}

			// Get path to the assets
			$assets_path = sh_get_story_url($post_id, $safe_story_id);

			// Save the head and body
			$version = get_option('sh_api_version');
			update_post_meta($post_id, 'api_version', $version);
			$head_file = $story_path . '/head.html';
			$article_file = $story_path . '/article.html';

			$post_processing_queries = json_decode(base64_decode(get_option('sh_regex_list')));

			$body = shand_fix_content_paths($assets_path, defined('WPCOM_IS_VIP_ENV')? file_get_contents($article_file) : $wp_filesystem->get_contents($article_file));
			$head = shand_fix_content_paths($assets_path, defined('WPCOM_IS_VIP_ENV')? file_get_contents($head_file) 	: $wp_filesystem->get_contents($head_file));

			$body = apply_filters('sh_pre_process_body', $body, $assets_path, $article_file);
			$head = apply_filters('sh_pre_process_head', $head, $assets_path, $head_file);
			
			if(isset($post_processing_queries->body)){
				$body = shand_post_processing($body,$post_processing_queries->body);
			}
			
			if(isset($post_processing_queries->head)){
				$head = shand_post_processing($head, $post_processing_queries->head);
			}
			
			$body = apply_filters('sh_post_process_body', $body, $assets_path, $article_file);
			$head = apply_filters('sh_post_process_head', $head, $assets_path, $head_file);

			update_post_meta($post_id, 'story_body', wp_slash($body));
			update_post_meta($post_id, 'story_head', wp_slash($head));

			// Save the abstract
			if (!$noabstract) {
				$abstract = $body;
				remove_action('save_post', 'shand_save_shorthand_story', 10, 3);
				$post = array(
					'ID' => $post_id,
					'post_content' => shand_abstract_template($post_id, wp_kses_post($_REQUEST['abstract']), $abstract)
				);
				wp_update_post($post);
				add_action('save_post', 'shand_save_shorthand_story', 10, 3);
			} else {
				remove_action('save_post', 'shand_save_shorthand_story', 10, 3);
				$post = array(
					'ID' => $post_id,
					'post_content' => ''
				);
				wp_update_post($post);
				add_action('save_post', 'shand_save_shorthand_story', 10, 3);
			}

			delete_post_meta($post_id, 'story_diagnostic');

		} else {

			update_post_meta($post_id, 'story_diagnostic', $err);

			echo 'Something went wrong, please try again';
			print_r($err);
			die();
		}
	}
}
add_action('save_post', 'shand_save_shorthand_story', 10, 3);

/* Load Shorthand Template Hook */
function shand_load_single_shorthand_template($template)
{
	global $post;
	if ($post->post_type == "shorthand_story") {
		$path = locate_template(array('single-shorthand_story.php', 'templates/single-shorthand_story.php', 'template-parts/single-shorthand_story.php'));
		if ($path) {
			return $path;
		}
		$plugin_path = plugin_dir_path(__FILE__);
		$template_name = 'templates/single-shorthand_story.php';
		if (
			$template === get_stylesheet_directory() . '/' . $template_name
			|| !file_exists($plugin_path . $template_name)
		) {
			return $template;
		}
		return $plugin_path . $template_name;
	}
	return $template;
}
add_filter('single_template', 'shand_load_single_shorthand_template');


function hook_css()
{
	if (is_single() && 'shorthand_story' == get_post_type()) {
		$meta = get_post_meta(get_post()->ID);
		echo trim($meta['story_head'][0]);
	}
}
add_action('wp_head', 'hook_css');


/* Get Posts Hook */
function shand_shorthand_get_posts($query)
{
	if (is_home() && $query->is_main_query())
		$query->set('post_type', array('post', 'shorthand_story'));

	return $query;
}
add_filter('pre_get_posts', 'shand_shorthand_get_posts');


/* Table Hook */
function shand_add_shorthand_story_columns($columns)
{
	$cols = array_slice($columns, 0, 2, true) + array('story_id' => __('Shorthand Story ID')) + array('api_version' => __('API Version')) + array_slice($columns, 2, count($columns) - 2, true);
	return $cols;
}
add_filter('manage_shorthand_story_posts_columns', 'shand_add_shorthand_story_columns');


/* Table Hook */
function shand_shorthand_show_columns($name)
{
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
function shand_post_type_tags_fix($request)
{
	if (isset($request['tag']) && !isset($request['post_type'])) {
		$request['post_type'] = 'any';
	}
	if (isset($request['category_name']) && !isset($request['post_type'])) {
		$request['post_type'] = 'any';
	}
	return $request;
}
add_filter('request', 'shand_post_type_tags_fix');


/* Activation Hook */
function shand_shorthand_activate()
{
	shand_create_post_type();
	flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'shand_shorthand_activate');


/* UTILITY FUNCTIONS */

/* Fix content paths */
function shand_fix_content_paths($assets_path, $content)
{
	
	$content = str_replace('./assets/', $assets_path . '/assets/', $content);
	$content = str_replace('./static/', $assets_path . '/static/', $content);
	$content = preg_replace('/.(\/theme-\w+.min.css)/', $assets_path . '$1', $content);
	
	$content = apply_filters('shand_fix_content_paths', $content);
	
	return $content;
}

function shand_post_processing($content, $queries)
{
	if ($queries == null){
		return $content;
	}
	
	foreach ($queries as $query) {
		if(isset($query->query) && isset($query->replace)){
			$content = preg_replace($query->query, $query->replace, $content);
		}
	}

	return $content;
}

function determine_version_id($story_id)
{
	if (substr($story_id, 0, 2) == 'v1') {
		return 'v1';
	}
	if (intval($story_id) > 0) {
		return 'v1';
	}
	return 'v2';
}

/* UI AND ACTIONS FOR TESTING DOWNLOADS */


add_action('wp_ajax_shand_test_1a', 'shand_test_1a');
add_action('wp_ajax_shand_test_1b', 'shand_test_1b');
add_action('wp_ajax_shand_test_2a', 'shand_test_2a');
add_action('wp_ajax_shand_test_2b', 'shand_test_2b');
add_action('wp_ajax_shand_test_media_fetch', 'shand_test_media_fetch');

function shand_test_media_fetch(){
	global $post;
	$story_id = $_REQUEST['story_id'];
	$post_id = $_REQUEST['post_id'];
	shand_save_media_fetch($post_id, $story_id);
	die();
}

/* Show testing buttons */
function shand_wpt_shorthand_tests()
{
	global $post;
	$selected_story = get_post_meta($post->ID, 'story_id', true);

	if ($selected_story) {
		$disabled=false;
	} else {
		$disabled=true;
	}
?>
	<button id="shand_test_1a" disabled="<?php echo $disabled; ?>" class="shand_test_action" data-storyid="<?php echo $selected_story; ?>">Test 1A - save to tmp</button>
	<button id="shand_test_1b" disabled="<?php echo $disabled; ?>" class="shand_test_action" data-storyid="<?php echo $selected_story; ?>">Test 1B - save to uploads</button>
	<button id="shand_test_2a" disabled="<?php echo $disabled; ?>" class="shand_test_action" data-storyid="<?php echo $selected_story; ?>">Test 2A - (copy from uploads and) extract from tmp</button>
	<button id="shand_test_2b" disabled="<?php echo $disabled; ?>" class="shand_test_action" data-storyid="<?php echo $selected_story; ?>">Test 2B - extract from uploads</button>
	<script type="text/javascript">
	jQuery( document ).ready( function($) {
		$( 'button.shand_test_action' ).on( 'click', function(e) {
			e.preventDefault();
			var action = jQuery(this).attr('id');
			var story_id = jQuery(this).attr('data-storyid');
			$.post(ajaxurl, {action, story_id}, function( data ) {
				console.log('Test: ' + action);
				console.log(data);
			} );
		} );
	} );
	</script>
<?php
}

function shand_test_1a() {
	global $post;
	$story_id = $_REQUEST['story_id'];
	$err = sh_test_1a_fetch_story_to_tmp($post->ID ?? "unsaved", $story_id);
	if (isset($err["error"])) {
		wp_send_json_error($err, 500);
	} else {
		wp_send_json($err);
	}
	die();
}

function shand_test_1b() {
	global $post;
	$story_id = $_REQUEST['story_id'];
	$err = sh_test_1b_fetch_story_to_uploads($post->ID ?? "unsaved", $story_id);
	if (isset($err["error"])) {
		wp_send_json_error($err, 500);
	} else {
		wp_send_json($err);
	}
	die();
}

function shand_test_2a() {
	global $post;
	$story_id = $_REQUEST['story_id'];
	$err = sh_test_2a_extract_story_from_tmp($post->ID ?? "unsaved", $story_id);
	if (isset($err["error"])) {
		wp_send_json_error($err, 500);
	} else {
		wp_send_json($err);
	}
	die();
}

function shand_test_2b() {
	global $post;
	$story_id = $_REQUEST['story_id'];
	$err = sh_test_2b_extract_story_from_uploads($post->ID ?? "unsaved", $story_id);
	if (isset($err["error"])) {
		wp_send_json_error($err, 500);
	} else {
		wp_send_json($err);
	}
	die();
}

function shand_wpt_shorthand_abstract()
{
	global $post;
	$abstract = get_post_meta($post->ID, 'abstract', true);
	echo '<input type="hidden" name="eventmeta_noncename" id="eventmeta_noncename" value="' .
		esc_attr(wp_create_nonce(plugin_basename(__FILE__))) . '" />';
	echo '<textarea id="abstract" name="abstract">' . esc_textarea($abstract) . '</textarea>';
}

function shand_wpt_shorthand_extra_html()
{
	global $post;
	$extra_html = get_post_meta($post->ID, 'extra_html', true);
	echo '<input type="hidden" name="eventmeta_noncename" id="eventmeta_noncename" value="' .
		wp_create_nonce(plugin_basename(__FILE__)) . '" />';
	echo '<textarea id="codearea" name="extra_html">' . esc_textarea($extra_html) . '</textarea>';
}

?>
