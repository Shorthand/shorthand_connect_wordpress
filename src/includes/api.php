<?php

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
function sh_v2_api_request( $url, $options ) {
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

function sh_v2_api_request_json( $url, $options ) {
	$response = sh_v2_api_request( $url, $options );
	$body     = wp_remote_retrieve_body( $response );
	return json_decode( $body );
}

function sh_get_profile() {
	$tokeninfo = array();

	$data = sh_v2_api_request_json( '/v2/token-info', array() );
	if ( $data && isset( $data->organisation_id ) ) {
		$tokeninfo['username'] = $data->name . ' (' . $data->token_type . ' Token)';
		$tokeninfo['gravatar'] = $data->logo;
		$tokeninfo             = (object) $tokeninfo;
	}

	return $tokeninfo;
}

function sh_get_stories() {
	$stories = null;

	$data = sh_v2_api_request_json( '/v2/stories', array( 'timeout' => '240' ) );
	if ( $data ) {
		$stories = array();
		// Something went wrong
		if ( isset( $data->status ) && $data->status ) {
			return null;
		}
		foreach ( $data as $storydata ) {
			$story     = array(
				'image'         => $storydata->signedCover,
				'id'            => $storydata->id,
				'metadata'      => (object) array(
					'description' => $storydata->description,
				),
				'title'         => $storydata->title,
				'story_version' => '' . $storydata->version,
			);
			$stories[] = (object) $story;
		}
	}

	return $stories;
}

function sh_get_story_path( $post_id, $story_id ) {
	init_wp_filesystem();
	$destination      = wp_upload_dir();
	$destination_path = $destination['path'] . '/shorthand/' . $post_id . '/' . $story_id;

	// on WP VIP, folders in the uploads dir always exist
	if ( ! file_exists( $destination_path ) ) {
		$destination_path = null;
	}

	$destination_path = apply_filters( 'sh_get_story_path', $destination_path, $destination );

	return $destination_path;
}

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

	// Attempt to connect to the server
	$zip_file          = wp_tempnam( 'sh_zip', $tmpdir );
	$response          = sh_v2_api_request(
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
		$story = extractStoryContent( $zip_file, $destination_path, $story_id );
	}

	do_action( 'sh_copy_story', $post_id, $story_id, $story );
	wp_delete_file( $zip_file );
	return $story;
}


function init_wp_filesystem() {
	WP_Filesystem();
	global $wp_filesystem;
	if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
		$creds = request_filesystem_credentials( site_url() );
		wp_filesystem( $creds );
	}
}

function extractStoryContent( $zip_file, $destination_path, $story_id ) {
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
