<?php
/**
 * Bulk story pull functions.
 *
 * @package Shorthand Connect
 */

/**
 * Updates a story for a given post.
 *
 * Fetches story data from the shorthand API, copies story files, updates post
 * metadata, and performs post-processing on the story content.
 * Note: Unzipping a fresh story copy won't update the fields.
 *
 * @param int    $post_id The ID of the post.
 * @param string $story_id The ID of the story.
 */
function shand_update_story( $post_id, $story_id ) {
	if ( get_post_meta( $post_id, '_shorthand_project_id', true ) ) {
		return new WP_Error( 'shorthand_project_story_update_blocked', __( 'Update this Story through its Shorthand Project.', 'shorthand-connect' ) );
	}

	$sh_media_cron_offload = filter_var( get_option( 'sh_media_cron_offload' ), FILTER_VALIDATE_BOOLEAN );
	update_post_meta( $post_id, 'story_id', sanitize_text_field( $story_id ) );

	// Grab JSON for Story & Set into object $obj.
	$obj = shorthand_api_request_json( '/v2/stories/' . rawurlencode( $story_id ) . '/settings', array( 'timeout' => '240' ) );

	$err = sh_copy_story( $post_id, $story_id, $sh_media_cron_offload );
	if ( isset( $err['error'] ) ) {
		update_post_meta( $post_id, 'ERROR', wp_json_encode( $err ) );
		update_post_meta( $post_id, 'story_diagnostic', $err );
		return new WP_Error( 'shorthand_story_import_failed', __( 'Something went wrong while importing the Story.', 'shorthand-connect' ) );
	}

	$safe_story_id = preg_replace( '/\W|_/', '', $story_id );
	$story_path    = sh_get_story_path( $post_id, $safe_story_id );
	// Sometimes the story needs to be gotten twice.
	if ( ! isset( $story_path ) ) {
		$err        = sh_copy_story( $post_id, $story_id, $sh_media_cron_offload );
		$story_path = sh_get_story_path( $post_id, $safe_story_id );
		if ( isset( $err['error'] ) ) {
			update_post_meta( $post_id, 'ERROR', wp_json_encode( $err ) );
			update_post_meta( $post_id, 'story_diagnostic', $err );
			return new WP_Error( 'shorthand_story_import_failed', __( 'Something went wrong while importing the Story.', 'shorthand-connect' ) );
		}
	}

	return shand_apply_story_update( $post_id, $story_id, $obj, $err, $sh_media_cron_offload );
}

/**
 * Applies an extracted Story archive to its WordPress post.
 *
 * @param int         $post_id WordPress post ID.
 * @param string      $story_id Shorthand Story ID.
 * @param object      $obj Story settings response.
 * @param array       $story Extracted Story content.
 * @param bool        $sh_media_cron_offload Whether media fetching is offloaded.
 * @param string|null $story_path_override Validated Story directory to apply.
 * @return true|WP_Error
 */
function shand_apply_story_update( $post_id, $story_id, $obj, $story, $sh_media_cron_offload, $story_path_override = null ) {
	$safe_story_id = preg_replace( '/\W|_/', '', $story_id );
	$story_path    = $story_path_override ? $story_path_override : sh_get_story_path( $post_id, $safe_story_id );

	if ( isset( $story_path ) ) {
		// The story has been uploaded.
		update_post_meta( $post_id, 'story_path', $story_path );

		// Log any story-specific errors to the metadata.
		if ( isset( $story['error'] ) ) {
			update_post_meta( $post_id, 'ERROR', wp_json_encode( $story ) );
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

		$body = shand_fix_content_paths( $assets_path, $story['article'] );
		$head = shand_fix_content_paths( $assets_path, $story['head'] );

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

		$post_data = array(
			'ID'         => $post_id,
			'post_title' => $obj->meta->title,
		);
		wp_update_post( $post_data );

		update_post_meta( $post_id, 'story_body', wp_slash( $body ) );
		update_post_meta( $post_id, 'story_head', wp_slash( $head ) );

		delete_post_meta( $post_id, 'story_diagnostic' );

	} else {
		update_post_meta( $post_id, 'ERROR', wp_json_encode( $story ) );
		update_post_meta( $post_id, 'story_diagnostic', $story );
		return new WP_Error( 'shorthand_story_import_failed', __( 'Something went wrong while importing the Story.', 'shorthand-connect' ) );
	}

	return true;
}
