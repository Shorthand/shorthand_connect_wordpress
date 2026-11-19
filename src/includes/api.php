<?php
/**
 * Shorthand API functions.
 *
 * @package Shorthand Connect
 */

/*
 * Makes a HTTP request of the Shorthand API.
 *
 * In a WP VIP environment, this should fall back to using wp_remote_request
 * when streaming file downloads, as the files may be too large for the timeouts
 * imposed.
 *
 * @param string $url The URI to request, relative to the base API URL
 * @param array $options An array of extra options args to pass to wp_remote_request
 */
function shorthand_api_v2_request( $url, $options ) {
	$token = get_option( 'sh_v2_token' );
	if ( ! $token ) {
		return false;
	}
	global $server_url;
	$url            = $server_url . $url;
	$plugin_path    = plugin_dir_path( __DIR__ ) . '/shorthand-connect.php';
	$plugin_data    = get_file_data( $plugin_path, array( 'Version' => 'Version' ) );
	$plugin_version = $plugin_data['Version'];

	$wp_version = $GLOBALS['wp_version'];
	$user_agent = 'WordPress/' . $wp_version . ' Shorthand/' . $plugin_version;

	$extra_headers = isset( $options['headers'] ) && is_array( $options['headers'] ) ? $options['headers'] : array();
	foreach ( array_keys( $extra_headers ) as $header_name ) {
		if ( in_array( strtolower( $header_name ), array( 'authorization', 'user-agent' ), true ) ) {
			unset( $extra_headers[ $header_name ] );
		}
	}
	$headers         = array_merge(
		array(
			'Authorization' => 'Token ' . $token,
			'user-agent'    => $user_agent,
		),
		$extra_headers
	);

	$request_options = array_merge(
		array(
			'headers'       => $headers,
			'http_api_args' => $options,
		),
		$options
	);
	$request_options['headers'] = $headers;

	if ( function_exists( 'vip_safe_wp_remote_request' ) && ! isset( $request_options['stream'] ) ) {
		$method  = isset( $request_options['method'] ) ? $request_options['method'] : 'GET';
		$timeout = ( 0 === strcasecmp( 'POST', $method ) ) ? 15 : 5;
		return vip_safe_wp_remote_request( $url, false, 1, $timeout, 10, $request_options );
	} else {
     return wp_remote_request( $url, $request_options ); // @codingStandardsIgnoreLine
	}
}

/**
 * Makes a JSON API request to the shorthand API.
 *
 * @param string $url The URL of the API endpoint.
 * @param array  $options Optional arguments for the API request.
 * @return mixed The decoded JSON response, or null if an error occurs.
 */
function shorthand_api_request_json( $url, $options ) {
	$response = shorthand_api_v2_request( $url, $options );
	$body     = wp_remote_retrieve_body( $response );
	return json_decode( $body );
}

/**
 * Retrieves user profile information from the shorthand API.
 *
 * @return object User profile information, including username, gravatar, and other potential data.
 */
function shorthand_api_get_profile() {
	$tokeninfo = array();

	$data = shorthand_api_request_json( '/v2/token-info', array() );
	if ( $data && isset( $data->organisation_id ) ) {
		$tokeninfo['username'] = $data->name . ' (' . $data->token_type . ' Token)';
		$tokeninfo['gravatar'] = $data->logo;
		$tokeninfo             = (object) $tokeninfo;
	}

	return $tokeninfo;
}

/**
 * Finds published WordPress posts for Shorthand Story IDs.
 *
 * @param array $story_ids Shorthand Story IDs.
 * @return array Published WordPress post details keyed by Shorthand Story ID.
 */
function shorthand_get_published_wordpress_stories( $story_ids ) {
	$story_ids = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $story_ids ) ) ) );
	if ( empty( $story_ids ) ) {
		return array();
	}

	$post_ids = get_posts(
		array(
			'post_type'              => 'shorthand_story',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'     => 'story_id',
					'value'   => $story_ids,
					'compare' => 'IN',
				),
			),
		)
	);

	$published = array();
	foreach ( $post_ids as $post_id ) {
		$story_id = get_post_meta( $post_id, 'story_id', true );
		if ( ! $story_id || isset( $published[ $story_id ] ) ) {
			continue;
		}
		$published[ $story_id ] = array(
			'post_id'  => (int) $post_id,
			'edit_url' => current_user_can( 'edit_post', $post_id ) ? (string) get_edit_post_link( $post_id, 'raw' ) : '',
		);
	}

	return $published;
}

