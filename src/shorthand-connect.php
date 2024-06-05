<?php
/**
 * @package Shorthand Connect
 * @version 1.3.31
 */

/*
Plugin Name: Shorthand Connect
Plugin URI: http://shorthand.com/
Description: Import your Shorthand stories into your WordPress CMS as simply as possible - magic!
Author: Shorthand
Version: 1.3.31
Author URI: http://shorthand.com
*/

if ( file_exists( plugin_dir_path( __FILE__ ) . 'config.php' ) ) {
	include_once plugin_dir_path( __FILE__ ) . 'config.php';
} else {
	include_once plugin_dir_path( __FILE__ ) . 'config.default.php';
}
require_once plugin_dir_path( __FILE__ ) . 'includes/api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mass-pull.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/shorthand-options.php';
require_once plugin_dir_path( __FILE__ ) . 'templates/abstract.php';

if ( ! function_exists( 'WP_Filesystem' ) ) {
	include_once ABSPATH . 'wp-admin/includes/file.php';
}

/**
 * Creates the Shorthand post type.
 */
function shand_create_post_type() {
	$permalink = get_option( 'sh_permalink' );
	if ( '' === $permalink ) {
		$permalink = 'shorthand_story';
	}

	register_post_type(
		'shorthand_story',
		array(
			'labels'               => array(
				'name'               => __( 'Shorthand' ),
				'singular_name'      => __( 'Shorthand Story' ),
				'add_new'            => __( 'Add Shorthand Story' ),
				'add_new_item'       => __( 'Add Shorthand Story' ),
				'new_item'           => __( 'Add Shorthand Story' ),
				'edit_item'          => __( 'Update Shorthand Story' ),
				'view_item'          => __( 'View Shorthand Story' ),
				'not_found'          => __( 'No stories found' ),
				'not_found_in_trash' => __( 'No stories found in trash' ),
			),
			'publicly_queryable'   => true,
			'public'               => true,
			'has_archive'          => true,
			'menu_position'        => 4,
			'supports'             => array( 'title', 'thumbnail', 'author', 'custom-fields' ),
			'register_meta_box_cb' => 'shand_add_shorthand_metaboxes',
			'menu_icon'            => plugins_url( '/includes/icon.png', __FILE__ ),
			'rewrite'              => array(
				'slug'       => $permalink,
				'with_front' => true,
			),
			'taxonomies'           => array( 'category', 'post_tag' ),
		)
	);

	register_taxonomy_for_object_type( 'category', 'shorthand_story' );
	register_taxonomy_for_object_type( 'post_tag', 'shorthand_story' );
}

add_action( 'init', 'shand_create_post_type' );

