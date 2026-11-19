<?php
/**
 * Main plugin file.
 *
 * @package Shorthand Connect
 */

/*
Plugin Name: Shorthand Connect
Plugin URI: http://shorthand.com/
Description: Import your Shorthand stories into your WordPress CMS as simply as possible - magic!
Author: Shorthand
Version: 1.3.49
Author URI: http://shorthand.com
*/

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'SHORTHAND_VERSION', '1.3.49' );
define( 'SHORTHAND__MINIMUM_WP_VERSION', '6.7.2' );
define( 'SHORTHAND__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once SHORTHAND__PLUGIN_DIR . 'class-shorthand.php';

// Add config.
if ( file_exists( SHORTHAND__PLUGIN_DIR . 'config.php' ) ) {
	include_once SHORTHAND__PLUGIN_DIR . 'config.php';
} else {
	include_once SHORTHAND__PLUGIN_DIR . 'config-default.php';
}

require_once SHORTHAND__PLUGIN_DIR . 'includes/api.php';
require_once SHORTHAND__PLUGIN_DIR . 'includes/wp-api.php';
require_once SHORTHAND__PLUGIN_DIR . 'includes/mass-pull.php';
require_once SHORTHAND__PLUGIN_DIR . 'includes/projects.php';

if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	require_once SHORTHAND__PLUGIN_DIR . 'class-shorthand-admin.php';
	add_action( 'init', array( 'Shorthand_Admin', 'init' ) );
}

// Loading initial setup or general configuration.
require_once SHORTHAND__PLUGIN_DIR . 'includes/shorthand-options-variables.php';
if ( isset( $_GET['view'] ) && ( $_GET['view'] === 'start' )) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	require_once SHORTHAND__PLUGIN_DIR . 'includes/shorthand-options-init.php';
} else {
	require_once SHORTHAND__PLUGIN_DIR . 'includes/shorthand-options.php';
}

// Added admin menu.
function shorthand_shorthand_menu() {
	add_options_page( 'Shorthand Options', 'Shorthand', 'manage_options', 'shorthand-options', 'shorthand_shorthand_options' );
}
add_action( 'admin_menu', 'shorthand_shorthand_menu' );

require_once SHORTHAND__PLUGIN_DIR . 'templates/abstract.php';

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