/**
 * Retrieves stories from the shorthand API.
 *
 * Fetches a list of stories from the shorthand API, optionally filtered by keyword.
 *
 * @param string $keyword Optional keyword to filter stories by.
 * @return array|null An array of story data, or null if an error occurs or no stories are found.
 */
function shorthand_api_get_stories(  string $keyword = '', string $cursor = '', string $limit = '50' ) {
	$stories = null;
	$url     = '/v2/stories';
	$default_cover_url = plugin_dir_url( dirname( __DIR__ ) . '/shorthand-connect.php' ) . 'images/default.png';
	$url     .= '?limit='.$limit;
	if ( $cursor && $cursor != '') {
		$url .= '&cursor=' . $cursor;
	}
	if ( $keyword && $keyword != '' ) {
		$url .= '&keyword=' . $keyword;
	}
	

	$data = shorthand_api_request_json( $url, array( 'timeout' => '240' ) );
	if ( $data ) {
		$stories = array();
		// Something went wrong.
		if ( isset( $data->status ) && $data->status ) {
			return null;
		}

		foreach ( $data as $storydata ) {
			if ( ! empty( $storydata->projectId ) ) {
				continue;
			}
			$updated_timestamp   = strtotime( esc_html( $storydata->updatedAt ) );
			$updated_at          = $storydata->updatedAt;
			$published_timestamp = ! empty( $storydata->lastPublishedAt ) ? strtotime( $storydata->lastPublishedAt ) : 0;
			$updated             = human_time_diff( $updated_timestamp, current_time( 'timestamp' ) );
			$published           = $published_timestamp ? human_time_diff( $published_timestamp, current_time( 'timestamp' ) ) : '';
			$image               = empty( $storydata->signedCover ) ? $default_cover_url : $storydata->signedCover;
			$stories[]           = array(
				'version_value'       => '' . $storydata->version,
				'title'               => $storydata->title,
				'description'         => $storydata->description,
				'imagealt'            => $storydata->title,
				'image'               => $image,
				'updated_timestamp'   => $updated_timestamp,
				'updated_at'          => $updated_at,
				'updated_value'       => $updated,
				'published_timestamp' => $published_timestamp,
				'published_value'     => $published,
				'published_label'     => $published ? sprintf(
					/* translators: %s: Human-readable elapsed time. */
					__( '%s ago', 'shorthand-connect' ),
					$published
				) : __( 'Not published', 'shorthand-connect' ),
				'story_id'            => $storydata->id,
				'class'               => 'story',
				'wordpress_published'    => 'false',
				'wordpress_status_label' => '',
				'wordpress_action_label' => '',
				'wordpress_url'        => '',
			);
		}

		$published_posts = shorthand_get_published_wordpress_stories( wp_list_pluck( $stories, 'story_id' ) );
		foreach ( $stories as &$story ) {
			if ( ! isset( $published_posts[ $story['story_id'] ] ) ) {
				continue;
			}
			$story['wordpress_published']    = 'true';
			$story['wordpress_status_label'] = __( 'In WordPress', 'shorthand-connect' );
			$story['wordpress_action_label'] = __( 'Edit post', 'shorthand-connect' );
			$story['wordpress_url']          = $published_posts[ $story['story_id'] ]['edit_url'];
		}
		unset( $story );
	}

	return $stories;
}

/**
 * Retrieves Projects from the Shorthand API for the admin card picker.
 *
 * @return array|WP_Error Project card data, or an API error.
 */