function shand_wpt_shorthand_story() {
	global $post;
	global $server_url;
	global $show_archived_stories;
	$baseurl = '';

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

	div.publishing-actions {
		padding: 10px;
		clear: both;
		border-top: 1px solid #dcdcde;
		background: #f6f7f7;
		margin: -12px;
		margin-top: -12px;
		margin-top: 10px;
		display: flex;
		justify-content: center;
	}

	.button-shorthand {
		background-color: #000;
		border: 1px solid #000;
		transition: background-color .15s ease, color .15s ease;
		color: #fff;
		font-family: poppins, sans-serif;
		font-size: 13px;
		font-weight: 600;
		letter-spacing: 0;
		line-height: 1;
		padding: 1em 2em;
		text-decoration: none;
		cursor: pointer;
		border-radius: .9em;
	}

	.button-shorthand:hover {
		background-color: #fff;
		border-color: #000;
		color: #000;
	}

	.button-shorthand:disabled {
		pointer-events: none;
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

	$selected_story = get_post_meta( $post->ID, 'story_id', true );
	if ( $selected_story ) {
		shand_wpt_update_story( $selected_story );
		return;
	}
	$stories = sh_get_stories();
	$profile = sh_get_profile();

	if ( ! ( $profile ) ) {
		echo 'Could not connect to Shorthand, please check your API token in <a href="options-general.php?page=shorthand-options">Shorthand settings</a>.';
	} elseif ( $stories === null ) {
		echo 'You currently have no stories ready for publishing on Shorthand. Please check that your story is set to be ready for publishing.';
	} else {
		echo '<ul class="stories">';
		foreach ( $stories as $story ) {
			$selected       = '';
			$story_selected = '';
			if ( $selected_story === $story->id ) {
				$selected       = 'checked';
				$story_selected = 'selected';
			}
			$archived = '';
			if ( isset( $story->story_version ) && '1' === $story->story_version ) {
				if ( $show_archived_stories ) {
					$archived = ' (archived)';
				} else {
					continue;
				}
			}
			echo '<li class="story ' . esc_attr( $story_selected ) . '"><label><input name="story_id" type="radio" value="' . esc_attr( $story->id ) . '" ' . esc_html( $selected ) . ' /><img width="150" src="' . esc_url( $baseurl . $story->image ) . '" /><span class="title">' . esc_html( $story->title . $archived ) . '</span><span class="desc">' . esc_html( $story->metadata->description ) . '</span></a></label></li>';
		}
		echo '</ul><div class="clear"></div>';
	}

	// Noncename needed to verify where the data originated
	echo '<input type="hidden" name="eventmeta_noncename" id="eventmeta_noncename" value="' .
	esc_attr( wp_create_nonce( plugin_basename( __FILE__ ) ) ) . '" />';

	?>
	<script>
	jQuery('li.story input:radio').click(function () {
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

/* Show update UI */
function shand_wpt_update_story( $story_id ) {
	?>
	<p>This will update WordPress with the latest version of the story from Shorthand.</p>
	<?php wp_nonce_field( 'shand_update_story', 'shand_update_story_nonce' ); ?>

	<div class="publishing-actions">
	<input name="story_id" type="hidden" value="<?php esc_attr_e( $story_id ); ?>"/>
	<input
		id="shorthand_update"
		name="save"
		class="button-shorthand"
		type="submit"
		value="Update Shorthand Story"
		formaction="?shand_update"
	/>

	<script>
		jQuery('#post').submit(function () {
		jQuery('#shorthand_update').prop('disabled', true);
		});
	</script>

	</div>

	<?php
}

function shand_add_shorthand_metaboxes() {
	global $post;
	global $noabstract;
	$selected_story = get_post_meta( $post->ID, 'story_id', true );
	if ( $selected_story ) {
		add_meta_box(
			'shand_wpt_shorthand_story_update',
			'Update Shorthand Story',
			'shand_wpt_shorthand_story',
			'shorthand_story',
			'side',
			'high'
		);
	} else {
		add_meta_box(
			'shand_wpt_shorthand_story',
			'Select Shorthand Story',
			'shand_wpt_shorthand_story',
			'shorthand_story',
			'normal',
			'default'
		);
	}
	if ( ! $noabstract ) {
		add_meta_box(
			'shand_wpt_shorthand_abstract',
			'Add story abstract',
			'shand_wpt_shorthand_abstract',
			'shorthand_story',
			'normal',
			'default'
		);
	}
	add_meta_box( 'shand_wpt_shorthand_extra_html', 'Add additional HTML', 'shand_wpt_shorthand_extra_html', 'shorthand_story', 'normal', 'default' );
}

add_action( 'add_meta_boxes', 'shand_add_shorthand_metaboxes' );

function shand_save_media_fetch( $post_id, $story_id ) {
	update_post_meta( $post_id, 'media_status', '[Fetching media...]' );
	$media = sh_copy_story( $post_id, $story_id, false, true );
	if ( isset( $media['error'] ) ) {
		$error = wp_json_encode( $media['error'] );
		update_post_meta( $post_id, 'media_status', $error );
	} else {
		update_post_meta( $post_id, 'media_status', '[Completed]' );
	}
}

add_action( 'sh_media_fetch', 'shand_save_media_fetch', 10, 2 );

/* Save the shorthand story */
function shand_save_shorthand_story( $post_id, $post ) {
	WP_Filesystem();
	global $wp_filesystem;

	if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
		$creds = request_filesystem_credentials( site_url() );
		wp_filesystem( $creds );
	}

	global $noabstract;
	$slug = 'shorthand_story';
	if ( $slug !== $post->post_type ) {
		return;
	}

	// Check if these fields are nonce_verified.
	if ( isset( $_POST['eventmeta_noncename'] ) && wp_verify_nonce( sanitize_text_field( $_POST['eventmeta_noncename'] ), plugin_basename( __FILE__ ) ) ) {
		if ( ! $noabstract && isset( $_REQUEST['abstract'] ) ) {
			update_post_meta( $post_id, 'abstract', wp_kses_post( $_REQUEST['abstract'] ) );
		} elseif ( $noabstract && get_post_meta( $post_id, 'abstract' ) ) {
			delete_post_meta( $post_id, 'abstract' );
		}

		if ( ! get_post_meta( $post_id, 'no_update' ) ) {
			update_post_meta( $post_id, 'no_update', 'false' );
		}

		if ( isset( $_REQUEST['extra_html'] ) ) {
			update_post_meta( $post_id, 'extra_html', wp_kses_post( $_REQUEST['extra_html'] ) );
		}
	}

	$do_update_story = isset( $_REQUEST['shand_update'] ) || get_post_meta( $post_id, 'no_update', true ) !== 'true';

	if ( isset( $_REQUEST['story_id'] ) && '' !== $_REQUEST['story_id'] && $do_update_story ) {
		update_post_meta( $post_id, 'no_update', 'true' );
		$sh_media_cron_offload = filter_var( get_option( 'sh_media_cron_offload' ), FILTER_VALIDATE_BOOLEAN );

		// Sanitize but also check if the query is GET or POST.
		if ( isset( $_REQUEST['story_id'] ) ) {
			$story_id = filter_input( INPUT_GET, 'story_id', FILTER_SANITIZE_STRING );
			// If the variable is not present in the $_GET array.
			if ( null === $story_id ) {
				$story_id = filter_input( INPUT_POST, 'story_id', FILTER_SANITIZE_STRING );
			}
			$safe_story_id = preg_replace( '/\W|_/', '', $story_id );
		}

		update_post_meta( $post_id, 'story_id', sanitize_text_field( $safe_story_id ) );
		$err        = sh_copy_story( $post_id, $safe_story_id, $sh_media_cron_offload );
		$story_path = sh_get_story_path( $post_id, $safe_story_id );

		// Sometimes the story needs to be gotten twice.
		if ( ! isset( $story_path ) ) {
			$err        = sh_copy_story( $post_id, $safe_story_id, $sh_media_cron_offload );
			$story_path = sh_get_story_path( $post_id, $safe_story_id );
		}

		if ( isset( $story_path ) ) {
			// The story has been uploaded.
			update_post_meta( $post_id, 'story_path', $story_path );

			// Log any story-specific errors to the metadata.
			if ( isset( $err['error'] ) ) {
				update_post_meta( $post_id, 'ERROR', wp_json_encode( $err ) );
			} else {
				delete_post_meta( $post_id, 'ERROR' );
			}

			if ( $sh_media_cron_offload ) {
				update_post_meta( $post_id, 'media_status', '[Awaiting media fetch...]' );
				wp_schedule_single_event( time() + 30, 'sh_media_fetch', array( $post_id, $safe_story_id ) );
			}

			// Get path to the assets.
			$assets_path = shorthand_get_story_url( $post_id, $safe_story_id );

			// Save the head and body.
			$head_file    = $story_path . '/head.html';
			$article_file = $story_path . '/article.html';

			$post_processing_queries = json_decode( base64_decode( get_option( 'sh_regex_list' ) ) );

			$body = shand_fix_content_paths( $assets_path, $err['article'] );
			$head = shand_fix_content_paths( $assets_path, $err['head'] );

			$body = apply_filters( 'sh_pre_process_body', $body, $assets_path, $article_file );
			$head = apply_filters( 'sh_pre_process_head', $head, $assets_path, $head_file );

			if ( isset( $post_processing_queries->body ) ) {
				$body = shand_post_processing( $body, $post_processing_queries->body );
			}

			if ( isset( $post_processing_queries->head ) ) {
				$head = shand_post_processing( $head, $post_processing_queries->head );
			}

			$body = apply_filters( 'sh_post_process_body', $body, $assets_path, $article_file );
			$head = apply_filters( 'sh_post_process_head', $head, $assets_path, $head_file );

			update_post_meta( $post_id, 'story_body', wp_slash( $body ) );
			update_post_meta( $post_id, 'story_head', wp_slash( $head ) );

			// Save the abstract.
			if ( ! $noabstract ) {
				$abstract = $body;
				remove_action( 'save_post', 'shand_save_shorthand_story', 10, 3 );
				$post = array(
					'ID'           => $post_id,
					'post_content' => shand_abstract_template( $post_id, wp_kses_post( $_REQUEST['abstract'] ), $abstract ),
				);
				wp_update_post( $post );
				add_action( 'save_post', 'shand_save_shorthand_story', 10, 3 );
			} else {
				remove_action( 'save_post', 'shand_save_shorthand_story', 10, 3 );
				$post = array(
					'ID'           => $post_id,
					'post_content' => '',
				);
				wp_update_post( $post );
				add_action( 'save_post', 'shand_save_shorthand_story', 10, 3 );
			}

			delete_post_meta( $post_id, 'story_diagnostic' );

		} else {
			// Log any story-specific errors to the metadata.
			update_post_meta( $post_id, 'ERROR', wp_json_encode( $err ) );
			update_post_meta( $post_id, 'story_diagnostic', $err );

			wp_die(
				isset( $err['error'] ) ? esc_html( __( $err['error'] ) ) : '',
				isset( $err['pretty'] ) ? esc_html( __( $err['pretty'] ) ) : ''
			);
		}
	}
}

add_action( 'save_post', 'shand_save_shorthand_story', 10, 3 );

/* Load Shorthand Template Hook */
function shand_load_single_shorthand_template( $template ) {
	global $post;
	if ( 'shorthand_story' === $post->post_type ) {
		$path = locate_template( array( 'single-shorthand_story.php', 'templates/single-shorthand_story.php', 'template-parts/single-shorthand_story.php' ) );
		if ( $path ) {
			return $path;
		}
		$plugin_path   = plugin_dir_path( __FILE__ );
		$template_name = 'templates/single-shorthand_story.php';
		if ( ( get_stylesheet_directory() . '/' . $template_name === $template )
			|| ! file_exists( $plugin_path . $template_name )
		) {
			return $template;
		}
		return $plugin_path . $template_name;
	}
	return $template;
}

add_filter( 'single_template', 'shand_load_single_shorthand_template' );

/**
 * Outputs header tags.
 */
function hook_css() {
	if ( is_single() && 'shorthand_story' === get_post_type() ) {
		$meta = get_post_meta( get_post()->ID );
		echo get_shorthandinfo( $meta, 'story_head' );
	}
}

add_action( 'wp_head', 'hook_css' );

/* Get Posts Hook */
function shand_shorthand_get_posts( $query ) {
	if ( is_admin() ) {
		return $query;
	}

	// Check if the query is the main query and it's for the front-end.
	if ( $query->is_main_query() && ! is_admin() ) {
		$queried_object      = get_queried_object();
		$shorthand_templates = array( 'single-shorthand_story.php', 'templates/single-shorthand_story.php', 'template-parts/single-shorthand_story.php' );

		// Check if the queried object uses a Shorthand Post template from the array.
		if ( $queried_object
			&& isset( $queried_object->ID )
			&& in_array( get_page_template_slug( $queried_object->ID ), $shorthand_templates, true )
		) {

			// Get the current post type(s).
			$post_type = $query->get( 'post_type' );

			// If the post type hasn't been modified, then add the shorthand post type.
			if ( empty( $post_type ) || ( 'post' === $post_type ) ) {
				$query->set( 'post_type', array( 'post', 'shorthand_story' ) );
			}
		}
	}
	return $query;
}

add_filter( 'pre_get_posts', 'shand_shorthand_get_posts' );

/* Table Hook */
function shand_add_shorthand_story_columns( $columns ) {
	return array_slice( $columns, 0, 2, true ) + array( 'story_id' => __( 'Shorthand Story ID' ) ) + array_slice( $columns, 2, count( $columns ) - 2, true );
}

add_filter( 'manage_shorthand_story_posts_columns', 'shand_add_shorthand_story_columns' );

/**
 * Fix post type tags.
 *
 * @param array $request Request.
 */
function shand_post_type_tags_fix( $request ) {
	if ( isset( $request['tag'] ) && ! isset( $request['post_type'] ) ) {
		$request['post_type'] = 'any';
	}
	if ( isset( $request['category_name'] ) && ! isset( $request['post_type'] ) ) {
		$request['post_type'] = 'any';
	}
	return $request;
}

add_filter( 'request', 'shand_post_type_tags_fix' );

/**
 * Activates plugin by creating post type.
 */
function shand_shorthand_activate() {
	shand_create_post_type();
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'shand_shorthand_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/**
 * Update links in content.
 *
 * @param string $assets_path Path string to use.
 * @param string $content     HTML content to be modified.
 */
function shand_fix_content_paths( $assets_path, $content ) {
	$content = str_replace( './assets/', $assets_path . '/assets/', $content );
	$content = str_replace( './static/', $assets_path . '/static/', $content );
	$content = preg_replace( '/.(\/theme-\w+.min.css)/', $assets_path . '$1', $content );
	$content = apply_filters( 'shand_fix_content_paths', $content );
	return $content;
}

/**
 * Generates form elements for abstract.
 *
 * @param string $content Content to output.
 * @param array  $queries Queries.
 */
function shand_post_processing( $content, $queries ) {
	if ( ! $queries ) {
		return $content;
	}
	foreach ( $queries as $query ) {
		if ( isset( $query->query ) && isset( $query->replace ) ) {
			$content = preg_replace( $query->query, $query->replace, $content );
		}
	}
	return $content;
}

/**
 * Generates form elements for abstract.
 */
function shand_wpt_shorthand_abstract() {
	global $post;
	$abstract = get_post_meta( $post->ID, 'abstract', true );
	echo '<input type="hidden" name="eventmeta_noncename" id="eventmeta_noncename" value="' .
	esc_attr( wp_create_nonce( plugin_basename( __FILE__ ) ) ) . '" />';
	echo '<textarea id="abstract" name="abstract">' . esc_textarea( $abstract ) . '</textarea>';
}

/**
 * Generates form elements for extra HTML.
 */
function shand_wpt_shorthand_extra_html() {
	global $post;
	$extra_html = get_post_meta( $post->ID, 'extra_html', true );
	echo '<input type="hidden" name="eventmeta_noncename" id="eventmeta_noncename" value="' .
	esc_attr( wp_create_nonce( plugin_basename( __FILE__ ) ) ) . '" />';
	echo '<textarea id="codearea" name="extra_html">' . esc_textarea( $extra_html ) . '</textarea>';
}

/**
 * Removes meta box.
 */
function remove_wp_meta_box() {
	if ( function_exists( 'acf' ) ) {
		// Check if ACF is installed and enabled.
		$sh_disable_acf = filter_var( get_option( 'sh_disable_acf' ), FILTER_VALIDATE_BOOLEAN );
		if ( $sh_disable_acf ) {
			add_filter( 'acf/settings/remove_wp_meta_box', '__return_false' );
		}
	}
}

add_action( 'acf/init', 'remove_wp_meta_box' );

/* Add "Pull Story" to post dropdown */
add_filter(
	'bulk_actions-edit-shorthand_story',
	function ( $bulk_actions ) {
		$bulk_actions['bulk-pull-stories'] = __( 'Pull Story', 'txtdomain' );
		return $bulk_actions;
	}
);

/* Pull the stories */
add_filter(
	'handle_bulk_actions-edit-shorthand_story',
	function ( $redirect_url, $action, $post_ids ) {
		// Run on posts which have bulk-pull-stories set to true.
		if ( 'bulk-pull-stories' === $action ) {
			$story_ids = array();
			foreach ( $post_ids as $post_id ) {
				// Get story ID and push to array.
				$story_id = get_post_meta( $post_id, 'story_id', true );
				array_push( $story_ids, $story_id );

				// copy latest zip files.
				sh_copy_story( $post_id, $story_id, 'true', 'false' );

				// Update Meta Data.
				shand_update_story( $post_id, $story_id );
			}
			// Change to bulk-pulled-stories for notice below.
			$redirect_url = add_query_arg( 'bulk-pulled-stories', count( $post_ids ), $redirect_url );
		}
		$redirect_url = add_query_arg( 'bulk-pulled-stories-nonce', wp_create_nonce( 'bulk-pulled-stories' ), $redirect_url );
		return $redirect_url;
	},
	10,
	3
);

/* Add notice after post has been pulled. */
add_action(
	'admin_notices',
	function () {
		if ( isset( $_REQUEST['bulk-pulled-stories-nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['bulk-pulled-stories-nonce'] ) ), 'bulk-pulled-stories' )
			&& isset( $_REQUEST['bulk-pulled-stories'] )
		) {
			$num_changed = intval( $_REQUEST['bulk-pulled-stories'] );
			echo '<div id="message" class="updated notice is-dismissable"><p>',
			/* translators: Pulled %d stories. */
			sprintf( esc_html( _n( 'Pulled %d story.', 'Pulled %d stories.', $num_changed ) ), esc_html( $num_changed ) ),
			'</p></div>';
		}
	}
);

/**
 * Retrieves information about the current story.
 *
 * @param  string $meta Meta value.
 * @param  string $show Optional. Site info to retrieve. Default empty (site name).
 * @return string HTML or text.
 */
function get_shorthandinfo( $meta, $show = '' ) {
	$output = '';
	if ( isset( $meta[ $show ] ) ) {
		$output = trim( $meta[ $show ][0] );
	}
	return $output;
}

/**
 * Retrieves information about the current story.
 *
 * @param  string $show Optional. Shorthand option to retrieve. Default empty (site name).
 * @return string HTML or text.
 */
function get_shorthandoption( $show = '' ) {
	$output = get_option( $show );
	return $output;
}

/**
 * Retrieves information about the current story.
 *
 * @return string HTML form.
 */
function get_shorthand_password_form() {
	return get_the_password_form();
}
