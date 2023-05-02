<?php
/*
/*
 Name: Update Story Meta Fields Function */
/*
 Desc: This function sets each custom meta field, should be paired with sh_copy_story().
/*
 Note: Unzipping a fresh story copy won't update the fields
*/
function shand_update_story( $post_id, $story_id ) {
	init_WP_Filesystem();
	global $wp_filesystem;
	global $post;

	$sh_media_cron_offload = filter_var( get_option( 'sh_media_cron_offload' ), FILTER_VALIDATE_BOOLEAN );
	$safe_story_id         = preg_replace( '/\W|_/', '', $story_id );
	update_post_meta( $post_id, $story_id, sanitize_text_field( $safe_story_id ) );

	// Grab JSON for Story & Set into object $obj
	$obj = sh_v2_api_get_json( '/v2/stories/' . $story_id . '/settings', array( 'timeout' => '240' ) );

	$err = sh_copy_story( $post_id, $safe_story_id, $sh_media_cron_offload );

	$story_path = sh_get_story_path( $post_id, $safe_story_id );
	// Sometimes the story needs to be gotten twice
	if ( ! isset( $story_path ) ) {
		$err        = sh_copy_story( $post_id, $safe_story_id, $sh_media_cron_offload );
		$story_path = sh_get_story_path( $post_id, $safe_story_id );

	}
	if ( $sh_media_cron_offload ) {
		update_post_meta( $post_id, 'media_status', '[Awaiting media fetch...]' );
		wp_schedule_single_event( time() + 30, 'sh_media_fetch', array( $post_id, $safe_story_id ) );
	}

	if ( isset( $story_path ) ) {
		// The story has been uploaded
		update_post_meta( $post_id, 'story_path', $story_path );

		// Log any story-specific errors to the metadata
		if ( isset( $err['error'] ) ) {
			update_post_meta( $post_id, 'ERROR', json_encode( $err ) );
		} else {
			delete_post_meta( $post_id, 'ERROR' );
		}

		// Get path to the assets
		$assets_path = sh_get_story_url( $post_id, $safe_story_id );

		// Save the head and body
				$head_file = $story_path . '/head.html';
		$article_file      = $story_path . '/article.html';

		$post_processing_queries = json_decode( base64_decode( get_option( 'sh_regex_list' ) ) );

		$body = shand_fix_content_paths( $assets_path, defined( 'WPCOM_IS_VIP_ENV' ) ? file_get_contents( $article_file ) : $wp_filesystem->get_contents( $article_file ) );
		$head = shand_fix_content_paths( $assets_path, defined( 'WPCOM_IS_VIP_ENV' ) ? file_get_contents( $head_file ) : $wp_filesystem->get_contents( $head_file ) );

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
		update_post_meta( $post_id, 'story_diagnostic', $err );
		echo 'Something went wrong, please try again';
		print_r( $err );
		die();
	}

}
