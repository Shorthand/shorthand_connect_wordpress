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

	$request_options = array_merge(
		array(
			'headers'       => array(
				'Authorization' => 'Token ' . $token,
				'user-agent'    => $user_agent,
			),
			'http_api_args' => $options,
		),
		$options
	);

	if ( function_exists( 'vip_safe_wp_remote_request' ) && ! isset( $request_options['stream'] ) ) {
		$timeout = ( 0 === strcasecmp( 'POST', $request_options['method'] ) ) ? 15 : 5;
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
 * Retrieves stories from the shorthand API.
 *
 * Fetches a list of stories from the shorthand API, optionally filtered by keyword.
 *
 * @param string $keyword Optional keyword to filter stories by.
 * @return array|null An array of story data, or null if an error occurs or no stories are found.
 */
function shorthand_api_get_stories( string $keyword = '' ) {
	$stories = null;
	$url     = '/v2/stories';
	if ( $keyword ) {
		$url .= '?keyword=' . $keyword;
	}

	$data = shorthand_api_request_json( $url, array( 'timeout' => '240' ) );
	if ( $data ) {
		$stories = array();
		// Something went wrong.
		if ( isset( $data->status ) && $data->status ) {
			return null;
		}

		foreach ( $data as $storydata ) {
			$updated_timestamp   = strtotime( esc_html( $storydata->updatedAt ) );
			$published_timestamp = strtotime( esc_html( $storydata->lastPublishedAt ) );
			$updated             = human_time_diff( $updated_timestamp, current_time( 'timestamp' ) );
			$published           = human_time_diff( $published_timestamp, current_time( 'timestamp' ) );
			$stories[]           = array(
				'version_value'       => '' . $storydata->version,
				'title'               => $storydata->title,
				'description'         => $storydata->description,
				'imagealt'            => $storydata->title,
				'image'               => $storydata->signedCover,
				'updated_timestamp'   => $updated_timestamp,
				'updated_value'       => $updated,
				'published_timestamp' => $published_timestamp,
				'published_value'     => $published,
				'story_id'            => $storydata->id,
				'class'               => 'story',
			);
		}
	}

	return $stories;
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
 * @param int    $post_id The ID of the post.
 * @param string $story_id The ID of the story.
 * @return string The URL of the story directory.
 */
function shorthand_get_story_url( $post_id, $story_id ) {
	init_wp_filesystem();
	$destination     = wp_upload_dir();
	$destination_url = $destination['url'] . '/shorthand/' . $post_id . '/' . $story_id;
	$destination_url = apply_filters( 'shorthand_get_story_url', $destination_url );

	return $destination_url;
}

function sh_copy_story( $post_id, $story_id, $without_assets = false, $assets_only = false ) {

	wp_raise_memory_limit( 'admin' );
	init_wp_filesystem();
	$destination      = wp_upload_dir();
	$tmpdir           = get_temp_dir();
	$destination_path = $destination['path'] . '/shorthand/' . $post_id;
	$story            = array();

	// Attempt to connect to the server.
	$zip_file = wp_tempnam( 'sh_zip', $tmpdir );
	$response = shorthand_api_v2_request(
		'/v2/stories/' . $story_id . ( $without_assets ? '?without_assets=true' : '' ) . ( $assets_only ? '?assets_only=true' : '' ),
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
		$story = extract_story_content( $zip_file, $destination_path, $story_id );
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
