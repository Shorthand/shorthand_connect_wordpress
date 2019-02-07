<?php

// VERSION 1 API

function sh_get_profile() {

	global $serverURL;
	$token = get_option('sh_token_key');
	$user_id = get_option('sh_user_id');

	$valid_token = false;

	$data = array();

	//Attempt to connect to the server
	if($token && $user_id) {
		$url = $serverURL.'/api/profile/';
		$vars = 'user='.$user_id.'&token='.$token;
		$response = wp_remote_post( $url, array(
			'headers' => array(
				'user-agent'  =>  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8) AppleWebKit/535.6.2 (KHTML, like Gecko) Version/5.2 Safari/535.6.2',
			),
			'body'    => array(
				'user' => $user_id,
				'token' => $token
			),
		));
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode($body);
	}
	return $data;
}

function sh_get_stories() {
	global $serverURL;
	$token = get_option('sh_token_key');
	$user_id = get_option('sh_user_id');

	$valid_token = false;

	$stories = null;

	//Attempt to connect to the server
	if($token && $user_id) {
		$url = $serverURL.'/api/index/';
		$response = wp_remote_post( $url, array(
			'headers' => array(
				'user-agent'  =>  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8) AppleWebKit/535.6.2 (KHTML, like Gecko) Version/5.2 Safari/535.6.2',
			),
			'body'    => array(
				'user' => $user_id,
				'token' => $token
			),
			'timeout' => '240'
		));
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode($body);

		if(isset($data->stories)) {
			$valid_token = true;
			$stories = $data->stories;
		}
	}
	return $stories;
}

function sh_get_story_path($post_id, $story_id) {
	WP_Filesystem();
	$destination = wp_upload_dir();
	$destination_path = $destination['path'].'/shorthand/'.$post_id.'/'.$story_id;
	if(!file_exists($destination_path)) {
		$destination_path = null;
	}
	return $destination_path;
}

function sh_get_story_url($post_id, $story_id) {
	WP_Filesystem();
	$destination = wp_upload_dir();
	$destination_url = $destination['url'].'/shorthand/'.$post_id.'/'.$story_id;
	return $destination_url;
}

function sh_copy_story($post_id, $story_id) {

	// Set the maximum memory limit for the entire operation (this is already called later by unzip_file, but lets do it earlier)
	@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );

	WP_Filesystem();
	$destination = wp_upload_dir();
	$tmpdir = get_temp_dir();
	$destination_path = $destination['path'].'/shorthand/'.$post_id.'/'.$story_id;

	global $serverURL;
	$token = get_option('sh_token_key');
	$user_id = get_option('sh_user_id');

	$valid_token = false;

	$story = array();

	//Attempt to connect to the server
	if($token && $user_id) {
		$url = $serverURL.'/api/story/'.$story_id.'/';
		$vars = 'user='.$user_id.'&token='.$token;
		$zipfile = tempnam($tmpdir, 'sh_zip');
		$response = wp_remote_post( $url, array(
			'headers' => array(
				'user-agent'  =>  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8) AppleWebKit/535.6.2 (KHTML, like Gecko) Version/5.2 Safari/535.6.2',
			),
			'body'    => array(
				'user' => $user_id,
				'token' => $token
			),
			'timeout' => '600',
			'stream' => true,
			'filename' => $zipfile
		));
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode($body);
		if($response['response']['code'] == 200) {
			$unzipfile = unzip_file( $zipfile, $destination_path);
			if ( $unzipfile ) {
				$story['path'] = $destination_path;
			} else {
				$story['error'] = array(
				'pretty' => 'Could not unzip file'
			);
			}
		} else {
			$story['error'] = array(
				'pretty' => 'Could not upload file',
				'error' => curl_error($ch),
				'response' => $response
			);
		}
	}
	return $story;
}

?>