function shorthand_api_get_projects() {
	$projects = shorthand_get_projects();
	if ( is_wp_error( $projects ) ) {
		return $projects;
	}

	$cards             = array();
	$project_story_ids = array();
	$all_story_ids     = array();
	$default_cover_url = plugin_dir_url( dirname( __DIR__ ) . '/shorthand-connect.php' ) . 'images/default.png';
	foreach ( $projects as $project ) {
		if ( ! isset( $project->id, $project->title, $project->stories, $project->createdAt ) || ! is_array( $project->stories ) ) {
			continue;
		}

		$updated_at          = ! empty( $project->editedAt ) ? $project->editedAt : $project->createdAt;
		$updated_timestamp   = strtotime( $updated_at );
		$updated             = $updated_timestamp ? human_time_diff( $updated_timestamp, current_time( 'timestamp' ) ) : '';
		$image               = ! empty( $project->signedCover ) ? $project->signedCover : ( ! empty( $project->cover ) ? $project->cover : $default_cover_url );
		$story_ids                            = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', wp_list_pluck( $project->stories, 'id' ) ) ) ) );
		$project_story_ids[ $project->id ]    = $story_ids;
		$all_story_ids                       = array_merge( $all_story_ids, $story_ids );
		$cards[]                             = array(
			'title'               => $project->title,
			'imagealt'            => $project->title,
			'image'               => $image,
			'updated_timestamp'   => $updated_timestamp,
			'updated_value'       => $updated,
			'project_id'          => $project->id,
			'story_count'         => count( $project->stories ),
			'story_count_value'   => count( $project->stories ),
			'wordpress_published'    => 'false',
			'wordpress_status_label' => '',
			'wordpress_action_label' => '',
			'wordpress_url'        => '',
		);
	}

	$published_posts = shorthand_get_published_wordpress_stories( $all_story_ids );
	foreach ( $cards as &$card ) {
		$story_ids = isset( $project_story_ids[ $card['project_id'] ] ) ? $project_story_ids[ $card['project_id'] ] : array();
		if ( empty( $story_ids ) || array_diff( $story_ids, array_keys( $published_posts ) ) ) {
			continue;
		}
		$card['wordpress_published']    = 'true';
		$card['wordpress_status_label'] = __( 'In WordPress', 'shorthand-connect' );
		$card['wordpress_action_label'] = __( 'View stories', 'shorthand-connect' );
		$card['wordpress_url']          = add_query_arg(
			array(
				'post_type'         => 'shorthand_story',
				'shorthand_project' => $card['project_id'],
			),
			admin_url( 'edit.php' )
		);
	}
	unset( $card );

	return $cards;
}

/**
 * Gets the path to the story directory.
 *
 * Determines the path to the directory where story files for a specific post
 * and story ID should be stored.
 *
 * @param int    $post_id The ID of the post.
 * @param string $story_id The ID of the story.
 * @return string|null The path to the story directory, or null if the directory doesn't exist or an error occurs.
 */
function sh_get_story_path( $post_id, $story_id ) {
	init_wp_filesystem();
	$destination      = wp_upload_dir();
	$destination_path = $destination['path'] . '/shorthand/' . $post_id . '/' . $story_id;

	// On WP VIP, folders in the uploads dir always exist.
	if ( ! file_exists( $destination_path ) ) {
		$destination_path = null;
	}

	$destination_path = apply_filters( 'sh_get_story_path', $destination_path, $destination );

	return $destination_path;
}

/**
 * Gets the URL of the story directory.
 *
 * Determines the URL of the directory where story files for a specific post
 * and story ID are located.
 *
 * @param int         $post_id The ID of the post.
 * @param string      $story_id The ID of the story.
 * @param string|null $story_path Optional resolved filesystem path to the story directory.
 * @return string The URL of the story directory.
 */
function shorthand_get_story_url( $post_id, $story_id, $story_path = null ) {
	init_wp_filesystem();
	$destination      = wp_upload_dir();
	$destination_url  = $destination['url'] . '/shorthand/' . $post_id . '/' . $story_id;
	$resolved_path    = $story_path ? $story_path : sh_get_story_path( $post_id, $story_id );
	$normalized_path  = $resolved_path ? wp_normalize_path( untrailingslashit( $resolved_path ) ) : null;
	$content_dir      = wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) );
	$uploads_base_dir = ! empty( $destination['basedir'] ) ? wp_normalize_path( untrailingslashit( $destination['basedir'] ) ) : null;

	if ( $normalized_path && ( $normalized_path === $content_dir || 0 === strpos( $normalized_path, $content_dir . '/' ) ) ) {
		$relative_path   = ltrim( substr( $normalized_path, strlen( $content_dir ) ), '/' );
		$destination_url = content_url( $relative_path );
	} elseif ( $normalized_path && $uploads_base_dir && ( $normalized_path === $uploads_base_dir || 0 === strpos( $normalized_path, $uploads_base_dir . '/' ) ) ) {
		$relative_path   = ltrim( substr( $normalized_path, strlen( $uploads_base_dir ) ), '/' );
		$destination_url = trailingslashit( $destination['baseurl'] ) . $relative_path;
	}

	$destination_url = apply_filters( 'shorthand_get_story_url', $destination_url, $post_id, $story_id, $resolved_path, $destination );

	return $destination_url;
}