function shorthand_wpt_shorthand_story() {
	global $post;
	global $show_archived_stories;

	// Adding styles.
	$css_path = 'css/connect.css';
	wp_register_style( 'connect_style', plugin_dir_url( __FILE__ ) . $css_path, array(), SHORTHAND_VERSION, 'all' );
	wp_enqueue_style( 'connect_style' );

	$selected_story = get_post_meta( $post->ID, 'story_id', true );
	if ( $selected_story ) {
		shand_wpt_update_story( $selected_story );
		return;
	}

	$profile = shorthand_api_get_profile();

	if ( ! ( $profile ) ) {
		$uri = Shorthand_Admin::get_page_url();
		printf( wp_kses( __( 'Could not connect to Shorthand, please check your API token in <a alt="(opens Shorthand Connect plugin settings)" href="%s">Shorthand settings</a>.' ) ), esc_url( $uri ) );
	} else {
		$can_import_projects = current_user_can( 'manage_options' );
		$active_tab          = isset( $_GET['shorthand_tab'] ) ? sanitize_key( wp_unslash( $_GET['shorthand_tab'] ) ) : 'stories'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab          = $can_import_projects && 'projects' === $active_tab ? 'projects' : 'stories';
		?>
	<div id="shorthand-content-picker">
		<input id="shorthand-project-id" name="shorthand_project_id" type="hidden" value="" />
		<div class="nav-tab-wrapper shorthand-picker-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Shorthand content type', 'shorthand-connect' ); ?>">
			<button class="nav-tab" id="shorthand-stories-tab" type="button" role="tab" aria-controls="shorthand-stories-panel" data-shorthand-tab="stories"><?php esc_html_e( 'Stories', 'shorthand-connect' ); ?></button>
			<?php if ( $can_import_projects ) : ?>
				<button class="nav-tab" id="shorthand-projects-tab" type="button" role="tab" aria-controls="shorthand-projects-panel" data-shorthand-tab="projects"><?php esc_html_e( 'Projects', 'shorthand-connect' ); ?></button>
			<?php endif; ?>
		</div>

		<div id="shorthand-stories-panel" class="shorthand-picker-panel" role="tabpanel" aria-labelledby="shorthand-stories-tab">
			<div id="stories-list">
				<div class="filter-container wp-core-ui">
					<label class="screen-reader-text" for="shorthand-story-search"><?php esc_html_e( 'Search Stories', 'shorthand-connect' ); ?></label>
					<input id="shorthand-story-search" class="search" placeholder="<?php esc_attr_e( 'Search for a Story', 'shorthand-connect' ); ?>" />
					<button class="sort button-secondary desc" type="button" data-sort="updated_timestamp"><?php esc_html_e( 'Edited', 'shorthand-connect' ); ?><span class="sort-icon"></span></button>
				</div>
				<ul class="list stories"></ul>
			</div>
		</div>

		<?php if ( $can_import_projects ) : ?>
			<div id="shorthand-projects-panel" class="shorthand-picker-panel" role="tabpanel" aria-labelledby="shorthand-projects-tab" hidden>
				<?php if ( isset( $_GET['shorthand_project_started'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-success inline"><p><?php esc_html_e( 'Project import started. WordPress will continue it through WP-Cron.', 'shorthand-connect' ); ?></p></div>
				<?php endif; ?>
				<?php if ( isset( $_GET['shorthand_project_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['shorthand_project_error'] ) ) ); ?></p></div>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Choose a Project to import or synchronise all of its Stories.', 'shorthand-connect' ); ?></p>
				<div id="projects-list">
					<div class="filter-container wp-core-ui">
						<label class="screen-reader-text" for="shorthand-project-search"><?php esc_html_e( 'Search Projects', 'shorthand-connect' ); ?></label>
						<input id="shorthand-project-search" class="search" placeholder="<?php esc_attr_e( 'Search for a Project', 'shorthand-connect' ); ?>" />
						<button class="sort button-secondary desc" type="button" data-sort="updated_timestamp"><?php esc_html_e( 'Edited', 'shorthand-connect' ); ?><span class="sort-icon"></span></button>
					</div>
					<ul class="list projects"></ul>
					<div class="project-import-error notice notice-error inline" hidden><p></p></div>
				</div>
			</div>
		<?php endif; ?>
	</div>
		<?php
		wp_register_script(
			'list-js',
			plugin_dir_url( __FILE__ ) . 'js/list.js/v2.3.4/list.min.js',
			array(),
			'2.3.4',
			array()
		);
		wp_enqueue_script( 'list-js' );

		wp_register_script(
			'list-js-stories',
			plugin_dir_url( __FILE__ ) . 'js/connect-stories-list.js',
			array( 'list-js' ),
			SHORTHAND_VERSION,
			array(
				'strategy' => 'defer',
			)
		);
		wp_enqueue_script( 'list-js-stories' );

		wp_register_script(
			'list-js-stories-selected',
			plugin_dir_url( __FILE__ ) . 'js/connect-stories-list-selected.js',
			array( 'list-js-stories' ),
			SHORTHAND_VERSION,
			array(
				'strategy' => 'defer',
			)
		);
		wp_enqueue_script( 'list-js-stories-selected' );
		wp_register_script(
			'fetch-stories',
			plugin_dir_url( __FILE__ ) . 'js/connect-stories-fetch.js',
			array( 'list-js-stories', 'list-js-stories-selected' ),
			SHORTHAND_VERSION,
			array(
				'strategy' => 'defer',
			)
		);
		wp_localize_script(
			'fetch-stories',
			'wp_server',
			array(
				'stories_url'         => esc_url_raw( rest_url( 'shorthand_connect/v1/stories' ) ),
				'projects_url'        => esc_url_raw( rest_url( 'shorthand_connect/v1/projects' ) ),
				'project_import_url'  => esc_url_raw( rest_url( 'shorthand_connect/v1/projects/import' ) ),
				'project_list_url'    => esc_url_raw( add_query_arg( array( 'post_type' => 'shorthand_story', 'shorthand_project_started' => 1 ), admin_url( 'edit.php' ) ) ),
				'placeholder_post_id' => absint( $post->ID ),
				'nonce'               => wp_create_nonce( 'wp_rest' ),
				'active_tab'          => $active_tab,
				'can_import_projects' => $can_import_projects,
				'project_error'       => __( 'The Project import could not be started.', 'shorthand-connect' ),
				'project_saving'      => __( 'Starting Project import…', 'shorthand-connect' ),
			)
		);
		wp_enqueue_script( 'fetch-stories' );
		echo '<div class="clear"></div>';
	}

	// Noncename needed to verify where the data originated
	echo '<input type="hidden" name="eventmeta_noncename" id="eventmeta_noncename" value="' . esc_attr( wp_create_nonce( plugin_basename( __FILE__ ) ) ) . '" />';
}

function shorthand_wpt_shorthand_story_advanced() {
	?>
	<div class="form-check form-switch text-light">
	<input class="form-check-input" type="checkbox" id="show-advanced">
	<label class="form-check-label" for="show-advanced">Show advanced options</label>
	</div>
	<script>
	//var elements = document.querySelectorAll( 'body *' );
	//document.getElementById("postcustom").style.visibility = 0;
	console.log(document.getElementById("postcustom"));
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
	</div>

	<?php

	wp_register_script(
		'connect-form',
		plugin_dir_url( __FILE__ ) . 'js/connect-form.js',
		array(),
		'1.0.0',
		array(
			'strategy' => 'defer',
		)
	);
	wp_enqueue_script( 'connect-form' );
}

/**
 * Shows the Project-level synchronisation control for an imported Project Story.
 *
 * @param string $project_id Shorthand Project ID.
 * @param int    $post_id WordPress post ID.
 */
function shand_wpt_update_project( $project_id, $post_id ) {
	$update_url = wp_nonce_url(
		add_query_arg(
			array(
				'action'     => 'shorthand_update_project',
				'post_id'    => $post_id,
				'project_id' => $project_id,
			),
			admin_url( 'admin-post.php' )
		),
		'shorthand_update_project_' . $post_id
	);
	?>
	<p><?php esc_html_e( 'This will synchronise every Story in this Shorthand Project. Stories removed from the Project will remain in WordPress.', 'shorthand-connect' ); ?></p>
	<div class="publishing-actions">
		<a class="button button-primary button-large" href="<?php echo esc_url( $update_url ); ?>"><?php esc_html_e( 'Update Shorthand Project', 'shorthand-connect' ); ?></a>
	</div>
	<?php if ( current_user_can( 'manage_options' ) && current_user_can( 'delete_post', $post_id ) ) : ?>
		<?php $removal_dialog_id = shorthand_get_project_removal_dialog_id( $project_id ); ?>
		<p><button class="button-link button-link-delete" type="button" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $removal_dialog_id ); ?>" data-shorthand-project-dialog-open="<?php echo esc_attr( $removal_dialog_id ); ?>"><?php esc_html_e( 'Remove Project', 'shorthand-connect' ); ?></button></p>
		<p class="description"><?php esc_html_e( 'This moves every WordPress Story in the Project to Trash. Restoring one Story restores the complete Project.', 'shorthand-connect' ); ?></p>
	<?php endif; ?>
	<?php
}

function shand_add_shorthand_metaboxes() {
	global $post;
	global $noabstract;
	$selected_story = get_post_meta( $post->ID, 'story_id', true );
	$project_id     = get_post_meta( $post->ID, '_shorthand_project_id', true );

	if ( $project_id ) {
		add_meta_box(
			'shorthand_wpt_shorthand_project_update',
			'Update Shorthand Project',
			function () use ( $project_id, $post ) {
				shand_wpt_update_project( $project_id, $post->ID );
			},
			'shorthand_story',
			'side',
			'high'
		);
	} elseif ( ! $selected_story ) {
		add_meta_box(
			'shorthand_wpt_shorthand_story',
			'Select Shorthand Story or Project',
			'shorthand_wpt_shorthand_story',
			'shorthand_story',
			'normal',
			'high'
		);
	}

	if ( ! $noabstract ) {
		add_meta_box(
			'shand_wpt_shorthand_abstract',
			'Add story abstract',
			'shand_wpt_shorthand_abstract',
			'shorthand_story',
			'advanced'
		);
	}

	add_meta_box(
		'shand_wpt_shorthand_extra_html',
		'Add additional HTML',
		'shand_wpt_shorthand_extra_html',
		'shorthand_story',
		'advanced'
	);
}

add_action( 'add_meta_boxes', 'shand_add_shorthand_metaboxes' );

function shand_save_media_fetch( $post_id, $story_id ) {
	$project_id = get_post_meta( $post_id, '_shorthand_project_id', true );
	if ( $project_id && function_exists( 'shorthand_import_project_story' ) ) {
		shorthand_import_project_story( $project_id );
		return;
	}

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
function shorthand_save_shorthand_story( $post_id, $post ) {
	$slug = 'shorthand_story';
	if ( $slug !== $post->post_type ) {
		return;
	}
	if ( get_post_meta( $post_id, '_shorthand_project_id', true ) ) {
		return;
	}

	$profile = shorthand_api_get_profile();
	if ( ( get_post_type( $post ) === 'shorthand_story' ) && ! ( $profile ) ) {
		$uri = Shorthand_Admin::get_page_url();
		/* translators: %s: Shorthand Connect settings URL. */
		$message = sprintf( wp_kses_post( __( 'Could not connect to Shorthand, please check your API token in <a alt="(opens Shorthand Connect plugin settings)" href="%s">Shorthand settings</a>.', 'shorthand-connect' ) ), esc_url( $uri ) );
		wp_die( $message, esc_html__( 'Shorthand is not connected', 'shorthand-connect' ) );
	}

	WP_Filesystem();
	global $wp_filesystem;

	if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
		$creds = request_filesystem_credentials( site_url() );
		wp_filesystem( $creds );
	}

	global $noabstract;

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
			$story_id      =  sanitize_text_field( $_REQUEST['story_id'] );
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
			$assets_path = shorthand_get_story_url( $post_id, $safe_story_id, $story_path );

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
				remove_action( 'save_post', 'shorthand_save_shorthand_story', 10, 3 );
				$post = array(
					'ID'           => $post_id,
					'post_content' => shorthand_abstract_template( $post_id, wp_kses_post( $_REQUEST['abstract'] ), $abstract ),
				);
				wp_update_post( $post );
				add_action( 'save_post', 'shorthand_save_shorthand_story', 10, 3 );
			} else {
				remove_action( 'save_post', 'shorthand_save_shorthand_story', 10, 3 );
				$post = array(
					'ID'           => $post_id,
					'post_content' => '',
				);
				wp_update_post( $post );
				add_action( 'save_post', 'shorthand_save_shorthand_story', 10, 3 );
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

add_action( 'save_post', 'shorthand_save_shorthand_story', 10, 3 );

/* Load Shorthand Template Hook */
function shand_load_single_shorthand_template( $template ) {
	global $post;
	if ( 'shorthand_story' === $post->post_type ) {
		$path = locate_template(
			array(
				'single-shorthand_story.php',
				'templates/single-shorthand_story.php',
				'template-parts/single-shorthand_story.php',
				'single-shorthand-story.php',
				'templates/single-shorthand-story.php',
				'template-parts/single-shorthand-story.php',
			)
		);
		if ( $path ) {
			return $path;
		}
		$plugin_path   = plugin_dir_path( __FILE__ );
		$template_name = 'templates/single-shorthand-story.php';
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
		$post_id = get_post()->ID;
		$meta    = get_post_meta( $post_id );
		$head    = get_shorthandinfo( $meta, 'story_head' );
		echo shorthand_add_runtime_project_permalinks( $head, $post_id );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		$shorthand_templates = array(
			'single-shorthand_story.php',
			'templates/single-shorthand_story.php',
			'template-parts/single-shorthand_story.php',
			'single-shorthand-story.php',
			'templates/single-shorthand-story.php',
			'template-parts/single-shorthand-story.php',
		);

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
	return array_slice( $columns, 0, 2, true )
		+ array(
			'story_id'         => __( 'Shorthand Story ID', 'shorthand-connect' ),
			'shorthand_project' => __( 'Project', 'shorthand-connect' ),
		)
		+ array_slice( $columns, 2, count( $columns ) - 2, true );
}

add_filter( 'manage_shorthand_story_posts_columns', 'shand_add_shorthand_story_columns' );

/**
 * Renders custom column values for shorthand stories in the posts list table.
 *
 * @param string $column  The current column name.
 * @param int    $post_id The current post ID.
 */
function shand_render_shorthand_story_column( $column, $post_id ) {
	if ( 'story_id' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'story_id', true ) );
		return;
	}

	if ( 'shorthand_project' !== $column ) {
		return;
	}

	$project_id = get_post_meta( $post_id, '_shorthand_project_id', true );
	if ( ! $project_id ) {
		echo '<span class="description">' . esc_html__( 'Standalone', 'shorthand-connect' ) . '</span>';
		return;
	}

	$project_title = shorthand_get_project_admin_title( $project_id, $post_id );
	$filter_url    = add_query_arg(
		array(
			'post_type'        => 'shorthand_story',
			'shorthand_project' => $project_id,
		),
		admin_url( 'edit.php' )
	);
	printf(
		'<strong><a href="%1$s">%2$s</a></strong><br><span class="description">%3$s</span>',
		esc_url( $filter_url ),
		esc_html( $project_title ),
		esc_html__( 'Project story', 'shorthand-connect' )
	);
}

add_action( 'manage_shorthand_story_posts_custom_column', 'shand_render_shorthand_story_column', 10, 2 );

/**
 * Resolves a Project title for the posts list without requiring an API request
 * for Stories imported by current plugin versions.
 *
 * @param string $project_id Shorthand Project ID.
 * @param int    $post_id    Optional WordPress post ID.
 * @return string Project title, falling back to its ID.
 */
function shorthand_get_project_admin_title( $project_id, $post_id = 0 ) {
	if ( $post_id ) {
		$stored_title = get_post_meta( $post_id, '_shorthand_project_title', true );
		if ( $stored_title ) {
			return $stored_title;
		}
	}

	static $project_titles = null;
	if ( null === $project_titles ) {
		$project_titles = array();
		$jobs           = get_option( 'shorthand_project_import_jobs', array() );
		if ( is_array( $jobs ) ) {
			foreach ( $jobs as $job_project_id => $job ) {
				if ( ! empty( $job['project']->title ) ) {
					$project_titles[ (string) $job_project_id ] = sanitize_text_field( $job['project']->title );
				}
			}
		}
		$api_projects = shorthand_get_projects();
		if ( is_array( $api_projects ) ) {
			foreach ( $api_projects as $project ) {
				if ( ! empty( $project->id ) && ! empty( $project->title ) ) {
					$project_titles[ sanitize_text_field( $project->id ) ] = sanitize_text_field( $project->title );
				}
			}
		}
	}

	return isset( $project_titles[ $project_id ] ) ? $project_titles[ $project_id ] : $project_id;
}

/**
 * Returns Project IDs represented by local Shorthand Stories.
 *
 * @return array Project IDs keyed by ID with display titles as values.
 */
function shorthand_get_admin_project_options() {
	$post_ids = get_posts(
		array(
			'post_type'              => 'shorthand_story',
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'meta_key'               => '_shorthand_project_id',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);
	$projects = array();
	foreach ( $post_ids as $post_id ) {
		$project_id = get_post_meta( $post_id, '_shorthand_project_id', true );
		if ( $project_id && ! isset( $projects[ $project_id ] ) ) {
			$projects[ $project_id ] = shorthand_get_project_admin_title( $project_id, $post_id );
		}
	}
	natcasesort( $projects );
	return $projects;
}

/**
 * Adds a Project membership filter to the Shorthand Story list table.
 *
 * @param string $post_type Current post type.
 * @param string $which     Filter position.
 */
function shorthand_render_project_posts_filter( $post_type, $which ) {
	if ( 'shorthand_story' !== $post_type || 'top' !== $which ) {
		return;
	}
	$selected = isset( $_GET['shorthand_project'] ) ? sanitize_text_field( wp_unslash( $_GET['shorthand_project'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$projects = shorthand_get_admin_project_options();
	?>
	<label class="screen-reader-text" for="filter-by-shorthand-project"><?php esc_html_e( 'Filter by Shorthand Project', 'shorthand-connect' ); ?></label>
	<select id="filter-by-shorthand-project" name="shorthand_project">
		<option value=""><?php esc_html_e( 'All Projects and Stories', 'shorthand-connect' ); ?></option>
		<option value="standalone" <?php selected( $selected, 'standalone' ); ?>><?php esc_html_e( 'Standalone Stories', 'shorthand-connect' ); ?></option>
		<?php foreach ( $projects as $project_id => $project_title ) : ?>
			<option value="<?php echo esc_attr( $project_id ); ?>" <?php selected( $selected, $project_id ); ?>><?php echo esc_html( $project_title ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

add_action( 'restrict_manage_posts', 'shorthand_render_project_posts_filter', 10, 2 );

/**
 * Filters the Shorthand Story list by Project membership.
 *
 * @param WP_Query $query Current query.
 */
function shorthand_filter_posts_by_project( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'shorthand_story' !== $query->get( 'post_type' ) || empty( $_GET['shorthand_project'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$project_id = sanitize_text_field( wp_unslash( $_GET['shorthand_project'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'standalone' === $project_id ) {
		$meta_query   = $query->get( 'meta_query' );
		$meta_query   = is_array( $meta_query ) ? $meta_query : array();
		$meta_query[] = array(
			'key'     => '_shorthand_project_id',
			'compare' => 'NOT EXISTS',
		);
		$query->set( 'meta_query', $meta_query );
		return;
	}
	$query->set( 'meta_key', '_shorthand_project_id' );
	$query->set( 'meta_value', $project_id );
}

add_action( 'pre_get_posts', 'shorthand_filter_posts_by_project' );

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
function shorthand_shorthand_activate() {

	shand_create_post_type();
	flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules

	// Set config values.
	update_option( 'sh_v2_token', '' );
	update_option( 'sh_permalink', 'shorthand_story' );
	$css_path = '/css/options-default.css';
	$sh_css   = file_get_contents( __DIR__. $css_path ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
	update_option( 'sh_css', $sh_css );
	update_option( 'sh_regex_list', '' );
	update_option( 'sh_media_cron_offload', true );
	update_option( 'sh_disable_acf', false );
}

register_activation_hook( __FILE__, 'shorthand_shorthand_activate' );
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
		$num_changed = 0;
		// Run on posts which have bulk-pull-stories set to true.
		if ( 'bulk-pull-stories' === $action ) {
			foreach ( $post_ids as $post_id ) {
				if ( get_post_meta( $post_id, '_shorthand_project_id', true ) ) {
					continue;
				}
				$story_id = get_post_meta( $post_id, 'story_id', true );
				shand_update_story( $post_id, $story_id );
				$num_changed++;
			}
			$redirect_url = add_query_arg( 'bulk-pulled-stories', $num_changed, $redirect_url );
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