function sh_copy_story( $post_id, $story_id, $without_assets = false, $assets_only = false ) {

	wp_raise_memory_limit( 'admin' );
	init_wp_filesystem();
	$destination      = wp_upload_dir();
	$tmpdir           = get_temp_dir();
	$destination_path = $destination['path'] . '/shorthand/' . $post_id;
	$story            = array();
	$safe_story_id    = preg_replace( '/\W|_/', '', $story_id );

	// Attempt to connect to the server.
	$zip_file = wp_tempnam( 'sh_zip', $tmpdir );
	$response = shorthand_api_v2_request(
		'/v2/stories/' . rawurlencode( $story_id ) . ( $without_assets ? '?without_assets=true' : '' ) . ( $assets_only ? '?assets_only=true' : '' ),
		array(
			'timeout'  => '600',
			'stream'   => true,
			'filename' => $zip_file,
		)
	);
	if ( is_wp_error( $response ) ) {
		$story['error'] = array(
			'pretty' => ' Request to Shorthand failed',
			'error'  => $response->get_error_message(),
		);
	} elseif ( ! $response || 200 !== $response['response']['code'] ) {
		$story['error'] = array(
			'pretty'   => 'Request to Shorthand failed; check the token is configured correctly',
			'error'    => 'unexpected response',
			'response' => $response,
		);
	} else {
		$story = extract_story_content( $zip_file, $destination_path, $safe_story_id );
	}

	do_action( 'sh_copy_story', $post_id, $story_id, $story );
	wp_delete_file( $zip_file );
	return $story;
}

/**
 * Initializes the WordPress filesystem.
 *
 * Ensures that the WordPress filesystem is available for file operations.
 * If not, it requests filesystem credentials and re-initializes the filesystem.
 */
function init_wp_filesystem() {
	WP_Filesystem();
	global $wp_filesystem;
	if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
		$creds = request_filesystem_credentials( site_url() );
		wp_filesystem( $creds );
	}
}

/**
 * Extracts story content from a ZIP file.
 *
 * Extracts the 'head.html' and 'article.html' files from a given ZIP file
 * into a specified destination path.
 *
 * @param string $zip_file The path to the ZIP file.
 * @param string $destination_path The path to the extraction destination.
 * @param string $story_id The ID of the story.
 * @return array An array containing extracted content, path, or error information.
 */
function extract_story_content( $zip_file, $destination_path, $story_id ) {
	// WP VIP HOSTING COMPATIBILITY
	$zip = new ZipArchive();
	if ( $zip->open( $zip_file ) ) {
		wp_mkdir_p( $destination_path . '/' . $story_id );
		$head    = $zip->getFromName( 'head.html' );
		$article = $zip->getFromName( 'article.html' );
		$zip->extractTo( $destination_path . '/' . $story_id );
		$zip->close();
		if ( is_wp_error( $zip ) ) {
			$story['error'] = array(
				'pretty'      => 'Could not copy story into Wordpress',
				'error'       => $zip->get_error_message(),
				'unzip error' => $zip_file,
			);
			if ( function_exists( 'wp_filesize' ) ) {
				$story['error']['downloaded zip size'] = wp_filesize( $zip_file );
			}
		} else {
			$story = array(
				'head'    => false === $head ? '' : $head,
				'article' => false === $article ? '' : $article,
				'path'    => $destination_path . '/' . $story_id,
			);
		}
	} else {
		$story['error'] = array(
			'pretty' => 'Could not unzip file',
			'error'  => 'extraction failed',
		);
	}

	return $story;
}
